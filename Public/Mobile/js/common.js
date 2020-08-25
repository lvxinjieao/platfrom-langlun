/**
 * 基本行为
 * @author 鹿文学
 */
var ht = $('html');
if (ht.width()<1242) {
	//orientation(ht);
}
/**
 * 横竖撇检测
 * @author 鹿文学
 */
function orientation(ht) {
	var supportOrientation = (typeof window.orientation == 'number' && typeof window.onorientationchange === 'object');
	var init = function() {
		var htmlNode = document.body.parentNode,orientation;
		var fontSize = ht.width()*625/1242;
		var updateOrientation = function(){
			if (supportOrientation) {
				orientation = window.orientation;
				switch(orientation) {
					case 90:
					case -90:
					fontSize = ht.width()*625/1242/1.77777;break;
				}
			} else {
				if(window.innerWidth>window.innerHeight) {
					fontSize = ht.width()*625/1242/1.77777;
				}
			}
			ht.css({'font-size':(fontSize*14)/100+'px'});
		}
		if (supportOrientation) {
			window.addEventListener('orientationchange',updateOrientation,false);
		} else{
			window.addEventListener('resize',updateOrientation,false);		
		}
		updateOrientation();
	}
	window.addEventListener('DOMContentLoaded',init,false);
}
$(function(){
  $(window).resize(function(){
    
    /* if (ht.width()<1242)
      orientation(ht); */
    
  });
	
	//全选的实现
	
  $(".check-all").click(function(){
    $(this).toggleClass('on');
    this.checked?($('.ids').addClass('on')):($('.ids').removeClass('on'));
    $('.ids').prop("checked", this.checked);
  });
  $(".ids").click(function(){
    var option = $(".ids");$(this).toggleClass('on');
    option.each(function(i){
      if(!this.checked){
        $(".check-all").prop("checked", false).removeClass('on');
        return false;
      }else{
        $(".check-all").prop("checked", true).addClass('on');
      }
    });
  });
  
  // 时间
  (function() {
      function time() {
        var d = new Date();
        $('.jstime span').text(d.getFullYear()+'年'+(parseInt(d.getMonth())+1)+'月'+d.getDate()+'日');
      }
      time();
      setInterval(function(){
        time();
      },86400000);
  })();
  
  

    
});


function clock(ele,time) {
  var e = $(ele);var t = time?time:60;
  e.addClass('disabled').html('已发送<span>'+t+'s</span>');
  var a = setInterval(function() {    
    t--;
    e.html('已发送<span>'+t+'s</span>');
    t>0 || (clearInterval(a),e.removeClass('disabled').html('重新获取'));
  },1000);
}

/* 
 * 设置表单值
 * @author lwx
 */
function setValue(name,values) {
    var first=name.substr(0,1),input,val;
    if (values === '') return ;
    if ('#'==first || '.' == first) {
        input = $(name);
    } else {
        input = $('[name="'+name+'"]');
    }
    
    if (input.eq(0).is(':radio')) {
        input.filter('[value="'+values+'"]').each(function() {this.checked=true;});
    } else if (input.eq(0).is(':checkbox')) {
        if (!$.isArray(values)) {
            val = new Array();
            val[0] = values;
        } else {
            val = values;
        } 
        
        for (i=0,len=val.length;i<len;i++) {
            input.filter('[value="'+val[i]+'"]').each(function() {this.checked=true;});
        }
    } else {
        input.val(values);
    }
}

/* 
 * 检测手机PC
 * @author lwx
 */
var browser = {
  versions:function(){
    var u = navigator.userAgent,app=navigator.appVersion;
    return {
      trident:u.indexOf('Trident')>-1,/*IE*/
      presto:u.indexOf('Presto')>-1,/*opera*/
      webkit:u.indexOf('AppleWebKit')>-1,/*apple google*/
      gecko:u.indexOf('Gecko')>-1 && u.indexOf('KHTML')==-1,/*firefox*/
      mobile:!!u.match(/AppleWebKit.*Mobile.*/)||!u.match(/AppleWebKit/),/*移动*/
      ios:!!u.match(/\(i[^;]+;( U;)?CPU.+Mac OS X/),/*ios*/
      android:u.indexOf('Android')>-1 || u.indexOf('Linux')>-1,/*android uc*/
      iPhone:u.indexOf('iPhone')>-1|| u.indexOf('Mac')>-1,/*iPhone QQHD*/
      iPad:u.indexOf('iPad')>-1,/*iPad*/
      webApp:u.indexOf('Safari')==-1,
			weixin: u.indexOf('MicroMessenger') > -1, //是否微信
      qq:u.indexOf('QQ') > -1,
    };
  }(),
  language:(navigator.browserLanguage||navigator.language).toLowerCase(),
}