<?php
/**
 * Created by PhpStorm.
 * User: xmy 280564871@qq.com
 * Date: 2017/4/1
 * Time: 10:14
 */

namespace App\Controller;
use User\Api\MemberApi;
use Common\Model\ShareRecordModel;

class ShareController extends BaseController{


	public function down(){
		$this->display();
	}

	public function register(){

		$this->display();
	}

	/**
	 * 获取分享链接
	 * @param $token
	 * author: xmy 280564871@qq.com
	 */
	public function get_share_url($token){
		$this->auth($token);
		$data['url'] = U("Share/register",['invite_account'=>USER_ACCOUNT],true,true);
		$data['icon'] = icon_url(C('APP_LOGO'));
		$data['title'] = C('APP_SHARE_TITLE');
		$data['contend'] = C('APP_SHARE_CONTENT');
		$this->set_message(200,1,$data);
	}

	/**
	 * 邀请好友注册
	 * @param $phone
	 * @param $password
	 * @param $v_code
	 * @param string $invite_account
	 * author: xmy 280564871@qq.com
	 */
	public function share_register($phone,$password,$v_code,$invite_account="")
	{
		#验证短信验证码
		$code_result = $this->check_code_status($phone,$v_code);
		$code_result = 200;
		switch ($code_result) {
			case -1100:
				$code_msg = '验证码无效，请重新获取验证码';
				break;
			case -1102:
				$code_msg = '时间超时,请重新获取验证码';
				break;
			case -1103:
				$code_msg = '验证码不正确，请重新输入';
				break;
			
		}
		if($code_result==200) {
			$user['account'] = $phone;
			$user['nickname'] = $phone;
			$user['password'] = $password;
			$result = 1;
			if ($result > 0) {
				$result = $this->userRegisterByApp($user);
			}
			if($result < 1){
				switch ($result) {
					case -3:
						$code_msg = '手机号已被使用';
						break;
					default:
						$code_msg = "注册失败";
				}
				$this->set_message(-1, $code_msg);
			}

			if(!empty($invite_account)) {
				$model = new ShareRecordModel();
				//添加邀请人记录
				$model->addShareRecord("invite_friend", $invite_account, $phone);

				//添加邀请好友注册积分
				$model->addPointByType("invite_friend", get_table_param('user',["account" =>$invite_account],'id')['id']);
			}
			$this->set_message(1,1,$user);
		}else{
			$this->set_message(-1,$code_msg);
		}
	}
	/**
	 * APP注册
	 * @param $user_data
	 * @return int|MemberApi
	 * author: yyh
	 */
    public function userRegisterByApp($user_data){
    	$user = new MemberApi();
	    $result = $user->app_register($user_data['account'], $user_data['password'], 2, 2, $user_data['nickname'], $user_data['sex']);
    	return $result;
    }
	/**
	 * 获取邀请记录
	 * @param $token
	 * author: yyh
	 */
	public function get_my_invite_record($token){
		$this->auth($token);
		$invite_id = USER_ID;
		$model = new ShareRecordModel();
		$data = $model->getMyInviteRecord($invite_id);
		if (empty($data)){
			$this->set_message(1064,"暂无记录");
		}else{
			$this->set_message(200,1,$data);
		}
	}


	/**
	 * 获取用户邀请统计
	 * @param $token
	 * author: xmy 280564871@qq.com
	 */
	public function get_user_invite_info($token){
		$this->auth($token);
		$invite_id = USER_ID;
		$model = new ShareRecordModel();
		$data = $model->getUserInviteInfo($invite_id);
		if (empty($data)){
			$this->set_message(1064,"暂无记录");
		}else{
			$this->set_message(200,1,$data);
		}
	}

}