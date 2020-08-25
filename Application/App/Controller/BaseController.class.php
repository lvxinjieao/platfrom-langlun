<?php
namespace App\Controller;

use Think\Controller\RestController;
use Common\Model\ServerModel;

class BaseController extends RestController
{
	protected function _initialize()
	{
		//加载配置
		C(api('Config/lists'));
        //访问记录
        access_log(4);
	}

	/**
	 * 验证用户登录token
	 * @param $token
	 * author: xmy 280564871@qq.com
	 */
	protected function auth($token,$data=[],$type=0){
		$token = think_decrypt($token);
		if(empty($token)){
			$this->set_message(1032,"信息过期，请重新登录",$data,$type);
		}
		$info = json_decode($token,true);
		if(!$info['user_id']){
			$this->set_message(1117,"token数据不完整",$data,$type);
		}
		$servermodel = new ServerModel();
    	$send_notice = $servermodel->send_server_notice($info['user_id']);
		define("USER_ID",$info['user_id']);
		define("USER_ACCOUNT",$info['account']);
		//define("IS_UC",$info['is_uc']);
	}

	/**
	 * 返回输出
	 * @param int $status 状态
	 * @param string $return_msg   错误信息
	 * @param array $data           返回数据
	 * author: xmy 280564871@qq.com
	 */
	public function set_message($status, $return_msg = 0, $data = [],$type=0)
	{
	    if ($status == 1){
	        $status = 200;
        }
		$msg = array(
			"code" => $status,
			"msg"  => $return_msg,
			"data" => $data
		);
        if ($type == 1){
            echo json_encode($msg,JSON_FORCE_OBJECT);
        }else{
            echo json_encode($msg);
        }
		exit;
	}

