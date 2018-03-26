<?php
require_once './common.php';
$worker_number = 2;

class Server{
    private static $_instance;

    public static function run(){
        global $app_path,$worker_number;
        self::$_instance = new swoole_websocket_server('0.0.0.0',1234,SWOOLE_BASE);

        //定义table
        $table = new swoole_table(1024);
        $table->column('value', swoole_table::TYPE_STRING,50);
        $table->create();

        self::$_instance->table = $table;

        //参数配置
        self::$_instance->set([
            'worker_num' => $worker_number,
            'task_worker_num' => 4,
            'daemonize ' => 1,
            'dispatch_mode' => 3,//忙闲分配
            'heartbeat_check_interval' => 3600,//一小时检测一次，主动关闭超出一小时未发送信息
            'heartbeat_idle_time' => 3600,
            'pid_file'=>$app_path.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'server.pid',
        ]);

        //主进程回调-单进程
        self::$_instance->on('start',function($server)use($app_path){

        });

        //worker进程回调-单进程
        self::$_instance->on('workerstart',function($server, $worker_id){

        });

        //建立连接-单进程
        self::$_instance->on('open',function($server, $request){
            //没有session
            if(!isset($request->cookie['SID']) || !lib\Tools::checkLetterNumber($request->cookie['SID'])){
                $server->push($request->fd,json_encode(['type'=>'error','msg'=>'No auth']));
                return true;
            }

            //尚未登录或者登录过期
            $name = lib\FileCache::instance()->get($request->cookie['SID'],'session');
            if(empty($name)){
                $server->push($request->fd,json_encode(['type'=>'error','msg'=>'No auth']));
                return true;
            }

            //获取在线用户列表
            $info = $server->table->get('l_users');
            if(empty($info) || !isset($info['value'])){
                $logined = [];
            }else{
                $logined = json_decode($info['value'],true);
            }
            $logined[$request->fd] = $server->worker_id;//存储数据结构


            //判断是否重复登录
            $oldinfo = $server->table->get('u_'.$name);
            if(isset($oldinfo['value']) && !empty($oldinfo['value']) && $oldinfo['value'] != $request->fd){
                if(isset($logined[$oldinfo['value']])){
                    unset($logined[$oldinfo['value']]);
                    //注入重复登录事件回调
                    $server->task(['type'=>'repeat','fd'=>$oldinfo['value'],'wid'=>$logined[$oldinfo['value']]],3);
                }
            }

            $server->table->set('u_'.$name,['value'=>$request->fd]);
            $server->table->set('i_'.$request->fd,['value'=>$name]);
            $server->table->set('l_users',['value'=>json_encode($logined)]);

            echo "worker_id:{$server->worker_id},user{$request->fd} build connect\n";

            //广播上线用户
            $task_id = $server->task(['type'=>'online','user'=>$name,'fd'=>$request->fd],0);

            //推送用户列表到当前账户
            $list = [];
            foreach($logined as $k=>$v){
                $result = $server->table->get('i_'.$k);
                $list[] = isset($result['value'])?$result['value']:'none';
            }
            $server->push($request->fd,json_encode(['type'=>'users','list'=>$list]));
            unset($oldinfo,$list,$result,$logined);
            return true;
        });

        //发送消息-单进程
        self::$_instance->on('message',function($server, $frame){
            $msg = preg_replace("/[^a-zA-Z0-9_|.| |-]+/","", $frame->data);
            $msg = substr($msg,0,50);
            if(empty($msg)){
                return true;
            }

            $params = [
                'type'=>'say',
                'msg'=>$msg,
                'user'=>$frame->fd
            ];

            $task_id = $server->task($params,1);//投入第二个任务进程

            return true;
        });

        //关闭连接-单进程
        self::$_instance->on('close',function($server, $fd){
            $user   = $server->table->get('i_'.$fd)['value'];
            $new_fd = $server->table->get('u_'.$user)['value'];

            //如果用户已创建新连接 就不需要删除，
            if($new_fd == $fd){
                $server->table->del('u_'.$user);
            }

            //删除连接标识
            $server->table->del('i_'.$fd);

            //更新用户在线列表
            $list = $server->table->get('l_users')['value'];
            $list = json_decode($list,true);
            if(isset($list[$fd])){
                unset($list[$fd]);
                $server->table->set('l_users',['value'=>json_encode($list)]);
            }

            $task_id = $server->task(['type'=>'offline','user'=>$user],2);//投入第二个任务进程
            return $task_id;
        });

        //定义异步任务-单进程
        self::$_instance->on('task',function($server, $task_id, $src_worker_id,$data){
            global $worker_number;
            if($server->worker_id == $worker_number+3){
                $server->sendMessage($data,$data['wid']);
                return $task_id;
            }

            //广播消息，遍历所有进程发送进程消息
            for($i=0;$i<$worker_number;$i++){
                $server->sendMessage($data,$i);
            }
            return $task_id;
        });

        //任务完成回调-单进程
        self::$_instance->on('finish',function($serv, $task_id, $data){

        });

        //接受管道信息
        self::$_instance->on('pipemessage',function($server, $src_worker_id, $data){
            global $worker_number;
            //根据不同任务进程ID执行不同回调
            switch($src_worker_id){
                case $worker_number:
                    //广播消息，上线通知
                    $up_fd = $data['fd'];
                    unset($data['fd']);
                    if(!empty($server->connections)){
                        foreach($server->connections as $fd){
                            if($fd == $up_fd){
                                continue;
                            }
                            $server->push($fd,json_encode($data));
                        }
                    }
                    break;
                case $worker_number+1:
                    //广播消息，发送消息
                    if(!empty($server->connections)){
                        foreach($server->connections as $fd){
                            $temp = $data;
                            if($temp['user'] == $fd){
                                $temp['user'] = 'I';
                            }else{
                                $info = $server->table->get('i_'.$temp['user']);
                                if(!isset($info['value'])){
                                    $temp['user'] = 'none';
                                }else{
                                    $temp['user'] = $info['value'];
                                }
                            }
                            $server->push($fd,json_encode($temp));
                        }
                        unset($temp);
                    }

                    break;
                case $worker_number+2:
                    //广播消息，下线通知
                    if(!empty($server->connections)){
                        foreach($server->connections as $fd){
                            $server->push($fd,json_encode($data));
                        }
                    }
                    break;
                case $worker_number+3:
                    //关闭连接进程
                    $server->close($data['fd']);
                    break;
                default:break;
            }
        });

        self::$_instance->start();
    }
}

Server::run();