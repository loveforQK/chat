<?php
namespace lib;

class FileCache{
    private $path = '';
    private static $_instance = null;

    public function __construct(){
        global $app_path;
        $this->path = $app_path.DIRECTORY_SEPARATOR.'runtime';
    }

    public static function instance(){
        if(self::$_instance === null){
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function set($key,$val,$category = '',$duration = 0){
        $file = $this->path;
        if(!empty($category)){
            $file .= DIRECTORY_SEPARATOR.$category;

            if(!is_dir($file)){
                @mkdir($file,0777,true);
            }
        }

        $file .= DIRECTORY_SEPARATOR.md5($key).'.data';
        if(@file_put_contents($file, $val, LOCK_EX) !== false){
            if($duration === 0){
                $duration = 31536000;//一年
            }
            return @touch($file, $duration + time());
        }else{
            return false;
        }
    }

    public function get($key,$category = ''){
        $file = $this->path;
        if(!empty($category)){
            $file .= DIRECTORY_SEPARATOR.$category;
        }

        $file .= DIRECTORY_SEPARATOR.md5($key).'.data';
        if(!file_exists($file)){
            return false;
        }

        if(@filemtime($file) > time()){
            $fp = @fopen($file, 'r');//读方式打开文件
            if ($fp !== false) {
                @flock($fp, LOCK_SH);
                $cacheValue = @stream_get_contents($fp);
                @flock($fp, LOCK_UN);
                @fclose($fp);
                return $cacheValue;
            }
        }

        return false;
    }
}