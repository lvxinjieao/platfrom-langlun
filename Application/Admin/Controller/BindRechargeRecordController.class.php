<?php
/**
 * Created by PhpStorm.
 * User: xmy 280564871@qq.com
 * Date: 2017/4/8
 * Time: 9:41
 */

namespace Admin\Controller;

use Admin\Model\BindRechargeModel;

class BindRechargeRecordController extends ThinkController{

	public function _initialize()
	{
		$this->meta_title = "绑币充值记录";
		return parent::_initialize(); // TODO: Change the autogenerated stub
	}

	public function lists($p=1){
		if (is_file(dirname(__FILE__).'/access_data_BindRechargeRecord_list.txt')&&I('get.p')>0) {
            $filetxt = file_get_contents(dirname(__FILE__).'/access_data_BindRechargeRecord_list.txt');
            $data = json_decode($filetxt,true);
        }else{
			$model = new BindRechargeModel();
			empty(I('game_id')) || $map['game_id'] = I('game_id');
			empty(I("pay_way")) || $map['pay_way'] = I("pay_way");
			empty(I('pay_status')) || $map['pay_status'] = I('pay_status');
			empty(I("account")) || $map['user_account'] = ["like","%".I("account")."%"];
			empty(I("time_start")) || $map['create_time'] = ["between",[strtotime(I("time_start")),empty(I("time_end"))?time():strtotime(I("time_end"))+86400-1]];
			$data = $model->getLists($map,"create_time desc",$p);
	        file_put_contents(dirname(__FILE__).'/access_data_BindRechargeRecord_list.txt',json_encode($data));
	    }
		//分页
        $row = intval(C('LIST_ROWS')) ? :10;
		//$pagedata= $this->array_order_page($data['count'],I('get.p'),$data['data'],$row);
        $this->showPage($data['count'],$row);
		$this->assign("data",$data);
		$this->display();
	}
}