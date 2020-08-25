<?php
/**
 * Created by PhpStorm.
 * User: xmy 280564871@qq.com
 * Date: 2017/5/5
 * Time: 16:22
 */

namespace Common\Model;


class UserModel extends BaseModel {

	protected $_auto = [
		['password', 'think_ucenter_md5', self::MODEL_BOTH, 'function', UC_AUTH_KEY],
	];
	/**
	 * 绑定手机号
	 * @param $user_id
	 * @param $phone
	 * @return bool
	 * author: xmy 280564871@qq.com
	 */
	public function bindPhone($user_id,$phone){
		$data['id'] = $user_id;
		$data['phone'] = $phone;
		return $this->save($data);
	}

	/**
	 * 第三方注册
	 * @param $user
	 * @return bool|mixed
	 * author: xmy 280564871@qq.com
	 */
	public function thirdRegister($user){
		$data = $this->create($user);
		if(!$data){
			return false;
		}
		$result = $this->add($data);
		if($result){
            $record=array(
                'user_id'=>$result,
                'user_account'=>$data['account'],
                'user_nickname'=>$data['nickname'],
                'game_id'=>$data['game_id'],
                'game_name'=>$data['game_name'],
                'server_id'=>null,
                'type'=>2,
                'server_name'=>null,
                'promote_id'=>$data['promote_id'],
                'login_time'=>NOW_TIME,
                'login_ip'=>get_client_ip(),
            );
            M('user_login_record','tab_')->add($record);
        }
		return $result;
	}
	public function getUserOneParam($user_id,$param){
		if(!$param){
			return -1;
		}
		$data = $this
		        ->field($param)
		        ->find($user_id);
		if(empty($data)){
		    $data['shop_point'] = 0;
        }
		return $data;
	}
	public function updateShopAddress($user,$data){
		$save['id'] = $user;
		$save['shop_address'] = $data;
		$res = $this->save($save);
		return $res;
	}
	/**
	 * [send_msg description]
	 * @param  [type]  $user [用户id]
	 * @param  integer $type [2玩家实名认证]
	 * @param  [type]  $msg  [description]
	 * @return [type]        [description]
	 */
	public function send_msg($user,$type=2,$msg){
		// $
	}
}