<?php
/**
 * Created by PhpStorm.
 * User: xmy 280564871@qq.com
 * Date: 2017/4/7
 * Time: 13:59
 */

namespace Common\Model;

class ShareRecordModel extends BaseModel{


	/**
	 * 添加邀请好友注册记录
	 * @param $invite_account   邀请人账号
	 * @param $user_account     被邀请人账号
	 * @return mixed
	 * author: yyh 
	 */
	public function addShareRecord($type_key,$invite_account,$user_account){
		$data['invite_id'] = get_table_param('user',["account" =>$invite_account],'id')['id'];
		$data['invite_account'] = $invite_account;
		$data['user_id'] = get_table_param('user',["account" =>$user_account],'id')['id'];
		$data['user_account'] = $user_account;
		$data['create_time'] = time();
		$data['award_coin'] = 0;
		return M('ShareRecord','tab_')->add($data);
	}
	/**
	 * 增加积分
	 * @param $type_key
	 * @param $user_id
	 * @return bool
	 * author: yyh 280564871@qq.com
	 */
	public function addPointByType($type_key,$user_id){
		$type = $this->getPointType($type_key);
		$times = $type['time_of_day'];
		$map['type_id'] = $type['id'];
		$map['user_id'] = $user_id;
		$today = strtotime(date("Y-m-d"));
		$map['create_time'] = ['between',[$today,$today+86400-1]];
		$num = M('pointRecord','tab_')->where($map)->count();
		if($num >= $times && $times != 0){//判断是否超过当日领取次数
			$this->error = "当日已到达最大领取次数";
			return false;
		}else{
			$this->startTrans();
			$user_result = $this->operationPoint($user_id,$type['point'],1);
			$data['user_id'] = $user_id;
			$data['type_id'] = $type['id'];
			$data['point'] = $type['point'];
			$data['create_time'] = time();
			$data['type'] = 1;
			$record_result = M('pointRecord','tab_')->add($data);
			if($user_result !== false && $record_result !== false){
				$this->commit();
				return true;
			}else{
				$this->rollback();
				return false;
			}
		}
	}
	/**
	 * 获取积分方式
	 * @param $key
	 * @return bool|mixed
	 * author: yyh 280564871@qq.com
	 */
	public static function getPointType($key){
		$map['key'] = $key;
		$map['status'] = 1;
		$type = M("point_type","tab_")->where($map)->find();
		if(empty($type)){
			M("point_type","tab_")->error = "此奖励不存在或被禁用";
			return false;
		}
		return $type;
	}
	/**
	 * 操作用户积分
	 * @param $user_id
	 * @param $point    积分
	 * @param $type     1 增加 2减少
	 * @return bool
	 * author: xmy 280564871@qq.com
	 */
    public function operationPoint($user_id,$point,$type){
    	$user = M('User','tab_')->find($user_id);
    	if($type == 1){
    		$user['shop_point'] += $point;
	    }else{
    		if($user['shop_point'] >= $point){
    			$user['shop_point'] -= $point;
		    }else{
    			return false;
		    }
	    }
	    return M('User','tab_')->save($user);

    }
	/**
	 * 获取我的邀请记录
	 * @param $invite_id
	 * @return mixed
	 * author: xmy 280564871@qq.com
	 */
	public function getMyInviteRecord($invite_id){
		$map['invite_id'] = $invite_id;
		$data = $this->field("user_account,create_time,sum(award_coin) as award_coin")
			->where($map)
			->group("user_id")
			->order("create_time desc")
			->select();
		return $data;
	}


	/**
	 * 获取用户邀请统计
	 * @param $invite_id
	 * @return mixed
	 * author: xmy 280564871@qq.com
	 */
	public function getUserInviteInfo($invite_id){
		$map['invite_id'] = $invite_id;
		$data = $this->field("count(distinct user_id) as invite_num,sum(award_coin) as award_coin")
			->where($map)
			->group("invite_id")
			->find();
		return $data;
	}


	/**
	 * 获取奖励平台币 总额
	 * @param $invite_id
	 * @param $user_id
	 * @return mixed
	 * author: xmy 280564871@qq.com
	 */
	public function getUserInviteCoin($invite_id,$user_id){
		$map['invite_id'] = $invite_id;
		$map['user_id'] = $user_id;
		$data = $this->field("sum(award_coin) as award_coin")
			->where($map)
			->group("user_id")
			->find();
		return $data['award_coin'];
	}


	/**
	 * 邀请好友消费奖励平台币
	 * @param $user_id
	 * @param $pay_amount
	 * @param $order_number
	 * @return bool
	 * author: xmy 280564871@qq.com
	 */
	public function inviteFriendAward($user_id,$pay_amount,$order_number){
		$map['user_id'] = $user_id;
		$share_record = $this->where($map)->find();
		if(empty($share_record)){
			return true;
		}
		$invite_id = $share_record['invite_id'];

		//计算奖励
		$award_coin = round($pay_amount * 0.05,2);

		//增加邀请用户 平台币
		$invite = M("user","tab_")->find($invite_id);//邀请人

		//获取该邀请人共获得多少平台币
		$total = $this->getUserInviteCoin($invite_id,$user_id);

		//是否到达上限
		if($total >= 100){
			return true;
		}

		if($total+$award_coin > 100){
			$award_coin = 100 - $total;
		}

		$invite['balance'] += $award_coin;

		//奖励平台币记录
		$share_record['create_time'] = time();
		$share_record['award_coin'] = $award_coin;
		$share_record['order_number'] = $order_number;
		unset($share_record['id']);

		//开启事务
		$this->startTrans();
		$record_result = $this->add($share_record);//平台币奖励记录
		$invite_result = M("user","tab_")->save($invite);//邀请人平台币存储
		if($record_result === false || $invite_result === false){
			$this->rollback();
			return false;
		}else{
			$this->commit();
			return true;
		}
	}
}