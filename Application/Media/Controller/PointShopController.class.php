<?php
namespace Media\Controller;
use Think\Controller;
use User\Api\UserApi;
use Common\Api\GameApi;
use Org\ThinkSDK\ThinkOauth;
use Common\Model\UserModel;
use Admin\Model\PointShopModel;
use Admin\Model\PointShopRecordModel;
use Admin\Model\PointTypeModel;
use Common\Model\AdvModel;
use Admin\Model\PointRecordModel;
use User\Api\MemberApi;

class PointShopController extends BaseController {
	
	public function mall($type=0,$short=0,$p=1) {
		$shopmodel = new PointShopModel();
		$recordmodel = new PointShopRecordModel();
		$usermodel = new UserModel();
		$pointtype = new PointTypeModel();
        $Advmodel = new AdvModel();
		if($type!=0){
			$map['good_type'] =$type;
		}
		if($short){
			for ($i=0; $i <strlen($short) ; $i++) { 
				if($i){
					$shortarr.=" or short_name like '".mb_substr($short,$i,1,'utf-8')."%'";
				}else{
					$shortarr=" short_name like '".mb_substr($short,$i,1,'utf-8')."%'";
				}
				$map['_string'] = $shortarr;
			}
		}
		$map['status'] = 1;
		$map['number'] = array('gt',0);
		$row = 15;
		$data = $shopmodel->getLists($map,"create_time desc",$p,$row);
		$this->set_page($data['count'],$row);
		$record = $recordmodel->getLists('',"sr.create_time desc,sr.id desc",1,50);
		$userpoint = $usermodel->getUserOneParam(is_login(),'shop_point');
		$Advdata = $Advmodel->getAdv("shop_pc_wide",5);

        //判断是否已经完成每日游戏首冲
        if(daily_task(session('user_auth.user_id'),'share_game')){
            $is_share_game = 1;
        }else{
            $is_share_game = 0;
        }

		if(IS_AJAX){
			if(empty($data['data'])){
				$res['status'] = 0;
			}else{
				$res['status'] = 1;
				$res['data'] = $data;
			}
			$this->ajaxReturn($res);
		}else{
			$this->mall_integral();
			$this->assign('data',$data);
			$this->assign('point',$userpoint['shop_point']>0?$userpoint['shop_point']:0);
			$this->assign('record',$record);
			$this->assign('adv',$Advdata);
            $this->assign('is_share_game',$is_share_game);
			$this->display();
		}
	}
	public function user_sign_in(){
		$user = is_login();
		if($user>0){
			$model = new PointTypeModel();
			$res = $model->userGetPoint($user,strtolower('sign_in'));
			if($res==1){
				$shppoint = M('User','tab_')->field('shop_point')->find($user);
				$this->ajaxReturn(array('status'=>1,'msg'=>'签到成功','shppoint'=>$shppoint['shop_point']));
			}elseif($res==-1){
				$this->ajaxReturn(array('status'=>-1,'msg'=>'签到功能已关闭'));
			}elseif($res==-2){
				$this->ajaxReturn(array('status'=>-2,'msg'=>'已签到，不要重复签到'));
			}else{
				$this->ajaxReturn(array('status'=>0,'msg'=>'签到失败'));
			}
		}else{
			$this->ajaxReturn(array('status'=>0,'msg'=>'未登录'));
		}
	}
	public function mall_detail($id=''){
		$shopmodel = new PointShopModel();
		$recordmodel = new PointShopRecordModel();
		$usermodel = new UserModel();
		$pointtype = new PointTypeModel();
		$userpoint = $usermodel->getUserOneParam(is_login(),'shop_point');
		$this->assign('point',$userpoint['shop_point']>0?$userpoint['shop_point']:0);
		$this->mall_integral();
		empty($id)&&$this->ajaxReturn(array('status'=>0,'msg'=>'缺少商品id'));
		$model = new PointShopModel();
		$data = $model->goodsDetail($id,is_login(),[],true);
		$record = $recordmodel->getCountRecordLists(['good_id'=>['gt',0]],"num desc",1,4);
		$like = $shopmodel->getLists([],"create_time desc",$p,1000);
		if(count($like['data'])>4){
			$likes = array_rand($like['data'],4);
			foreach ($likes as $key => $value) {
				$likedata[] = $like['data'][$value];
			}
		}else{
			$likedata = $like['data'];
		}
		$question = M('Kefuquestion','tab_')
			->where(array('status'=>1,'istitle'=>2,'titleurl'=>'jifen'))
			->limit(10)
			->select();
		$this->assign('question',$question);
		$this->assign('likedata',$likedata);
		$this->assign('toprecord',$record);
		$this->assign('data',$data);

		$this->display();
	}

