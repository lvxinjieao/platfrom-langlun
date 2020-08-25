<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------
namespace Home\Event;
use Think\Controller;
/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class DiscountEvent extends BaseEvent {

	public function promoteWelfare($p,$extend=[]){
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $row = 10;
        $model = D('PromoteWelfare');
        // 条件搜索
        $map = [];
        foreach(I('get.') as $name=>$val){
            $map[$name] =   $val;
        }
        $map = array_merge($map,$extend['map']);
        $lists_data = $lists = $model
                        ->where($map)
                        ->page($page,$row)
                        ->order($extend['order'])
                        ->select();
//        foreach ($lists_data as $key => $value) {
//            if($value['promote_id']==-2||$value['promote_id']==-1){
//                $new_data[$value['game_id']]['game_id'] = $value['game_id'];
//                $new_data[$value['game_id']]['game_name'] = $value['game_name'];
//                $new_data[$value['game_id']]['first_discount'] = $value['first_discount'];
//                $new_data[$value['game_id']]['continue_discount'] = $value['continue_discount'];
//                $new_data[$value['game_id']]['promote_status'] = $value['promote_status'];
//                $new_data[$value['game_id']]['cont_status'] = $value['cont_status'];
//            }elseif($value['promote_id']>0){
//                $new_data[$value['game_id']]['game_id'] = $value['game_id'];
//                $new_data[$value['game_id']]['game_name'] = $value['game_name'];
//                $new_data[$value['game_id']]['game_discount'] = $value['game_discount'];
//                $new_data[$value['game_id']]['promote_discount'] = $value['promote_discount'];
//                $new_data[$value['game_id']]['recharge_status'] = $value['recharge_status'];
//            }
//            $new_data[$value['game_id']]['promote_id'] = $value['promote_id'];
//        }
        $count = $lists = $model->where($map)->count();
        //分页
        if($count > $row){
            $page = new \Think\Page($count, $row);
            //分页跳转的时候保证查询条件
            foreach($_REQUEST as $key=>$val) {
                $page->parameter[$key]   =  $val;
            }
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $this->assign('_page', $page->show());
        }
        return $lists_data;
    }
   
}
