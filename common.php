<?php
//设置时区
date_default_timezone_set('Asia/Shanghai');

ini_set('display_errors','On');
error_reporting(E_ALL);

//定义全局变量
$app_path = __DIR__;

//加载自动加载器
require_once './lib/Autoloader.php';