	/**
	 *验证签名
	 */
	public function validation_sign($encrypt = "", $md5_sign = "")
	{
		$signString = $this->arrSort($encrypt);
		$md5Str = $this->encrypt_md5($signString, $key = "");
		if ($md5Str === $md5_sign) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 *对数据进行排序
	 */
	private function arrSort($para)
	{
		ksort($para);
		reset($para);
		return $para;
	}

	/**
	 * [check_code_return 验证手机号]
	 * @param  [type]  $phone  [description]
	 * @param  [type]  $v_code [description]
	 * @param  integer $status [1清除session  其他不清除]
	 * @param  string  $type   [description]
	 * @return [type]          [description]
	 */
    public function check_code_return($phone,$v_code,$status=1,$type=''){
        $telcode = session($phone);
        if(!$telcode){
            $this->set_message(1100,'验证码无效，请重新获取验证码',$type==2?0:"");
        }

        $time = (time() - $telcode['create_time'])/60;
        if ($time>$telcode['delay']) {
            session('telsvcode',null);unset($telcode);
            $this->set_message(1102,'时间超时,请重新获取验证码',$type==2?0:"");
        }
        if ($telcode['code'] == $v_code) {
            if ($status==1){
                session('telsvcode',null);unset($telcode);
                return true;
            }else{
                $this->set_message(200,'success',$type==2?0:"");
            }
        }else{
            $this->set_message(1103,'验证码不正确，请重新输入',$type==2?0:"");
        }
    }
    public function check_code_status($phone,$v_code){
        $telcode = session($phone);
        if(!$telcode){
        	return -1100;
        }

        $time = (time() - $telcode['create_time'])/60;
        if ($time>$telcode['delay']) {
            session('telsvcode',null);unset($telcode);
            return -1102;
            // $this->set_message(1102,'时间超时,请重新获取验证码',$type==2?0:"");
        }
        if ($telcode['code'] == $v_code) {
            session('telsvcode',null);unset($telcode);
        	return 200;
        }else{
        	return -1103;
        }
    }
	/**
	 *MD5验签加密
	 */
	protected function encrypt_md5($param = "", $key = "")
	{
		#对数组进行排序拼接
		if (is_array($param)) {
			$md5Str = implode($this->arrSort($param));
		} else {
			$md5Str = $param;
		}
		$md5 = md5($md5Str . $key);
		return '' === $param ? 'false' : $md5;
	}



	/**
	 *消费记录表 参数
	 */
	private function spend_param($param = array())
	{
		$user_entity = get_user_entity($param['user_id']);
		$data_spned['user_id'] = $param["user_id"];
		$data_spned['user_account'] = $user_entity["account"];
		$data_spned['user_nickname'] = $user_entity["nickname"];
		$data_spned['game_id'] = $param["game_id"];
		$data_spned['game_appid'] = $param["game_appid"];
		$data_spned['game_name'] = $param["game_name"];
		$data_spned['server_id'] = 0;
		$data_spned['server_name'] = "";
		$data_spned['promote_id'] = $user_entity["promote_id"];
		$data_spned['promote_account'] = $user_entity["promote_account"];
		$data_spned['order_number'] = $param["order_number"];
		$data_spned['pay_order_number'] = $param["pay_order_number"];
		$data_spned['props_name'] = $param["title"];
		$data_spned['pay_amount'] = $param["real_pay_amount"];
		$data_spned['pay_time'] = NOW_TIME;
		$data_spned['pay_status'] = $param["pay_status"];
		$data_spned['pay_game_status'] = 0;
		$data_spned['pay_way'] = $param["pay_way"];
		$data_spned['spend_ip'] = $param["spend_ip"];
		return $data_spned;
	}

	/**
	 *平台币充值记录表 参数
	 */
	private function deposit_param($param = array())
	{
		$user_entity = get_user_entity($param['user_id']);
		$data_deposit['order_number'] = $param["order_number"];
		$data_deposit['pay_order_number'] = $param["pay_order_number"];
		$data_deposit['user_id'] = $param["user_id"];
		$data_deposit['user_account'] = $user_entity["account"];
		$data_deposit['user_nickname'] = $user_entity["nickname"];
		$data_deposit['promote_id'] = $user_entity["promote_id"];
		$data_deposit['promote_account'] = $user_entity["promote_account"];
		$data_deposit['pay_amount'] = $param["pay_amount"];
		$data_deposit['pay_status'] = 0;
		$data_deposit['pay_source'] = 2;
		$data_deposit['pay_way'] = $param["pay_way"];
		$data_deposit['pay_ip'] = $param["spend_ip"];
		$data_deposit['sdk_version'] = $param["sdk_version"];
		$data_deposit['create_time'] = NOW_TIME;
		return $data_deposit;
	}

	/**
	 *绑定平台币消费
	 */
	private function bind_spend_param($param = array())
	{
		$user_entity = get_user_entity($param['user_id']);
		$data_bind_spned['user_id'] = $param["user_id"];
		$data_bind_spned['user_account'] = $user_entity["account"];
		$data_bind_spned['user_nickname'] = $user_entity["nickname"];
		$data_bind_spned['game_id'] = $param["game_id"];
		$data_bind_spned['game_appid'] = $param["game_appid"];
		$data_bind_spned['game_name'] = $param["game_name"];
		$data_bind_spned['server_id'] = 0;
		$data_bind_spned['server_name'] = "";
		$data_bind_spned['promote_id'] = $user_entity["promote_id"];
		$data_bind_spned['promote_account'] = $user_entity["promote_account"];
		$data_bind_spned['order_number'] = $param["order_number"];
		$data_bind_spned['pay_order_number'] = $param["pay_order_number"];
		$data_bind_spned['props_name'] = $param["title"];
		$data_bind_spned['pay_amount'] = $param["price"];
		$data_bind_spned['pay_time'] = NOW_TIME;
		$data_bind_spned['pay_status'] = $param["pay_status"];
		$data_bind_spned['pay_game_status'] = 0;
		$data_bind_spned['pay_way'] = 1;
		$data_bind_spned['spend_ip'] = $param["spend_ip"];
		return $data_bind_spned;
	}

	/**
	 *消费表添加数据
	 */
	protected function add_spend($data)
	{
		$spend_data = $this->spend_param($data);
		$spend = M("spend", "tab_")->add($spend_data);
		return $spend;
	}

	/*
	*平台币充值记录
	*/
	protected function add_deposit($data)
	{
		$deposit_data = $this->deposit_param($data);
		$deposit = M("deposit", "tab_")->add($deposit_data);
		return $deposit;
	}


	/*
	*绑定平台币消费记录
	*/
	protected function add_bind_spned($data)
	{
		$data_bind_spned = $this->bind_spend_param($data);
		$bind_spned = M("BindSpend", "tab_")->add($data_bind_spned);
		return $bind_spned;
	}


	/**
	 * 增加绑币充值记录
	 * @param $param
	 * author: xmy 280564871@qq.com
	 */
	protected function add_bind_recharge($param){
		$user = get_user_entity($param['user_id']);
		$data['order_number']     = "";
		$data['pay_order_number'] = $param['pay_order_number'];
		$data['game_id']          = $param['game_id'];
		$data['game_appid']       = $param['game_appid'];
		$data['game_name']        = $param['game_name'];
		$data['promote_id']       = $user['promote_id'];
		$data['promote_account']  = $user['promote_account'];
		$data['user_id']          = $param['user_id'];
		$data['user_account']     = $user['account'];
		$data['user_nickname']    = $user['nickname'];
		$data['amount']           = $param['pay_amount'];
		$data['real_amount']      = $param['price'];
		$data['pay_status']       = 0;
		$data['pay_way']          = $param['pay_way'];
		$data['create_time']      = time();
		$data['zhekou']           = $param['discount'];
		$data['sdk_version']  	  = $param['sdk_version'];
		$data['recharge_ip']   	  = $param['spend_ip'];
		return M("bind_recharge","tab_")->add($data);
	}

}
