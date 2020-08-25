<?php
namespace Media\Controller;
use Think\Controller;
use Common\Model\GiftbagModel;
use Common\Model\GameModel;
use Common\Model\ServerModel;
class GiftController extends BaseController {
	
	public function index($p=1,$type=null) {
 		$giftbgmodel = new GiftbagModel();
 		$user = is_login();
        empty($_REQUEST['ish5']) ? $sdk_version = ["%1%","%2%"]:$sdk_version = "%3%";
        $map['gb.giftbag_version'] = ['like',$sdk_version,'or'];
 		empty($_REQUEST['theme']) ?  "":$map['short'] = array('like',$_REQUEST['theme'].'%');
 		empty($_REQUEST['rec_sta']) ?  "":$map['giftbag_type'] = $_REQUEST['rec_sta'];
 		$gamegift = $giftbgmodel->getGiftLists(false,false,1,10000,$map,$user,'gb.create_time desc');
 		$size=16;//每页显示的记录数
		$page = intval($p);
  	    $page = $page ? $page : 1; //默认显示第一页数据
		$arraypage = $page; //默认显示第一页数据
		$count = count($gamegift);
        $pnum = ceil( $count/ $size); //总页数，ceil()函数用于求大于数字的最小整数
        //用array_slice(array,offset,length) 函数在数组中根据条件取出一段值;array(数组),offset(元素的开始位置),length(组的长度)
        $giftdata = array_slice($gamegift, ($arraypage-1)*$size, $size);
        $this->set_page($count,$size);
        //开服表
 		$servermodel = new ServerModel();
        $syserverdata = $servermodel->serverOrder(['tab_game.sdk_version'=>['neq',3]],1);
 		$h5serverdata = $servermodel->serverOrder(['tab_game.sdk_version'=>['eq',3]],1);
		$this->assign("data",$giftdata);
        $this->assign("syserverdata",$syserverdata);
		$this->assign("h5serverdata",$h5serverdata);

		//热门游戏
        $model = new GameModel();
        $mapg['recommend_status'] = array('like',"%".'2'."%");
            $mapg['sdk_version'] = array('neq',"3");
        $syhotgame = $model->getHotGame($mapg,'g.sort desc,g.id desc',10);
        $mapg['sdk_version'] = array('eq',"3");
        $h5hotgame = $model->getHotGame($mapg,'g.sort desc,g.id desc',10);
        $this->assign('syhot',$syhotgame);
        $this->assign('h5hot',$h5hotgame);
		$this->display();
	}

	public function giftdetail($gift_id) {
	    $users = $this->is_login();
	    $giftbgmodel = new GiftbagModel();
	    $user = is_login();
	    $data = $giftbgmodel->getDetail($gift_id,$user);
      if(empty($data)){
        $this->error('礼包不存在');
      }
	    $this->assign("data",$data);

	    //热门礼包
      $map['giftbag_type'] = 2;
	    $map['g.sdk_version'] = ['in','1,2'];
	    $syhotdata = $giftbgmodel->getGiftLists(false,false,1,6,$map,$users,'gb.id desc');
	    $this->assign("syhotdata",$syhotdata);
      $maph5['giftbag_type'] = 2;
      $maph5['g.sdk_version'] = ['eq','3'];
      $h5hotdata = $giftbgmodel->getGiftLists(false,false,1,6,$maph5,$users,'gb.id desc');
      $this->assign("h5hotdata",$h5hotdata);
	    
	    //相关礼包
        $tt['gb.id'] = array('neq',$data['gift_id']);
        $gdata = $giftbgmodel->getGiftLists(false,$data['game_id'],1,100,$tt,'','gb.id desc');
        $this->assign('giftlist',$gdata);
	    
        //常见问题
        $question=M('Kefuquestion','tab_')
        ->where(array('status'=>1,'istitle'=>2,'titleurl'=>'gift'))
        ->limit(6)
        ->select();
        $this->assign('question',$question);
        
	    $this->display();
	}
	
	public function getgift($gameid,$giftid) {
        $users = $this->is_login();	
        if($users) {
       		$giftbgmodel = new GiftbagModel();
       		$gamegift = $giftbgmodel->getGift($giftid,$users);
       		$this->ajaxReturn($gamegift);
        }else{
            $this->ajaxReturn(array('code'=>'0','msg'=>'您还未登录，请登录后领取'));
        }
	}
	
	
	
}