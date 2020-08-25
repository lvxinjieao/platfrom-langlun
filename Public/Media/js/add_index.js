//手机上玩
$(".btn_phone").hover(function(){
	$(this).parent().prev().find(".down-code").css("bottom","0");
},function(){
	$(this).parent().prev().find(".down-code").css("bottom","-220px");
})
	
// 手游,h5切换
$(".tab-menu-con").click(function(){
	$(this).addClass("tab-menu-active").siblings().removeClass("tab-menu-active");
	var index=$(this).index();
	$(this).parent().parent().next().find(".js_tab_con").eq(index).addClass("js_tab_show").siblings().removeClass("js_tab_show");
})
// $('.jsserver').click(function(){
// 	that = $(this);
// 	inde = that.data('index');
// 	alert(inde);
// 	that.addClass('active').siblings('li').removeClass('active');
// 	that.parent('ul').next('div').children('div:eq('+inde+')').addClass('active').siblings('div').removeClass('active');
// });
// 活动专题切换 
$(".arrow_left").click(function(){
$("#slide .left-btn").click();
})
$(".arrow_right").click(function(){
$("#slide .right-btn").click();
})
$(".arrow_left1").click(function(){
$("#slide1 .left-btn").click();
})
$(".arrow_right1").click(function(){
$("#slide1 .right-btn").click();
})