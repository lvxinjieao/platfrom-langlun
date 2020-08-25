<? php
namespace Mobile\Event;
use Common\Api\GameApi;
use User\Api\MemberApi;
class UserEvent {
	public function is_exist($openid) {
		$model = M("user", "tab_");
		$map['openid'] = $openid;
		$user = $model->where($map)->find();
		if (empty($user)) {
			return 0;
		} else {
			return $user["id"];
		}
	}
	public function login($data) {
		$uid = $this->is_exist($data['openid']);
		$up = D('user');
		if ($uid <= 0) {
			$user = new MemberApi;
			$uid = $user->regisert_sdk($data);
		}
		$bool = $up->login($uid, "");
		return $bool;
	}
	public function user_setDec($data) {
		$user_map["id"] = $uid;
		$user = M("user", "tab_");
		$user_data = $user->where($user_map)->find();
		if ($user['balance'] < $amount) {
			$this->error("金额不不足");
		}
		$result = $user->where($user_map)->setDec("balance", $amount); //修改充值表的条件		
		$r_map['pay_order_number'] = $pay_order_number;		
		$r_map['stauts'] = 0;					
		//修改充值表的数据		
		$r_data['status'] = 1;		
		$r_data['order_number'] = "PT_".date('Ymd').date ( 'His' ).sp_random_string(6);		
		//根据条件修改充值表的状态;		
		M('recharge','tab_')->where($r_map)->save($r_data);		
		//游戏支付 接口		
		$game = new GameApi();		
		$game_pay_data['appid'] = $data['game_appid'];		
		$game_pay_data['pay_order_number'] = $data['pay_order_number'];		
		$game_pay_data['amount'] = $data['amount'];		
		$game_pay_data['pay_time'] = $data['pay_time'];		
		$game->game_pay($game_pay_data);	
	}
}
		