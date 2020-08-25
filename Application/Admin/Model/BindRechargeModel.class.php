<?php
/**
 * Created by PhpStorm.
 * User: xmy 280564871@qq.com
 * Date: 2017/4/8
 * Time: 10:05
 */

namespace Admin\Model;

class BindRechargeModel extends TabModel{

	public function getLists($map="",$order="create_time desc",$p=1){
		$page = intval($p);
		$page = $page ? $page : 1; //默认显示第一页数据
        $row    = intval(C('LIST_ROWS')) ? :(empty($model['list_row']) ? 10 : $model['list_row']);
		$result['data'] = $this->where($map)->order($order)->page($p,$row)->select();
		$result['count'] = $this->where($map)->order($order)->count();

		$today = strtotime(date("Ymd"));
		$today_map['create_time'] = ['between',[$today,$today+86400-1]];
		$yesterday_map['create_time'] = ['between',[$today-86400,$today-1]];
		//本页
		$result['page_total'] = $this->where($map)->where(['pay_status'=>1])->page($page,$row)->sum("real_amount");
		//昨日
		$result['yesterday'] = $this->where($today_map)->where(['pay_status'=>1])->sum("real_amount");
		//今日
		$result['today'] = $this->where($yesterday_map)->where(['pay_status'=>1])->sum("real_amount");
		//总计
		$map['pay_status'] =1;
		$result['total'] = $this->where($map)->sum("real_amount");
		return $result;
	}
	
	/*
	 * 绑币充值未到账列表
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
	public function checkBindCoin() {
		
		$list = $this->field('id,user_id,user_account,amount,pay_order_number')->where(array('pay_status'=>0))->select();
		
		if ($list[0]) {
			
			$list = D('check')->dealWithCheckList(104,$list);
			
			if (empty($list[0])) {return '';}
			
			foreach ($list as $k => $v) {
				$data[$k]['info'] = '玩家：'.$v['user_account'].',绑币充值,充值金额：'.$v['amount'].',订单状态：下单未付款';
				$data[$k]['type'] = 104;
				$data[$k]['url'] = U('BindRechargeRecord/lists');
				$data[$k]['create_time'] = time();
				$data[$k]['status']=0;
				$data[$k]['position'] = $v['id'];
			}
			return $data;
		}else {
			return '';
		}
		
	}	
	
	
	
	
}