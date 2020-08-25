<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace Common\Api;
use Org\UcenterSDK\Ucservice;
class GameApi {
	public function game_login($uid,$game_id,$pid){
		$game_id || die(json_encode(array('code'=>0,'data'=>'缺少游戏参数')));
		$game_info = M('Game','tab_')->field('id,game_name,type,game_appid,developers')->where(array('id'=>$game_id))->find();
		//如果用户登陆过了  不要调起sdk
		if($uid){
			$user=$this->add_user_login_record($uid,$game_id);
			//记录第一次玩的游戏
			if($user['fgame_id']==0){
				$this->set_user_promote($uid,$game_id);
	   		}
			$this->add_user_play($user,$game_info,$pid);
			$this->add_user_behavior($uid,$game_id);
		}else{
			$uid = '';//不赋值  http_build_query 不识别user_id
		}
		//gameset
	 	$game = M('GameSet',"tab_");
		$map['game_id'] = $game_id;
		$game_data = $game->where($map)->find();
		//登录透传信息，支付时回传
		$shost=$_SERVER['HTTP_HOST'];
		$channelExt['for_third']=session('for_third')==''?'':session('for_third');
		$channelExt['ext']=$shost;
		$channelExt['sdkloginmodel']=strtolower(MODULE_NAME).".php";
		$channelExt['time']=time();
		$channelExt['pid']=$pid;
		$ext=simple_encode(json_encode($channelExt));//登录透传信息，cp跳转页面后需要解密处理
		if(!$game_info['type']){
			return $game_data['third_party_url'];
		}
		if($game_info['developers']=='egret'||$game_info['developers']=='白鹭'){
			$data['userId']    	= $uid;//渠道用户id
		    $data['userName']   = $user['nickname'];//用户昵称
		    $data['userImg']    = '';//用户头像没有此参数为空
		    $data['userSex']    = 0;//用户性别没有此参数为空
		    $data['channelExt'] = $ext;//透传信息 支付时传回来
		    $data['time']   	= time();
		    $data['sign']       = MD5('appId='.$game_data['agent_id'].'time='.$data['time'].'userId='.$uid.$game_data['game_key']);
		}elseif($game_info['developers']=='h5open'){
			$data['channelExt'] 	= $ext;//透传信息 支付时传回来
	        $data['channelId'] 		= $game_data['agent_id']?$game_data['agent_id']:$game_id;//同步平台账号
	        $data['gameAppid'] 		= $game_data['game_pay_appid'];
	        $data['timeStamp'] 		= time();
	        $data['nonce'] 			= sp_random_string(6);
	        $data['userId'] 		= $uid;
	        $data['userAccount'] 	= $user['nickname'];
	        $data['userHeadicon'] 	= $user['head_icon'];
	        $data['userSex'] 		= 1;
	        $data['userPhone'] 		= $user['phone'];
	        $data['sign'] 			= signsortData($data,$game_data['game_key'].$game_data['access_key']);
	        $_loginUrl =trim($game_data['login_notify_url'])."?".sortData($data);
	    	return $_loginUrl;
		}elseif($game_info['developers']=='群黑'){
			  $data['uname']          = $user['id'];
			  $data['unid']           = 'pangyuyou';
			  $data['gid']            = $game_data['game_key'];
			  $data['time']           = time();
			  $data['cb']             ='http://'.$_SERVER['HTTP_HOST'].'/'.strtolower(MODULE_NAME).'.php/Game/open_game/game_id/'.$game_id;
			  $data['sign']           = MD5($data['unid'].$user['id']. $data['gid'].time()."c17eddb7eacd2237db5f3f451a051b2f");
			  $_loginUrl =trim($game_data['login_notify_url'])."?".http_build_query($data);
			  return $_loginUrl;
		}
		else{//新接口
			$map['id']=$game_id;
			if ($game_info['game_appid'] == $game_data['game_pay_appid']) {
				$data['user_id']    		= $uid;//uid同步平台uid唯一值,
			    $data['game_appid'] 		= $game_data['game_pay_appid'];//同步平台账号
			    $data['email']      		= $game_data['agent_id']?:$game_id;//同步平台账号
			    $data['new_time']   		= time();
			    $data['sdklogindomain'] 	= $shost;
			    $data['sdkloginmodel']  	= strtolower(MODULE_NAME).".php";
			    $data['channelExt']    		= $ext;//透传信息 支付时传回来
			    $data['loginplatform2cp']   = get_client_ip();//白鹭不需要此参数。用于CP要求平台特别传输其他参数，默认是访问ip，可以改变 根据情况自由发挥，若需要传多个参数，可仿照登陆透传信息 对称加密加密传输
			    ksort($data);//字典排序
			    $data['sign']       		= MD5(http_build_query($data).$game_data['game_key']);
				$data['icon']				=$user['head_icon'];
				$data['nickname']			=$user['nickname'];
			}else{
				$this->error_record("游戏appid错误"); return false;
			}
		}
		if(strpos($game_data['login_notify_url'], "?")){
			$_loginUrl =trim($game_data['login_notify_url'])."&".http_build_query($data);
		}else{
			$_loginUrl =trim($game_data['login_notify_url'])."?".http_build_query($data);
		}
	    return $_loginUrl;
	}
	public function game_pay_notify($param=null,$code=1){
		header("Content-type: text/html; charset=utf-8");
        $pay_map['pay_order_number'] = $param['pay_order_number'];
        $param = M("Spend","tab_")->where($pay_map)->find();
        if(empty($param)){
			$this->error_record("未找到相关数据");
			echo array('status'=>0,'msg'=>'未找到相关数据');exit;
		}else if($param['pay_status']==0){
			$this->error_record("订单未支付");
            echo array('status'=>0,'msg'=>'订单未支付');exit;
		}
		$game = M('GameSet',"tab_");
		$map['game_id'] = $param['game_id'];
		$game_data = $game->where($map)->find();
		$game_info = M('Game','tab_')->where(array('id'=>$map['game_id']))->find();
		
		if(empty($game_data)){ $this->error_record("未找到指定游戏数据"); return false;}
		if(empty($game_data['pay_notify_url'])){$this->error_record("未设置游戏支付通知地址"); return false;}
		//手游支付回调
		if($game_info['sdk_version'] != 3){
			return $this->sy_game_pay_notify($param,$code);exit;
		}
		if($game_info['developers']=='egret'||$game_info['developers']=='白鹭'){
			$data = array(
				"userId"       => $param['user_id'],
				"ext"     => $param['extend'],
				"orderId" => $param['pay_order_number'],
				"money"       => $param['pay_amount'],
				"time"   => time(),
			);
			ksort($data);
			$str = "";
			foreach($data as $key=>$value){
				$str .= $key ."=". $value;
			}
			$data["sign"] = md5($str.$game_data['game_key']);
		}elseif($game_info['developers']=='h5open'){
			$data = array(
				"channelId"     => $game_data['agent_id'],
				"userId"     	=> $param['user_id'],
				"money" 		=> $param['pay_amount']*100,
				"ext"       	=> $param['extend'],
				"timeStamp"   	=> time(),
				"nonceStr"   	=> sp_random_string(16),
			);
			$data['sign'] = signsortData($data,$game_data['game_key'].$game_data['access_key']);
		}else{
			$data = array(
				"channel_source"       => "vlcms",
				"trade_no"     => $param['extend'],
				"out_trade_no" => $param['pay_order_number'],
				"amount"       => $param['pay_amount'] * 100,
				"game_appid"   => $game_data['game_pay_appid'],
				"payplatform2cp"   => get_client_ip(),//白鹭不需要此参数。用于CP要求平台特别传输其他参数，默认是访问ip，可以改变 根据情况自由发挥，若需要传多个参数，可仿照登陆透传信息 对称加密加密传输
				// "key"		   => $game_data['game_key'],	
			);
			ksort($data);
			$data["sign"] = MD5(http_build_query($data).$game_data['game_key']);
		}
		$_payUrl = $game_data['pay_notify_url']."?".http_build_query($data);
		$result = $this->post($_payUrl);
		if ($config['value'] == 1 ) {
        	if ($param['uc'] ==1 ) {
        		$result=json_decode($result,true);
        		if($result['status']=='success'||$result['msg']=='success'||$result['code']=='1009'||$result['code']==200){
					$uc = new Ucservice();
					$res1 = $uc->uc_spend_change($param['pay_order_number'],1);
				}
        	}
        }
		return $result;
	}
	//登陆记录
	public function add_user_login_record($uid,$game_id)
	{
		$user=M('User','tab_')->field('id,account,nickname,fgame_id,promote_id,promote_account,parent_id,parent_name,phone,head_icon,openid')->find($uid);
		// 10秒内不记录多次登陆 iframe一次刷新会出现多次登录记录
		$ulog=M("UserLoginRecord","tab_")->field('max(login_time) as logintime')->where(array('game_id'=>$game_id,'user_id'=>$uid))->find();
		if($ulog['logintime']+10>time()){
			return  $user;
		}
		$game=M('Game','tab_')->where(['id'=>$game_id])->field('id,game_name,relation_game_name,relation_game_id')->find();
		$data['game_id']=$game_id;
		$data['game_name']=$game['game_name'];
		$data['user_id']=$uid;
		$data['user_account']=$user['account'];
		$data['user_nickname']=$user['nickname'];
		$data['promote_id']=$user['promote_id'];
		$data['login_time']=time();
		$data['login_ip']=get_client_ip();
		M("UserLoginRecord","tab_")->add($data);
		return $user;
	}
	function add_user_behavior($user_id,$game_id){
		$map['user_id'] = $user_id;
        $map['game_id'] = $game_id;
        $map['status'] = ['in','-2,2'];
        $map['update_time'] = total(1,false);
        $data = M('user_behavior as ub','tab_')
                ->where($map)
                ->find();
        if($data){
         	$save['status'] = 2;
            $save['id'] = $data['id'];
            $save['update_time'] = time();
            $res = M('user_behavior','tab_')->save($save);
            if($res){
                $result['code'] = 1;
                $result['data'] = $save['status'];
            }else{
                $result['code'] = 0;
            }
        }else{
            $save['user_id'] = $user_id;
            $save['game_id'] = $game_id;
            $save['sdk_version'] = '3';
            $save['status'] = 2;
            $save['update_time'] = time();
            $save['create_time'] = $save['update_time'];
            $res = M('user_behavior','tab_')->add($save);
            if($res){
                $result['code'] = 1;
                $result['data'] = $save['status'];
            }else{
                $result['code'] = 0;
            }
        }
	}
	//玩家记录
	 public function add_user_play($user=[],$game=[],$pid=0){
	 	if(empty($user)||empty($game)){
	 		return false;
	 	}
        //实例化 play
        $user_play = M("play","tab_user_");
        $play_map['user_id'] = $user['id'];
        $play_map['game_id'] = $game['id'];
        $pid=$user['promote_id'];//如果1推广员的用户走的2推广员的链接，这条记录还应该是1推广员的
        $play = $user_play->where($play_map)->find();
        if(empty($play)){
            $play_data["user_id"] = $user['id'];
            $play_data["user_account"] =$user['account'];
            $play_data["user_nickname"] =$user['nickname'];
            $play_data["game_appid"] = $game['game_appid'];
            $play_data["game_id"] = $game['id'];
            $play_data["game_name"] = $game['game_name'];
            $play_data["area_id"] = 0;
            $play_data["area_name"] = "";
            $play_data["role_id"] = 0;
            $play_data["role_name"] = "";
            $play_data["role_level"] = 0;
            $play_data["balance"] = 0;
            $play_data["play_time"] = time();
            $play_data["create_time"] = time();
            $play_data["show_foot"] = 1;
            $play_data["promote_id"]=$user['promote_id'];//推广id
            $play_data["promote_account"] = $user['promote_account'];//推广姓名
            $user_play->add($play_data);
            M('game','tab_')->where(array('id'=>$game['id']))->setInc('play_count',1);
        }else{
        	$play_data["play_time"] = time();
            $play_data["show_foot"] = 1;
            $user_play->where($play_map)->save($play_data);
        }

    }

