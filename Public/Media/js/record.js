//兑换菜单切换
$(".record-menu li").hover(function () {
        index = $(this).index();

        $(".record-menu li").eq(index).addClass('active').siblings().removeClass('active');
        $(".record-ul li").eq(index).addClass('show').siblings().removeClass('show');
    })
//弹窗开启

$("").click(function(){
	$(".record").css("display","block");
})
//弹窗关闭
$("#report-close").click(function(){
	$(".record").css("display","none");
})
