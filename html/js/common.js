$(document).ready(function(){
    var folder_index = 0;

    $('.password').on('keydown',function(e){
        if(e.keyCode == 13){
            $(this).parents('.modalbox').animate({"margin-top":"-400px"});

            var timer1 = setInterval(function(){
                var i = $('.loading span.active').length;
                if(i == 10){
                    $('.loading span.active').removeClass('active');
                    i = 0;
                }
                $('.loading span').eq(i).addClass('active');
            },500);

            $.post('/api/login',{},function(data){

            });

            $('.mask').hide();
        }
    });

    start_rain();

    var timer = setInterval(show_folder,500);

    $('.chat_input').on('keydown',function(e){
        if(e.keyCode == 13){
            var val = $.trim($(this).val());
            if(val == ''){
                return false;
            }
            say('I',val);
            $(this).val('');
        }
    });

    function start_rain(){
        var width,height,
            canvas = document.getElementById("canvas");
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
    }

    function show_folder(){
        $('.folder .glyphicon').eq(folder_index).css('display','block');
        folder_index++;
        if(folder_index == $('.folder .glyphicon').length){
            folder_index = 0;
            $('.folder .glyphicon').hide();
        }
    }

    function say(user,msg){
        $('.chat_list ul').append('<li><span>'+user+':</span>'+msg+'</li>');
        var step = $('.chat_list ul li').length - 11;
        if(step > 0){
            $('.chat_list ul').animate({"margin-top":-40*step});
        }
    }
});