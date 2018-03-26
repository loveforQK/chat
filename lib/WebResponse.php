<?php
namespace lib;

class WebResponse{
    public $html_path = '';
    public $session_path = '';
    public $type = 'html';
    public $status_code = 200;
    public $content_type = [
        'html'=>'text/html',
        'js'=>'application/javascript',
        'css'=>'text/css',
        'json'=>'application/json'
    ];
    private $php_api = [
        'api/islogin'=>'api_islogin',
        'api/login'=>'api_login'
    ];
    private $userlist = [
        'admin1'=>'111111',
        'admin2'=>'111111',
        'admin3'=>'111111',
        'admin4'=>'111111',
        'admin5'=>'111111',
        'admin6'=>'111111',
        'admin7'=>'111111',
        'admin8'=>'111111',
        'admin9'=>'111111',
        'admin10'=>'111111',
    ];
    private $server = null;
    private $post = null;
    private $get = null;
    private $cookie = null;
    private $sessionId = null;

    public function __construct($request){
        global $app_path;
        $this->html_path = $app_path.DIRECTORY_SEPARATOR.'html';
        $this->session_path = $app_path.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'session';
        $this->server = isset($request->server)?$request->server:[];
        $this->get = isset($request->get)?$request->get:[];
        $this->cookie = isset($request->cookie)?$request->cookie:[];
        $this->post = isset($request->post)?$request->post:[];
    }

    public function deal(){
        $filename = substr($this->server['request_uri'],1);

        //动态接口路由
        if(!empty($filename) && array_key_exists($filename,$this->php_api)){
            $method = $this->php_api[$filename];
            $this->type = 'json';
            try{
                return $this->$method();
            }catch (\Exception $e){
                $this->status_code = 500;
                return 'Line:'.$e->getLine().PHP_EOL.'File:'.$e->getFile().PHP_EOL.'Msg:'.$e->getMessage();
            }
        }

        //静态资源
        if(empty($filename)){
            $filename = 'index.html';
            $this->type = 'html';
        }else{
            $this->type  = pathinfo($filename,PATHINFO_EXTENSION);
            $last = substr($filename,-1);
            if($this->type == ''){
                $this->type = 'html';
                $filename .= ($last == '/')?'index.html':'/index.html';
            }
        }

        if(!isset($this->content_type[$this->type])){
            $this->type = 'html';
        }

        //判断文件是否存在
        $file = $this->html_path.DIRECTORY_SEPARATOR.$filename;

        if(!file_exists($file)){
            $this->type = 'html';
            $this->status_code = 404;
            return '<h2>404 Not Found</h2>';
        }

        return file_get_contents($file);
    }

    //判断sessionID是否存在
    public function chechSessionId(){
        if(!isset($this->cookie['SID']) || empty($this->cookie['SID'])){
            return false;
        }

        if(!Tools::checkLetterNumber($this->cookie['SID'])){
            return false;
        }

        $this->sessionId = $this->cookie['SID'];
    }

    //获取随机字符串
    public function getSessionId(){
        if($this->sessionId === null){
            $this->sessionId = md5(microtime(true).rand(1,1000));
        }

        return $this->sessionId;

    }

    //检测是否登录
    public function api_islogin(){
        $name = FileCache::instance()->get($this->getSessionId(),'session');
        if($name){
            return json_encode(['code'=>1,'msg'=>'logined','name'=>$name]);
        }else{
            return json_encode(['code'=>0,'msg'=>'no login']);
        }
    }

    //登录事件
    public function api_login(){
        $name = isset($this->post['name'])?$this->post['name']:'';
        $pwd = isset($this->post['pwd'])?$this->post['pwd']:'';
        if(empty($name) || empty($pwd)){
            return json_encode(['code'=>0,'msg'=>'Login Failed']);
        }

        if(!isset($this->userlist[$name]) || $this->userlist[$name] != $pwd){
            return json_encode(['code'=>0,'msg'=>'Login Failed']);

        }

        FileCache::instance()->set($this->getSessionId(),$name,'session',86400);

        return json_encode(['code'=>1,'msg'=>'Login Success']);
    }
}