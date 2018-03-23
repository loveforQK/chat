<?php
require_once './common.php';

class Server{
    private static $_instance;

    public static function run(){
        global $app_path;
        self::$_instance = new swoole_websocket_server('0.0.0.0',1234);

        //定义table
        $table = new swoole_table(1024);
        $table->column('value', swoole_table::TYPE_STRING,50);
        $table->create();

        self::$_instance->table = $table;

        //参数配置
        self::$_instance->set([
            'worker_num' => 1,
            'daemonize ' => false,
        ]);

        //主进程回调-单进程
        self::$_instance->on('start',function($server)use($app_path){
            //文件保存主进程ID，方便平滑重启
            file_put_contents($app_path.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'server_pid.log',$server->master_pid);
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

            $old_fd = $server->table->get('u_'.$name)['value'];
            if(!empty($old_fd) && $old_fd != $request->fd){
                $server->table->del('i_'.$old_fd);
                $server->push($old_fd,json_encode(['type'=>'error','msg'=>'Login repeat']));
                echo "close old connect of the user\n";
            }

            $server->table->set('u_'.$name,['value'=>$request->fd]);
            $server->table->set('i_'.$request->fd,['value'=>$name]);

            echo "user build connect\n";

            //广播上线通知（排除自身）
            $list = [];
            foreach($server->connections  as $fd) {
                if($fd == $request->fd || $fd == $old_fd){
                    continue;
                }

                $temp = $server->table->get('i_'.$fd)['value'];
                if(empty($temp)){
                    continue;
                }

                //仅推送在线用户
                $server->push($fd,json_encode(['type'=>'online','user'=>$name]));
                $list[] = $temp;

            }
            $list[] = $name;

            //拉去在线用户列表
            $server->push($request->fd,json_encode(['type'=>'users','list'=>$list]));
            return true;
        });

        //发送消息-单进程
        self::$_instance->on('message',function($server, $frame){
            $msg = preg_replace("/[^a-zA-Z0-9_.-]+/","", $frame->data);
            $msg = substr($msg,0,50);
            if(empty($msg)){
                return true;
            }

            $params = [
                'type'=>'say',
                'msg'=>$msg,
                'user'=>$server->table->get('i_'.$frame->fd)['value']
            ];

            foreach($server->connections  as $fd) {
                $temp = $params;
                if($fd == $frame->fd){
                    $temp['user'] = 'I';
                }
                $server->push($fd,json_encode($temp));
            }
        });

        //关闭连接-单进程
        self::$_instance->on('close',function($server, $fd){
            $user = $server->table->get('i_'.$fd)['value'];
            $old_fd = $server->table->get('u_'.$user)['value'];

            //如果用户已创建新连接 就不需要删除，
            if($old_fd == $fd){
                $server->table->del('u_'.$user);
            }
            //删除连接标识
            $server->table->del('i_'.$fd);

            foreach($server->connections  as $v) {
                if($fd == $v){
                    continue;
                }
                $server->push($v,json_encode(['type'=>'offline','user'=>$user]));
            }
        });

        self::$_instance->start();
    }
}

Server::run();