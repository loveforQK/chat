<?php
$pid_path = "./runtime/pid.txt";//进程ID文件
$web_root = "./html/";//站点资源目录
$content_type = ['html'=>'text/html','js'=>'application/javascript','css'=>'text/css'];//资源输出类型

$http = new swoole_http_server('0.0.0.0',80);

$http->set([
    'worker_num' => 1,
    'daemonize ' => false
]);

$http->on('start',function($server){
    global $pid_path;
    file_put_contents($pid_path,$server->master_pid);
});

$http->on('request',function($request, $response){
    global $web_root,$content_type;

    $filename = substr($request->server['request_uri'],1);
    if(empty($filename)){
        $filename = 'index.html';
        $ext = 'html';
    }else{
        $ext  = pathinfo($filename,PATHINFO_EXTENSION);
        $last = substr($filename,-1);
        if($ext == ''){
            $ext = 'html';
            $filename .= ($last == '/')?'index.html':'/index.html';
        }
    }
    if(!isset($content_type[$ext])){
        $ext = 'html';
    }

    //判断文件是否存在
    $file = $web_root.$filename;

    if(!file_exists($file)){
        $response->status(404);
        $response->end('<h2>404 Not Found</h2>');
    }else{
        $response->header('Content-Type',$content_type[$ext]);
        $response->end(file_get_contents($file));
    }
});

$http->start();