    /**
     * [获取用户的收货地址]
     * @author 幽灵[syt]
     */
	public function ajax_shop_address(){
	    if($this->is_login()){
            $shop_address = M('User','tab_')->where(array('id'=>session('user_auth.user_id')))->getField('shop_address');
            $this->ajaxReturn(array('status'=>1,'data'=>$shop_address));
        }
    }

	public function mall_rule($id=''){
		$data = M('PointRule','tab_')->where(array('status'=>1))->order('sort desc')->select();
		$this->assign('data',$data);
		$this->display();
	}
	public function mall_sign(){
		$week1 = array('日','一','二','三','四','五','六');
		$week[] = $week1;
		$month = date('m',time());
		$firstday = date('w', mktime(0,0,0,date('m'),1,date('Y')));
		$lastdate = date('d', mktime(0,0,0,date('m')+1,0,date('Y')));
		$ooday = date('Y-m-d', mktime(0,0,0,date('m'),-$firstday+1,date('Y')));
		$today = date('Y-m-d', time());
		$oneday = date('w',time());
		for($jj=0;$jj<$firstday;$jj++ ){
			$ss = date('d', mktime(0,0,0,date('m'),(-$firstday)+($jj+1),date('Y')));
			$week[1][$jj][] = $ss;
			$week[1][$jj][] = 'pre';
		}
		$datnum = 1;
		for($j=$firstday;$j<7;$j++){
			$week[1][$j][] = $datnum;
			$week[1][$j][] = 'cur';
			$week[1][$j][] = date('Y-m-d', mktime(0,0,0,date('m'),$datnum,date('Y')));
			$datnum++;
		}
		$hang = 1;
		for ($i=$hang; $i <7 ; $i++) { 
			$hang++;
			if($hang>6){
				break;
			}
			for($x=0; $x <7 ; $x++){
				if($datnum>$lastdate){
					break(1);
				}
				$week[$hang][$x][] = $datnum;
				$week[$hang][$x][] = 'cur';
				$week[$hang][$x][] = date('Y-m-d', mktime(0,0,0,date('m'),$datnum,date('Y')));
				$datnum++;
			}
		}
		$model = new PointRecordModel();
		$map['user_id'] = is_login();
		$map['key'] = 'sign_in';
		$map['pr.create_time'] = array('between',array(strtotime($ooday),strtotime($today)+24*60*60-1));
		$signrecord = $model->getLists($map);
		foreach ($signrecord['data'] as $key => $value) {
			foreach ($week as $k => &$v) {
				foreach ($v as $k1 => &$v1) {
					if($v1[2]==$value['ct']){
						$v1[3] = 'is_sign';
						unset($value);
					}
				}
			}
		}
		$llast = count($week[count($week)-1]);
		$hang--;
		if($llast<7){
			for($jjj=1;$jjj<8-$llast;$jjj++ ){
				$ss = date('d', mktime(0,0,0,date('m')+1,$jjj,date('Y')));
				$week[count($week)-1][][] = $ss;
			}
		}
		$this->assign('weeklist',$week[0]);
		unset($week[0]);
		$this->assign('week',$week);
		$usermodel = new UserModel();
		$userpoint = $usermodel->getUserOneParam(is_login(),'shop_point');
		$this->assign('userpoint',$userpoint['shop_point']>0?$userpoint['shop_point']:0);

		$pointtype = new PointTypeModel();
		$lgmap['pt.key'] = 'sign_in';
		if(is_login()){
			$lgjoin .= ' and pr.user_id = '.is_login();
			// $lgjoin .= ' and pr.create_time '.total(1,true);
		}else{
			$lgjoin .= ' and pr.user_id = '.is_login();
		}
		$loginpont = $pointtype->getUserLists($lgmap,$lgjoin);
		if(empty($loginpont[0]['user_id'])){
            $issignin = 0;//今日是否签到
        }elseif(!empty($loginpont[0]['user_id'])&&$loginpont[0]['ct']==date('Y-m-d',time())){
            $issignin = 1;
        }else{
            $issignin = 0;
            if($loginpont[0]['ct']!=date("Y-m-d",strtotime("-1 day"))){
            	$loginpont[0]['day'] = 0;
            }
        }
		if($loginpont[0]['day']>=7||$loginpont[0]['day']<=0||empty($loginpont[0]['day'])){
			$signday = 1;
		}else{
			$signday = $loginpont[0]['day']+1;
		}

		$tosignday = $signday+1;
		if($tosignday>=7){
			$tosignday = 1;
		}else{
			$tosignday = $tosignday;
		}
		$addpoint = $loginpont[0]['point']+($signday-1)*$loginpont[0]['time_of_day'];
		$topoint = 	$loginpont[0]['point']+($tosignday-1)*$loginpont[0]['time_of_day'];
		$issignin = empty($loginpont[0]['user_id'])?0:1;
		$shopmodel = new PointShopModel();
		$like = $shopmodel->getLists([],"create_time desc",$p,1000);
		if(count($like['data'])>5){
			$likes = array_rand($like['data'],5);
			foreach ($likes as $key => $value) {
				$likedata[] = $like['data'][$value];
			}
		}else{
			$likedata = $like['data'];
		}
		$this->assign('likedata',$likedata);
		$this->assign('issignin',$issignin);
		$this->assign('addpoint',$addpoint);//今日
		$this->assign('topoint',$topoint);//明日
		$this->assign('signday',$signday);
		$this->display();
	}

