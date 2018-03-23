# 开发环境
win7、cygwin64、php7.0.18、swoole2.0.9
# 启动
进入chat目录<br>
<br>
php web.php<br>
<br>
php server.php<br>
<br>
浏览器访问 http://localhost:8080 就可以<br>
<br>
\lib\WebResponse.php文件中可以设置登录账户密码
# 描述
一个账户重复登录，仅以最新登录为准，之前登录的连接会被删除。