//全局变量
var wait_timer,folder_index = 0,wsUrl = 'ws://localhost:1234/',websocket,username = '';

//事件定义
$.tools = {
    //初始化
    init:function(){
        //判断是否登录
        $.getJSON('/api/islogin',{},function(data){
            if(data.code == 1){
                username = data.name;
                $.tools.show_loading();
            }else{
                $('#login_box').show();
            }
        });
    },
    //登录
    login:function(){
        var name = $.trim($('.username').val()),pwd = $.trim($('.password').val()),obj = $(this);
        if(name == '' || pwd == ''){
            $('#login_box .panel').addClass('danger');
            return false;
        }

        $.post('/api/login',{name:name,pwd:pwd},function(data){
            if(data.code == 1){
                username = name;
                $('#login_box').animate({"margin-top":"-400px"});
                $.tools.show_loading();
            }else{
                $('#login_box .panel').addClass('danger');
            }
        });
    },
    //加载显示
    show_loading:function(){
        //显示进度条
        $('#wait_box').show();
        wait_timer = setInterval(function(){
            var i = $('#wait_box span.active').length;
            if(i == 10){
                $('#wait_box span.active').removeClass('active');
                i = 0;
            }
            $('#wait_box span').eq(i).addClass('active');
        },100);

        setTimeout(function(){
            clearInterval(wait_timer);

            $('#wait_box').hide();
            $('.mask').hide();

            //开始下雨
            $.tools.start_rain();

            //开始循环目录
            var timer1 = setInterval(function(){
                $('.folder .glyphicon').eq(folder_index).css('display','block');
                folder_index++;
                if(folder_index == $('.folder .glyphicon').length){
                    folder_index = 0;
                    $('.folder .glyphicon').hide();
                }
            },500);

            //开始建立连接
            websocket = new WebSocket(wsUrl);

            websocket.onmessage = function(e){
                var data = JSON.parse(e.data);
                switch(data.type){
                    case 'say':
                        $.tools.say(data.user,data.msg);
                        break;
                    case 'online':
                        if($('.userlist #u_'+data.user).length == 0){
                            $('.userlist').append('<span id="u_'+data.user+'"><i class="glyphicon glyphicon-user"></i>'+data.user+'</span>');
                        }
                        break;
                    case 'offline':
                        $('.userlist #u_'+data.user).addClass('danger').fadeOut(2000,function(){
                            $(this).remove();
                        });
                        break;
                    case 'users':
                        $.each(data.list,function(k,v){
                            $('.userlist').append('<span id="u_'+v+'"><i class="glyphicon glyphicon-user"></i>'+v+'</span>');
                        });
                        break;
                    case 'error':
                        $('.mask').show();
                        alert(data.msg);
                        break;
                    default:
                        alert('Error');
                        break;
                }
            };

            websocket.onerror = function(e){
                alert('Error');
            };
        },3000);
    },
    //黑客雨
    start_rain:function(){
        var width,height,canvas = document.getElementById("canvas");
        canvas.width = width = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
        canvas.height = height = 400;
        var ctx = canvas.getContext('2d');
        var num = Math.ceil(width / 10);
        var y = Array(num).join(0).split('');
        var draw = function() {
                ctx.fillStyle = 'rgba(0,0,0,.05)'; //核心代码，创建黑色背景，透明度为0.05的填充色。
                ctx.fillRect(0, 0, width, height);
                ctx.fillStyle = '#0f0'; //设置了字体颜色为绿色
                ctx.font = '10px Microsoft YaHei';//设置字体大小与family
                for(var i = 0; i < num; i++) {
                    var x = (i * 10) + 10;
                    var text = String.fromCharCode(65 + Math.random() * 62);
                    var y1 = y[i];
                    ctx.fillText(text, x, y1);
                    if(y1 > Math.random() * 10 * height) {
                        y[i] = 0;
                    } else {
                        y[i] = parseInt(y[i]) + 10;
                    }
                }
            }

        ;(function(){
            setInterval(draw, 100);
        })();
    },
    //发表
    say:function(user,msg){
        $('.chat_list ul').append('<li><span>'+user+':</span>'+msg+'</li>');
        var step = $('.chat_list ul li').length - 11;
        if(step > 0){
            $('.chat_list ul').animate({"margin-top":-40*step});
        }
    },
};
//事件执行
$(document).ready(function(){
    //初始化
    $.tools.init();

    //密码输入框enter
    $('.password').on('keydown',function(e){
        if(e.keyCode == 13){
            $.tools.login();
        }
    });

    $('.chat_input').on('keydown',function(e){
        if(e.keyCode == 13){
            var msg = $.trim($(this).val());
            if(msg == ''){
                return false;
            }
            $(this).val('');

            websocket.send(msg);
        }
    });
});