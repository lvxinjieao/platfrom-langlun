// JavaScript Document
// 左侧切换
$(function(){
	$('.pay_l ul li').eq(0).addClass('li_h').siblings().removeClass('li_h');
    $('#apitype').val($('.pay_l ul li').eq(0).data('type'));
    $('#choose_type').text($('.pay_l ul li').eq(0).find('.pay_name').text());
    
	$('.pay_l ul li').click(function(){
        $('.pay_l ul li').attr('class','');
        $('#apitype').val($(this).data('type'));
        $('#choose_type').text($(this).find('.pay_name').text());
        $(this).addClass('li_h');
	});
});

function get_game_coin(){
	var money = parseInt($('#money').val());

	if(!money){
		money = $('.money_checked').find('input').val();
	}else{
		$('.pay_money_box input[name="money"]:checked').attr('checked', false).parent().removeClass('money_checked');
	}
	
	$('#game_coin').html(money);
}

$(function(){
	//充值金额切换
	$(".pay_money_box span:not('.last_money')").click(function(){
			$(this).addClass("money_checked").siblings("span").removeClass("money_checked");
			$(this).find('input').attr('checked','1');
			$(this).parent().find('.money2').val('');
			get_game_coin();
	});
	get_game_coin();

	$(".ptb").click(function(){
		$('#ptbtips').toggle();		
	});
	
});
