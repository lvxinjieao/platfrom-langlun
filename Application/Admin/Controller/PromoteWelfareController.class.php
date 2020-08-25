<?php

namespace Admin\Controller;

/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class PromoteWelfareController extends ThinkController {

	const model_name = 'PromoteWelfare';
    public function lists(){
        $extend = [];
        if(isset($_REQUEST['game_id']) ){
            $extend['game_id']  =  $_REQUEST['game_id'];
            unset($_REQUEST['game_id']);
        }
        if(isset($_REQUEST['promote_id']) ){
            $extend['promote_id']  =  $_REQUEST['promote_id'];
            unset($_REQUEST['promote_id']);
        }
        $show_status = null;
        switch ($_REQUEST['type']) {
            case '':
            case 1:
                $extend['status'] = 1;
                if(isset($_REQUEST['recharge_status']) ){
                    $extend['recharge_status']  =  $_REQUEST['recharge_status'];
                    unset($_REQUEST['recharge_status']);
                }
                break;
            case 2:
                $extend['status'] = 2;
                $show_status = 1;
                if(isset($_REQUEST['promote_status']) ){
                    $extend['promote_status']  =  $_REQUEST['promote_status'];
                    unset($_REQUEST['promote_status']);
                }
                if(isset($_REQUEST['cont_status']) ){
                    $extend['cont_status']  =  $_REQUEST['cont_status'];
                    unset($_REQUEST['cont_status']);
                }

                break;
        }
        $this->assign("show_status",$show_status);
				
		$this->m_title = '折扣设置';
		$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'PromoteWelfare/lists','status'=>1])->find());
				
				
        $data = parent::order_lists(self::model_name,$_GET["p"],$extend);
        $this->assign('model', $data['model']);
        $this->assign('list_grids', $data['grids']);
        $this->assign('list_data', $data['list_data']);
        $this->meta_title = $data['model']['title'];
        $this->display();
    	
    }

    public function add(){
        switch ($_REQUEST['status']) {
            case '1':
                if($_POST['promote_id'] == -1){
                    $this->error('请选择推广员');
                }
                if(empty($_POST['game_id'])){
                    $this->error('请选择游戏');
                }
                if(empty($_POST['promote_discount'])){
                    $this->error('请设置推广员折扣');
                }
                break;
            
            default:
                if(IS_POST && (!D('PromoteWelfare')->check_object())){
                    $this->error(D('PromoteWelfare')->getError());exit;
                }
                break;
        }
        
        $model = M('Model')->getByName(self::model_name);
				$this->m_title = '折扣设置';
				$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'PromoteWelfare/lists','status'=>1])->find());
				
        parent::add($model["id"],U('lists',array('type'=>$_POST['type'])));
    }

    public function edit($id=0){
		$id || $this->error('请选择要编辑的用户！');
		$model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
       $this->m_title = '折扣设置';
				$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'PromoteWelfare/lists','status'=>1])->find());
				
		parent::edit($model['id'],$id,U('lists',array('type'=>$_POST['type'])));
    }

    public function del($model = null, $ids=null){
        if(empty($ids))$this->error('请选择要操作的数据');
        $model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
        parent::del($model["id"],$ids);
    }

    /**
     * 商务专员状态修改
     * @author 小纯洁
     */
    public function changeStatus($field = null,$value=null)
    {
        $id = array_unique((array)I('id', 0));
        $id = is_array($id) ? implode(',', $id) : $id;
        if (empty($id)) {
            $this->error('请选择要操作的数据!');
        }
        $map['id'] = array('in', $id);
        $result = M('PromoteWelfare','tab_')->where($map)->setField($field,$value);
        $msg = $value == 0?"关闭":"开启";
        if($result !== false){
            $this->success($msg.'成功');
        }else{
            $this->error($msg.'失败');
        }
    }

}
