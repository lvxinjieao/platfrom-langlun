<?php
namespace Mobile\Controller;
use Think\Controller;
use Common\Model\GiftbagModel;
use User\Api\MemberApi;
class GiftController extends BaseController {
	
	public function index($type='sy') {
		$users = $this->is_login();
		if($type=='sy'){
		    $map['g.sdk_version'] = get_devices_type();
        }else{
            $map['g.sdk_version'] = 3;
        }
   		$giftbgmodel = new GiftbagModel();
   		$user = is_login();
   		if(is_cache()&&S('gift_data'.$type)){
   			$gamegift=S('gift_data'.$type);
   		}else{
   			$gamegift = $giftbgmodel->getGameGiftLists(false,false,$user,$map);
   			if(is_cache()){
   				S('gift_data'.$type,$gamegift);
   			}
   		}
   		//$allgamegift = $giftbgmodel->getGameGiftLists(false,$user);
   		if(!IS_AJAX){
			$this->assign("data",$gamegift);
			//$this->assign("alldata",$allgamegift);
			$this->display();
   		}else{
   			$this->ajaxReturn($gamegift);
   		}
	}
	public function giftdetail($gift_id) {
		$users = $this->is_login();
   		$giftbgmodel = new GiftbagModel();
   		$user = is_login();
		$data = $giftbgmodel->getDetail($gift_id,$user);
		if(!$data){
			$this->ajaxReturn(array('code'=>0,'msg'=>'礼包不存在'));
		}
		$this->assign("data",$data);
		$this->display();
	}
	public function getgift($gameid,$giftid) {

        C(api('Config/lists')); //添加配置

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