    public function mall_sign_mobile($token=''){
        if (!empty($token)){
            $m = new MemberApi();
            $m->sign_login($token);
            $this->assign('app',1);
        }
        $week1 = array('日','一','二','三','四','五','六');
        $week[] = $week1;
        $month = date('m',time());
        $firstday = date('w', mktime(0,0,0,date('m'),1,date('Y')));
        $lastdate = date('d', mktime(0,0,0,date('m')+1,0,date('Y')));
        $ooday = date('Y-m-d', mktime(0,0,0,date('m'),-$firstday+1,date('Y')));
        $today = date('Y-m-d', time());
        $oneday = date('w',time());
        for($jj=0;$jj<$firstday;$jj++ ){
            $ss = date('d', mktime(0,0,0,date('m'),(-$firstday)+($jj+1),date('Y')));
            $week[1][$jj][] = $ss;
            $week[1][$jj][] = 'pre';
        }
        $datnum = 1;
        for($j=$firstday;$j<7;$j++){
            $week[1][$j][] = $datnum;
            $week[1][$j][] = 'cur';
            $week[1][$j][] = date('Y-m-d', mktime(0,0,0,date('m'),$datnum,date('Y')));
            $datnum++;
        }
        $hang = 1;
        for ($i=$hang; $i <7 ; $i++) {
            $hang++;
            if($hang>6){
                break;
            }
            for($x=0; $x <7 ; $x++){
                if($datnum>$lastdate){
                    break(1);
                }
                $week[$hang][$x][] = $datnum;
                $week[$hang][$x][] = 'cur';
                $week[$hang][$x][] = date('Y-m-d', mktime(0,0,0,date('m'),$datnum,date('Y')));
                $datnum++;
            }
        }
        $model = new PointRecordModel();
        $map['user_id'] = is_login();
        $map['key'] = 'sign_in';
        $map['pr.create_time'] = array('between',array(strtotime($ooday),strtotime($today)+24*60*60-1));
        $signrecord = $model->getLists($map);
        foreach ($signrecord['data'] as $key => $value) {
            foreach ($week as $k => &$v) {
                foreach ($v as $k1 => &$v1) {
                    if($v1[2]==$value['ct']){
                        $v1[3] = 'is_sign';
                        unset($value);
                    }
                }
            }
        }
        $llast = count($week[count($week)-1]);
        $hang--;
        if($llast<7){
            for($jjj=1;$jjj<8-$llast;$jjj++ ){
                $ss = date('d', mktime(0,0,0,date('m')+1,$jjj,date('Y')));
                $week[count($week)-1][][] = $ss;
            }
        }
        $this->assign('weeklist',$week[0]);
        unset($week[0]);
        $this->assign('week',$week);
        $usermodel = new UserModel();
        $userpoint = $usermodel->getUserOneParam(is_login(),'shop_point');
        $this->assign('userpoint',$userpoint['shop_point']>0?$userpoint['shop_point']:0);

        $pointtype = new PointTypeModel();
        $lgmap['pt.key'] = 'sign_in';
        if(is_login()){
            $lgjoin .= ' and pr.user_id = '.is_login();
            // $lgjoin .= ' and pr.create_time '.total(1,true);
        }else{
            $lgjoin .= ' and pr.user_id = '.is_login();
        }
        $loginpont = $pointtype->getUserLists($lgmap,$lgjoin);
        if(empty($loginpont[0]['user_id'])){
            $issignin = 0;//今日是否签到
        }elseif(!empty($loginpont[0]['user_id'])&&$loginpont[0]['ct']==date('Y-m-d',time())){
            $issignin = 1;
        }else{
            $issignin = 0;
            if($loginpont[0]['ct']!=date("Y-m-d",strtotime("-1 day"))){
                $loginpont[0]['day'] = 0;
            }
        }
        if($loginpont[0]['day']>=7||$loginpont[0]['day']<=0||empty($loginpont[0]['day'])){
            $signday = 1;
        }else{
            $signday = $loginpont[0]['day']+1;
        }

        $tosignday = $signday+1;
        if($tosignday>=7){
            $tosignday = 1;
        }else{
            $tosignday = $tosignday;
        }
        $addpoint = $loginpont[0]['point']+($signday-1)*$loginpont[0]['time_of_day'];
        $topoint = 	$loginpont[0]['point']+($tosignday-1)*$loginpont[0]['time_of_day'];
        $issignin = empty($loginpont[0]['user_id'])?0:1;
        $shopmodel = new PointShopModel();
        $like = $shopmodel->getLists([],"create_time desc",$p,1000);
        if(count($like['data'])>5){
            $likes = array_rand($like['data'],5);
            foreach ($likes as $key => $value) {
                $likedata[] = $like['data'][$value];
            }
        }else{
            $likedata = $like['data'];
        }
        $this->assign('likedata',$likedata);
        $this->assign('issignin',$issignin);
        $this->assign('addpoint',$addpoint);//今日
        $this->assign('topoint',$topoint);//明日
        $this->assign('signday',$signday);
        $this->display();
    }
	private function mall_integral(){
		$pointtype = new PointTypeModel();
		$usermodel = new UserModel();
		$lgmap['pt.key'] = 'sign_in';
		if(is_login()){
			$lgjoin .= ' and pr.user_id = '.is_login();
			// $lgjoin .= ' and pr.create_time '.total(1,true);
		}else{
			$lgjoin .= ' and pr.user_id = '.is_login();
		}
		$loginpont = $pointtype->getUserLists($lgmap,$lgjoin);
		if(empty($loginpont[0]['user_id'])){
            $issignin = 0;//今日是否签到
        }elseif(!empty($loginpont[0]['user_id'])&&$loginpont[0]['ct']==date('Y-m-d',time())){
            $issignin = 1;
        }else{
            $issignin = 0;
            if($loginpont[0]['ct']!=date("Y-m-d",strtotime("-1 day"))){
            	$loginpont[0]['day'] = 0;
            }
        }
		if($loginpont[0]['day']>=7||$loginpont[0]['day']<=0||empty($loginpont[0]['day'])){
			$signday = 1;
			$tosignday = $signday+1;
		}else{
			$signday = $loginpont[0]['day']+1;
			$tosignday = $signday;
		}
		if($tosignday>=7){
			$tosignday = 1;
		}else{
			$tosignday = $tosignday;
		}
		$addpoint = $loginpont[0]['point']+($signday-1)*$loginpont[0]['time_of_day'];

        if($issignin == 1){
            $todaypoint = $loginpont[0]['point']+(($signday-2)>=0?($signday-2):0)*$loginpont[0]['time_of_day'];
        }else{
            $todaypoint = $loginpont[0]['point']+(($signday-1)>=0?($signday-1):0)*$loginpont[0]['time_of_day'];
        }

		$topoint = 	$loginpont[0]['point']+($tosignday-1)*$loginpont[0]['time_of_day'];
		// $issignin = empty($loginpont[0]['user_id'])?0:1;
		$list = $pointtype->where(['status'=>1])->order('id asc')->select();
		$newlist = array_reduce($list,function(&$newlist,$v){
		    $newlist[$v['key']] = $v;
		    return $newlist;
		});
		$userpoint = $usermodel->getUserOneParam(is_login(),'shop_point');
		$bindphone = $usermodel->getUserOneParam(is_login(),'phone');
		$firstspend = M('Spend','tab_')->where(['pay_time'=>total(1,false),'user_id'=>is_login(),'pay_status'=>1,'game_id'=>['gt',0]])->find();
		$this->assign('userpoint',$userpoint['shop_point']>0?$userpoint['shop_point']:0);
		$this->assign('bindphone',$bindphone['phone']);
		$this->assign('issignin',$issignin);
		$this->assign('addpoint',$addpoint);
		$this->assign('topoint',$topoint);
		$this->assign('list',$newlist);
		$this->assign('firstspend',$firstspend);
        $this->assign('todaypoint',$todaypoint);//今天签到获得的点数
	}