    public function add_user_role($data){
    	$user_play = M("user_play_info","tab_");
        $play_map['user_id']        	= $data['user_id'];
        $play_map['game_id']    		= $data['game_id'];
        $play_map['server_id']          = $data['server_id'];
        $play = $user_play->where($play_map)->find();
        if(empty($play)){
            $data['create_time'] = $data['play_time'];
            $user_play->add($data);
            $data['newadd'] = 1;
        }else{
            $data['id'] = $play['id'];
            $user_play->save($data);
        }
        return $data;
    }
   
    //判断用户有没有玩游戏 如没有把当前游戏写入
    public  function set_user_promote($uid,$game_id){
    	$map['id']=$uid;
    	M("user","tab_")->where($map)->setField(array('fgame_id'=>$game_id,'fgame_name'=>get_game_name($game_id)));	
    }
    //用于通知cp登陆的用户信息
    public function sdk2cplogin($uid,$param,$type='id'){
    	if(!$uid){
    		return false;
    	}
    	$map[$type] = $param;
    	$game_data=M('game','tab_')->field('id,game_appid')->where($map)->find();
    	if(!$game_data){
    		return false;
    	}
    	$uuu=get_user_entity($uid);
    	$game_set_data = M('GameSet','tab_')->where(array('id'=>$game_data['id']))->find();
    	$data['user_id']    = $uid;//uid同步平台uid唯一值,
	    $data['game_appid'] = $game_set_data['game_pay_appid'];//同步平台账号
	    $data['email']      = $game_set_data['agent_id'];//同步平台账号
	    $data['new_time']   = time();
		ksort($data);//字典排序
		$data['sign']       = MD5(http_build_query($data).$game_set_data['game_key']);
		$data['icon']		=$uuu['head_icon'];
		$data['nickname']		=$uuu['nickname'];
		$data['sdkloginmodel']		=strtolower(MODULE_NAME).".php";
		return json_encode($data);
    }
	public function error_record($msg=""){
		\Think\Log::record($msg);
	}

