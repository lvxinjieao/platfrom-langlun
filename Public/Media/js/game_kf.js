//样式
$('.rank-kf-tab li').hover(function() {
	$(this).find('.kf-tab-box').addClass('kf-tab-hover');
}, function() {
	$(this).find('.kf-tab-box').removeClass('kf-tab-hover')
});

//开服
// var ul = $('#rank-kf-tab ul');
// function _switch(dir, callback){
// var div = $('div #rank-kf-tab');

// var px = parseInt(div.css('margin-left').replace(/[^0-9-]/g, ''));
// var count = div.find('ul').length;
// var width = $('div.rank-kf-wrap').width();
// var max_px = width * count - width;
// var dis;

// if(dir == 'left'){
// dis = px == 0 ? 0 : px + width;
// }else{
// dis = px <= -max_px ? -max_px : px - width;
// }

// var current = Math.abs(Math.min(dis, 0) / width) +1;
// current = Math.min(count, current);
// div.animate({'margin-left': dis}, 200, callback);

// $('#current_game_page').html(current +'/'+ count);

// }
// _switch('left');
// var tzul = $('#rank-kf-tab1 ul');
// function _switch1(dir, callback){

// var div = $('div #rank-kf-tab1');
// console.log(tzul.length)
// var px = parseInt(div.css('margin-left').replace(/[^0-9-]/g, ''));
// var count = tzul.length;

// var width = $('div.rank-kf-wrap').width();
// var max_px = width * count - width;
// var dis;

// if(dir == 'left'){
// dis = px == 0 ? 0 : px + width;
// }else{
// dis = px <= -max_px ? -max_px : px - width;
// }

// var current = Math.abs(Math.min(dis, 0) / width) +1;
// current = Math.min(count, current);
// div.animate({'margin-left': dis}, 200, callback);

// $('#current_game_page1').html(current +'/'+ count);

// }
// _switch1('left');
var ul = $('.rank-kf-tab ul');

function _switch(dir, callback) {
	var div = $('#rank-kf-tab');
	// var div = $(this).parent().next().find(".rank-kf-tab");
	if(div.length > 0) {
		var px = parseInt(div.css('margin-left').replace(/[^0-9-]/g, ''));
		var count = div.find('ul').length;
		var width = $('.rank-kf-wrap1').width();
		var max_px = width * count - width;
		var dis;

		if(dir == 'left') {
			dis = px == 0 ? 0 : px + width;
		} else {
			dis = px <= -max_px ? -max_px : px - width;
		}
		var current = Math.abs(Math.min(dis, 0) / width) + 1;
		current = Math.min(count, current);
		div.animate({
			'margin-left': dis
		}, 200, callback);
		$('.current_game_page').html(current + '/' + count);

	}

}

function _switch1(dir, callback) {
	var div = $('#rank-kf-tab1');
	console.log(div, '内容1')
	// var div = $(this).parent().next().find(".rank-kf-tab");
	if(div.length > 0) {
		var px = parseInt(div.css('margin-left').replace(/[^0-9-]/g, ''));
		var count = div.find('ul').length;
		var width = $('.rank-kf-wrap1').width();
		var max_px = width * count - width;
		var dis;

		if(dir == 'left') {
			dis = px == 0 ? 0 : px + width;
		} else {
			dis = px <= -max_px ? -max_px : px - width;
		}

		var current = Math.abs(Math.min(dis, 0) / width) + 1;
		current = Math.min(count, current);
		div.animate({
			'margin-left': dis
		}, 200, callback);
		console.log(current, count, '当前页，总页数1');
		$('.current_game_page1').html(current + '/' + count);
	}

}

function _switch2(dir, callback) {
	var div = $('#rank-kf-tab2');
	console.log(div, '内容2')
	if(div.length > 0) {
		var px = parseInt(div.css('margin-left').replace(/[^0-9-]/g, ''));
		var count = div.find('ul').length;
		var width = $('.rank-kf-wrap2').width();
		var max_px = width * count - width;
		var dis;

		if(dir == 'left') {
			dis = px == 0 ? 0 : px + width;
		} else {
			dis = px <= -max_px ? -max_px : px - width;
		}

		var current = Math.abs(Math.min(dis, 0) / width) + 1;
		current = Math.min(count, current);
		div.animate({
			'margin-left': dis
		}, 200, callback);
		console.log(current, count, '当前页，总页数2');
		$('.current_game_page2').html(current + '/' + count);
	}
	// var div = $(this).parent().next().find(".rank-kf-tab");

}

function _switch3(dir, callback) {
	var div = $('#rank-kf-tab3');
	console.log(div, '内容3')
	// var div = $(this).parent().next().find(".rank-kf-tab");
	if(div.length > 0) {
		var px = parseInt(div.css('margin-left').replace(/[^0-9-]/g, ''));
		var count = div.find('ul').length;
		var width = $('.rank-kf-wrap2').width();
		var max_px = width * count - width;
		var dis;

		if(dir == 'left') {
			dis = px == 0 ? 0 : px + width;
		} else {
			dis = px <= -max_px ? -max_px : px - width;
		}

		var current = Math.abs(Math.min(dis, 0) / width) + 1;
		current = Math.min(count, current);
		div.animate({
			'margin-left': dis
		}, 200, callback);
		console.log(current, count, '当前页，总页数3');
		$('.current_game_page3').html(current + '/' + count);
	}

}
_switch('left');
_switch1('left');
_switch2('left');
_switch3('left');