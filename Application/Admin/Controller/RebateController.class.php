<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;
/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class RebateController extends ThinkController {
    const model_name = 'rebate';

    /**
    *返利设置列表
    */
    public function lists(){
        
        switch ($_REQUEST['type']) {
            case '':
            case 1 :
                $this->setRebateList($_REQUEST);
                break;
            case 2:
                $this->rebateRecordList($_REQUEST);
                break;
            default:
                
                break;
        }
    }

    private function setRebateList($search=null){
        if(isset($search['game_name'])){
            if($search['game_name']=='全部'){
                unset($search['game_name']);
            }else{
                $extend['game_name'] = $search['game_name'];
                unset($search['game_name']);
            }
        }
        if(isset($search['status'])){
            if($search['status']=='全部'){
                unset($search['status']);
            }else{
                $extend['status'] = $search['status'];
                unset($search['status']);
            }
        }	
        $extend['fields']='id,status,create_time,starttime,endtime,promote_id,game_name,money,ratio';
        $data = parent::order_lists(self::model_name,$_GET["p"],$extend);
        $this->assign('model', $data['model']);
        $this->assign('list_grids', $data['grids']);
        $this->assign('list_data', $data['list_data']);
        $this->meta_title = $data['model']['title'].'列表';
        $this->display();
    }

    private function rebateRecordList($search=null){
        if(isset($search['user_account'])){
            $map['account']=array('like','%'.trim($search['user_account']).'%');
            $res=M('user','tab_')->where($map)->field('id')->select();
            if(!empty($res)){
                foreach ($res as $key => $value) {
                    $asd[]=implode(",",$value);
                }
                $map['user_id'] = array('in',implode(',',$asd));
            }
            unset($search['user_account']);
        }
        empty(I('game_id')) || $map['game_id'] = I('game_id');
        $total =D("RebateList")->field('sum(pay_amount) pay_amount,sum(ratio_amount) ratio_amount')->where($map)->find();
        $ttotal=D("RebateList")->field('sum(pay_amount) pay_amount,sum(ratio_amount) ratio_amount')->where('create_time'.total(1))->where(array('pay_status'=>1))->find();
        $ytotal=D("RebateList")->field('sum(pay_amount) pay_amount,sum(ratio_amount) ratio_amount')->where('create_time'.total(5))->where(array('pay_status'=>1))->find();
        $this->assign('total' ,$total);
        $this->assign('ttotal',$ttotal);
        $this->assign('ytotal',$ytotal);
        parent::lists("RebateList",$_GET["p"],$map);
    }

    public function add()
    {
        if (IS_POST) {
            $rebate = D('rebate');
            $data = $rebate->create();
            if(!$data || !$rebate->check_promote()){
                $this->error($rebate->getError());
            }
            !empty(I('endtime')) || $data['endtime'] = 0;
            $res = $rebate->add($data);
            if($res !== false){
                $this->success('设置成功!',U('lists'));
            }else{
                $this->error('设置失败!');
            }
        } else {
            $this->meta_title = '新增游戏返利';
						
						$this->m_title = '返利设置';
				$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'Rebate/lists/type/1','status'=>1])->find());
				
						
            $this->display();
        }
    }

    public function edit()
    {
        $rebate = D('rebate');
        $id = $_REQUEST['id'];
        if (IS_POST) {
            if ($rebate->create() &&  $rebate->save()) {
                $this->success("编辑成功", U("lists"));
            } else {
                $this->error("编辑失败".$rebate->getError());
            }
        } else {
            $map['id'] = $id;
            $lists = $rebate->where($map)->find();
            $this->assign("data", $lists);
            
            $this->meta_title = '编辑游戏返利';
						
						
						$this->m_title = '返利设置';
				$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'Rebate/lists/type/1','status'=>1])->find());
				
						
            $this->display();
        }
    }
        
    public function del($model = null, $ids=null) {
        if(empty($ids))$this->error('请选择要操作的数据');
        $model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
        parent::del($model["id"],$ids);
    }

}
