<?php
class Autoloader{
    /**
     * 根据命名空间加载文件
     * @param string $name
     * @return boolean
     */
    public static function loadByNamespace($name){
        global $app_path;
        // 相对路径
        $class_path = str_replace('\\', DIRECTORY_SEPARATOR ,$name);
        $class_file = $app_path.DIRECTORY_SEPARATOR.$class_path.'.php';

        // 找到文件
        if(is_file($class_file)) {
            // 加载
            require_once($class_file);
            if(class_exists($name, false)) {
                return true;
            }
        }

        return false;
    }
}
// 设置类自动加载回调函数
spl_autoload_register('Autoloader::loadByNamespace');