	/**
	*post提交数据
    */
    protected function post($url){
    	$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
    }


    //手游通知
    public function sy_game_pay_notify($param=null,$code=1,$is_uc=0){
		$pay_map['pay_status'] = 1;
		$pay_map['pay_game_status'] = 0;
        $pay_map['pay_order_number'] = $param['pay_order_number'];
        $pay_data = M("Spend","tab_")->where($pay_map)->find();
		
		if(empty($pay_data)){
			$this->error_record("游戏已通知或未找到相关数据"); return false;
		}

		$game = M('GameSet',"tab_");
		$map['game_id'] = $pay_data['game_id'];
		$game_data = $game->where($map)->find();

		if(empty($game_data)){ $this->error_record("未找到指定游戏数据"); return false;}
		if(empty($game_data['pay_notify_url'])){$this->error_record("未设置游戏支付通知地址"); return false;}
		$md5_sign = md5($pay_data['pay_order_number'].$pay_data['cost']."1".$pay_data['extend'].$game_data['game_key']);

		$data = array(
			"out_trade_no" => $pay_data['pay_order_number'],
			"price"        => $pay_data['cost'],
			"pay_status"   => 1,
			"extend"       => $pay_data['extend'],
			"signType"     => "MD5",
			"sign"         => $md5_sign
		);
		$result = $this->sy_post($data,$game_data['pay_notify_url']);
		if($result == "success"){
			$this->update_game_pay_status($pay_data['pay_order_number'],$code,$is_uc);
			    $msg['status'] = 'success';
				return $msg;
		}else{
			\Think\Log::record("游戏支付通知信息：".$result.";游戏通知地址：".$game_data['pay_notify_url']);
		}
	}

	/**
	*手游post提交数据
    */
    protected function sy_post($param,$url){
    	$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);//设置等待时间
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
    }

    /**
    *修改游戏支付状态
    */
    private function update_game_pay_status($out_trade_no="",$code=1,$is_uc=0){
        $result = false;
        $map['pay_order_number'] = $out_trade_no;
        $data = array("pay_game_status"=>1);
        $result = M('spend',"tab_")->where($map)->setField($data);
        return $result;
    }

}