	//兑换记录
	public function mall_record(){
		$user = is_login();
		if($user>0){
			$model = new PointRecordModel();
			$map['user_id'] = $user;
			$data = $model->getRecordLists2($map);
		}else{
			$data='';
		}
//		var_dump($data);exit;
		$this->assign('data',$data);
		$this->display();
	}


	public function shopBuy(){
		$user = is_login();
		if($user<=0){
			$this->ajaxReturn(array('status'=>-1,'msg'=>'您还未登录'));
		}
		$good_id = $_POST['good_id'];
		if(empty($good_id)){
			$this->ajaxReturn(array('status'=>-2,'msg'=>'缺少礼包id'));
		}
		$model = new PointShopModel();
		
		$res = $model->shopBuy($user,$good_id,$_POST['num']);
		switch ($res) {
			
			case 0:
				$this->ajaxReturn(array('status'=>0,'msg'=>'购买失败'));
				break;

			case -3:
				$this->ajaxReturn(array('status'=>-3,'msg'=>'积分不足'));
				break;

			case -4:
				$this->ajaxReturn(array('status'=>-4,'msg'=>'库存不足'));
				break;
			
			case 1:
				$this->ajaxReturn(array('status'=>1,'msg'=>'购买成功'));
				break;

			default:
			    if($res['status']==1){
			    	$this->ajaxReturn(array('status'=>1,'msg'=>'xuni','data'=>$res));
			    }else{
					$this->ajaxReturn(array('status'=>0,'msg'=>'购买失败'));
			    }
				break;
		}
	}
	
	/**
	* 登录
	*/
	public function login($username='',$password='') {
        
	}


}