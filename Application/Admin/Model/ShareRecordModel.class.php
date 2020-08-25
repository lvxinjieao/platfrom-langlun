<?php
/**
 * Created by PhpStorm.
 * User: xmy 280564871@qq.com
 * Date: 2017/4/7
 * Time: 13:59
 */

namespace Admin\Model;

class ShareRecordModel extends TabModel
{


	/**
	 * 获取我的邀请记录
	 * @param $invite_id
	 * @return mixed
	 * author: xmy 280564871@qq.com
	 */
	public function getInviteRecord($map,$p=1)
	{
		$page = intval($p);
		$page = $page ? $page : 1; //默认显示第一页数据
		$row = 10;
		$data['data'] = $this
			->field("invite_id,invite_account,count(DISTINCT user_id) as num,max(create_time) as create_time,sum(award_coin) as award_coin")
			->where($map)
			->group("invite_id")
			->order("create_time desc")
			->page($page,$row)
			->select();

		$data['count'] = $this
			->where($map)
			->group("invite_id")
			->order("create_time desc")
			->count();

		return $data;
	}

	/**
	 * 获取详情
	 * @param $map
	 * @param $p
	 * @return mixed
	 * author: xmy 280564871@qq.com
	 */
	public function getDetail($map,$p=1){
		$page = intval($p);
		$page = $page ? $page : 1; //默认显示第一页数据
		$row = 10;
		$data['data'] = $this->field("id,user_id,user_account,sum(award_coin) as coin,create_time")
			->where($map)
			->order("create_time desc")
			->group("user_id")
			->page($page,$row)
			->select();

		$data['count'] = $this->where($map)->group("user_id")->count();

		return $data;
	}

}
