<?php
require_once './common.php';

class Web{
    private static $_instance;

    public static function run(){
        global $app_path;

        self::$_instance = new swoole_http_server('0.0.0.0',8080);

        //参数配置
        self::$_instance->set([
            'worker_num' => 1,
            'daemonize ' => 1,
            'pid_file'=>$app_path.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'web.pid',
        ]);

        //开启进程
        self::$_instance->on('start',function($server)use($app_path){

        });

        //接受请求处理
        self::$_instance->on('request',function($request, $response)use($app_path){
            //实例化全局类
            $controller = new lib\WebResponse($request);

            //判断客户端session是否存在
            if(!$controller->chechSessionId()){
                $response->cookie('SID',$controller->getSessionId(),time()+86400);
            }

            //业务处理
            $result = $controller->deal();

            //设置相应头信息
            $response->status($controller->status_code);
            $response->header('Content-Type',$controller->content_type[$controller->type]);
            unset($controller);

            //发送结果
            $response->end($result);
        });

        //启动服务
        self::$_instance->start();
    }
}

Web::run();