$(function(){

    /*头部菜单显示*/
    $('.head').on('click','.top-menu',function(){
        $(this).toggleClass('show');
        $('.game-menu-box').slideToggle(300);
    });
    $('.head').on('click','.top-menu-hide',function(){
        $('.head .top-menu').removeClass('show');
        $('.game-menu-box').slideUp(300);
    });
    /*浮动层*/
    var rzClose = $('.float_contact .rz .close'),
        rzShow = $('.float_contact .rz a'),
        rzContent = $('.float_contact .rz .rz_pic');
        rzClose.on('click',function(){
            rzContent.removeClass('active');
        });
    var picShow = $('.float_contact ul .show'),
        picContent = picShow.find('div');
        picShow.on('mouseover',function(){
            picContent.removeClass('active');
            $(this).find('div').addClass('active');
        });
        picShow.on('mouseout',function(){
            picContent.removeClass('active');
        });
    $('.float_contact_position').on('click','.top',function(){
        $('body,html').animate({"scrollTop":0},500);
    });
    /*回到顶部*/
    $('.share-box').on('click','.li-7 a',function(){
        $('body,html').animate({"scrollTop":0},500);
    });
    /*排行内容切换*/
    $('.game-rank-box .game-list li,.hot-game-box .hot-game-list li,.hot-raiders-box .hot-raiders-list li').on('mouseenter',function(){
        $(this).addClass('on').siblings('li').removeClass("on");
    });
    /*字数限制*/
    $('.hot-raiders-list li .text p').jsNumber({
        number : 20,
    });
    $('.raiders_list .text p').jsNumber({
        number : 58,
    });
    $('.raiders-box .raiders-list .text-intr').jsNumber({
        number : 70,
    });
    $('.info-list-box .info-list-con .info-list-intr p').jsNumber({
        number : 100,
    });
    $('.data-list-box .data-list .text p').jsNumber({
        number : 100,
    });
    /*选项卡*/
    $('.tab-box').tab({trigger:'mouseover'});
   /* $('.info-list-box').tab({
        tabNum : '.info-list-tab li',
        tabCell : '.info-list-con',
    });
    $('.info-left-part').tab({
        tabNum : $('.search-box .search-select li'),
        tabCell : $('.data-list-box .data-list-con'),
    });*/
    /*轮播调用*/
    $('.slide-box.fade').slide();
    $('.slide-box.fade2').slide({trigger:'click'});
    $('.slide-box.leftLoop').slide({
        effect : 'leftLoop',
    });
    /*竖版截图*/
    $('.slide-box.vis-3').slide({
        titCell:".slide-num",
        mainCell:".slide-img",
        autoPage:"<li></li>",
        effect:"leftLoop",
        vis:3,
    });
    /*横版截图*/
    $('.slide-box.vis-2').slide({
        titCell:".slide-num",
        mainCell:".slide-img",
        autoPage:"<li></li>",
        effect:"leftLoop",
        vis:2,
    });
    /*楼层自动跳转*/
    $('.indexes-box-wrap').floor({
        floor : '.details-content .part',
        floorNav : '.indexes-box li,.indexes-list-box li',
    });
    /*视频更换动画*/
    var changeVideo = $('.rank-box-wrap .video-box .change-video');
        changeVideo.on('click',function(){
            var _this = $(this);
            $(this).addClass('active');
            setTimeout(function(){
                _this.removeClass("active");
            },1001);
        });
    /*视频播放*/
    var videoPlay = $('.rank-box-wrap .video-box .video-bg'),
        videoBox = $('.rank-box-wrap .video-box video'),
        videoLi = $('.rank-box-wrap .video-box .video-list li');
        videoPlay.on('click',function(){
            videoLi.removeClass("play").find('video').trigger('pause');
            $(this).hide().parents('li').addClass('play').find('video').trigger('play')
        });
        videoBox.on('click',function(){
            var _this = $(this).parents('li');
            if(_this.hasClass('play')){
                _this.removeClass('play').find('video').trigger('pause')
            }else{
                videoLi.removeClass('play').find('video').trigger('pause');
                _this.addClass('play').find('video').trigger('play');
            }
        });
    /*播放视频*/
    var videoPlay2 = $('.info-left-part .info-inner-box').find('video');
        videoPlay2.on('click',function(){
            var _this = $(this);
            if(_this.hasClass('play')){
                videoPlay2.removeClass('play').trigger('pause');
                _this.removeClass('play').trigger('pause');
            }else{
                videoPlay2.removeClass('play').trigger('pause');
                _this.addClass('play').trigger('play');
            }
        });
});
