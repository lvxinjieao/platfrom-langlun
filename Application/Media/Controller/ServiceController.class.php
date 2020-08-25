<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace Media\Controller;

/**
 * 文档模型控制器
 * 文档模型列表和详情
 */
class ServiceController extends BaseController {

	public function index($type='often',$p=1){
	    $row = 10;
       if (!empty($type)){
            $type = I('type') ? I('type') : 'changjian';
            $data=M('Kefuquestion','tab_')
                ->where(array('status'=>1,'istitle'=>2,'titleurl'=>$type))
                ->page($p,$row)
                ->select();
            $count =M('Kefuquestion','tab_')
                ->where(array('status'=>1,'istitle'=>2,'titleurl'=>$type))
                ->count();
        }else{
        	 $type = I('type') ? I('type') : 'changjian';
            $data=M('Kefuquestion','tab_')
                ->where(array('status'=>1,'istitle'=>2,'titleurl'=>'changjian'))
                ->page($p,$row)
                ->select();
            $count =M('Kefuquestion','tab_')
                ->where(array('status'=>1,'istitle'=>2,'titleurl'=>$type))
                ->count();
        }

		//分页
		if($count > $row){
		    $page = new \Think\Page($count, $row);
		    $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
		    $this->assign('_page', $page->show());
		}
		
		$this->assign('data_list',$data);
		
		/*$typelist=M('Kefuquestion','tab_')
			->where(array('status'=>1,'istitle'=>1))
			->select();*/
		$typelist = array('changjian'=>'常见问题','mima'=>'密码问题','zhanghu'=>'账户问题','chongzhi'=>'充值问题','gift'=>'礼包中心','jifen'=>'积分商城');

		
		$this->assign('typelist',$typelist);
		$this->assign('type',$type);
		$this->display();
	}
}
