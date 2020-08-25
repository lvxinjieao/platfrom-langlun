<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;

/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class GiftbagController extends ThinkController {

    const model_name = 'Giftbag';

    public function lists(){
        $extend = array('key'=>'gift_name');
        if(isset($_REQUEST['time-start'])&&isset($_REQUEST['time-end'])){
            $extend['create_time'] =array('BETWEEN',array(strtotime($_REQUEST['time-start']),strtotime($_REQUEST['time-end'])+24*60*60-1));
            unset($_REQUEST['time-start']);unset($_REQUEST['time-end']);
        }elseif(isset($_REQUEST['time-start'])){
            $extend['create_time']=array('egt',strtotime($_REQUEST['time-start']));
            unset($_REQUEST['time-start']);
        }elseif(isset($_REQUEST['time-end'])){
            $extend['create_time']=array('elt',strtotime($_REQUEST['time-end'])+24*60*60-1);
            unset($_REQUEST['time-end']);
        }
        if(isset($_REQUEST['game_id'])){
            $extend['game_id']=$_REQUEST['game_id'];
            unset($_REQUEST['game_id']);
        }
        if(isset($_REQUEST['giftbag_type'])){
            $extend['giftbag_type']=$_REQUEST['giftbag_type'];
            unset($_REQUEST['giftbag_name']);
        }
        if(isset($_REQUEST['status'])){
            if($_REQUEST['status']=='全部'){
                unset($_REQUEST['status']);
            }else{
                $extend['status']=$_REQUEST['status'];
                unset($_REQUEST['status']);
            }
        }
        if(isset($_REQUEST['giftbag_version'])){
            $extend['giftbag_version'] = array('like','%'.$_REQUEST['giftbag_version'].'%');
            unset($_REQUEST['giftbag_version']);
        }
        // if($_GET['type'] == 1 || empty($_GET['type'])){
        //     $extend['developers'] = array('EQ',0);
        // }else{
        //     $extend['developers'] = array('NEQ',0);
        //     $this->assign('show_status',1);
        // }
        $extend['for_show_pic_list']='novice';
        $data = parent::order_lists(self::model_name,$_GET["p"],$extend);
		$this->assign('model', $data['model']);
        $this->assign('list_grids', $data['grids']);
        $this->assign('list_data', $data['list_data']);
        $this->meta_title = $data['model']['title'];
        $this->display();

    }

    public function record(){
        if(isset($_REQUEST['game_name'])){
                $extend['game_name']=trim($_REQUEST['game_name']);
                unset($_REQUEST['game_name']);
        }
        if (isset($_REQUEST['user_account'])) {
            $extend['user_account']=array('like','%'.trim($_REQUEST['user_account']).'%');
            unset($_REQUEST['user_account']);
        }
        if(isset($_REQUEST['sdk_version'])){
            if($_REQUEST['sdk_version'] ==''){
                unset($_REQUEST['sdk_version']);
            }else{
                $map['sdk_version'] = $_REQUEST['sdk_version'];
                $game_ids = M('game','tab_')->field('id')->where($map)->select();
                $game_ids = array_column($game_ids,'id');
                $extend['sdk_version'] = ['in',$game_ids];
                unset($_REQUEST['sdk_version']);
            }
        }
				
				$this->m_title = '礼包领取记录';
				$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'Giftbag/record','status'=>1])->find());
				
				
        parent::lists('GiftRecord',$_GET["p"],$extend);
    }

    public function add(){
        if(IS_POST){
            $Model  =   D('Giftbag');
            // 获取模型的字段信息
            $Model  =   $this->checkAttr($Model,$model['id']);
            if(empty($_REQUEST['game_id'])){
                $this->error('请选择游戏');
            }
            if(empty($_REQUEST['giftbag_version'])){
                $this->error('请选择运营平台');
            }
            $_POST['giftbag_version']=implode(',',$_POST['giftbag_version']);
            if(empty($_REQUEST['giftbag_name'])){
                $this->error('请输入礼包名称');
            }

            $start_time = I('start_time','');
            $end_time = I('end_time','');

            if(!empty($start_time) && !empty($end_time)){
                if($start_time>$end_time) $this->error('开始时间不能大于结束时间');
            }

            $data = $Model->create();
            if($data){
                $data['novice'] = str_replace(array("\r\n", "\r", "\n"), ",", $_POST['novice']);
                $data['novice'] = array_filter(explode(',',$data['novice']));
                if(empty($data['novice']))$this->error('请输入正确的激活码');
                $data['novice'] = implode(',',$data['novice']);
                $data['server_name']=get_server_name($data['server_id']);
                $data['novice_num']  = count(explode(',',$data['novice']));
                $Model->add($data);
                $this->success('添加礼包成功！', U('lists?model='.$model['name']));
            } else {
                $this->error($Model->getError());
            }
        } else {
            $this->meta_title = '新增礼包';
						
						$this->m_title = '礼包列表';
				$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'Giftbag/lists','status'=>1])->find());
				
						
            $this->display('add');
        }
    }

    public function edit($id=0){
		$_REQUEST['id'] || $this->error('请选择要编辑的用户！');
		$model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
		//获取模型信息
        $model = M('Model')->find($model['id']);
        $model || $this->error('模型不存在！');

        if(IS_POST){

            $_POST['giftbag_version']=implode(',',$_POST['giftbag_version']);
            $Model  =   D(parse_name(get_table_name($model['id']),1));

            // 获取模型的字段信息
            $Model  =   $this->checkAttr($Model,$model['id']);
            if(empty($_REQUEST['game_id'])){
                $this->error('请选择游戏');
            }

            $start_time = I('start_time','');
            $end_time = I('end_time','');

            if(!empty($start_time) && !empty($end_time)){
                if($start_time>$end_time) $this->error('开始时间不能大于结束时间');
            }

            $data = $Model->create();
            if($data){
                if(empty($start_time))$data['start_time'] = '';
                if(empty($end_time))$data['end_time'] = '';

                $data['novice'] = str_replace(array("\r\n", "\r", "\n"), ",", $_POST['novice']);
                $data['novice'] = array_filter(explode(',',$data['novice']));
                if(empty($data['novice']))$this->error('请输入正确的激活码');
                $data['novice'] = implode(',',$data['novice']);
                $data['novice_num']  = count(explode(',',$data['novice']));
                $Model->save($data);
                $this->success('保存'.$model['title'].'成功！', U('lists',array('model'=>$model['name'],'type'=>$_REQUEST['type'])));
            } else {
                $this->error($Model->getError());
            }
        } else {
            $fields     = get_model_attribute($model['id']);
            //获取数据
            $data       = D(get_table_name($model['id']))->find($id);
            $data || $this->error('数据不存在！');
            $data['giftbag_version']=explode(',',$data['giftbag_version']);

            $this->assign('model', $model);
            $this->assign('fields', $fields);
            $this->assign('data', $data);
            $this->meta_title = '编辑礼包';
						
						$this->m_title = '礼包列表';
				$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'Giftbag/lists','status'=>1])->find());
				
						
            $this->display($model['template_edit']?$model['template_edit']:'');
        }
    }

    public function del($model = null, $ids=null){
        if(empty($ids))$this->error('请选择要操作的数据');
        $model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
        parent::del($model["id"],$ids);
    }

    public function get_ajax_area_list(){
    	$area = D('Server');
    	$map['game_id'] = I('post.game_id',1);
    	$list = $area->where($map)->select();
    	$this->ajaxReturn($list);
    }

    public function get_ajax_pt_list($game_id=0){

        $game = get_game_entity($game_id,'id');
        $map['relation_game_name'] = $game['relation_game_name'];
        $list = M('game','tab_')->field('id,sdk_version')->where($map)->select();
        $this->ajaxReturn($list);
    }

}
