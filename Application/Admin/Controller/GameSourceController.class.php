<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;

/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class GameSourceController extends ThinkController {
	const model_name = 'GameSource';

    public function lists(){
        if(isset($_REQUEST['game_name'])){
            $extend['game_name']=array('like','%'.$_REQUEST['game_name'].'%');
            unset($_REQUEST['game_name']);
        }
        if(isset($_REQUEST['sdk_version'])){
            $extend['file_type']=$_REQUEST['sdk_version'];
            unset($_REQUEST['sdk_version']);
        }
        switch ($_GET['type']) {
            case '':
            case 1:
                $extend['develop_id'] = 0;
                break;
            default:
                $this->assign('show_status',1);
                $extend['develop_id'] = array('NEQ',0);
                break;
        }
		$this->m_title = '原包管理';
		$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'GameSource/lists','status'=>1])->find());
    	parent::lists(self::model_name,$_GET["p"],$extend);
    }

    public function add($value='')
    {
    	if(IS_POST){
          C(api('Config/lists'));
    		if(empty($_POST['game_id'])){
                $this->error('游戏名称不能为空');
            }
            $game=M('Game','tab_')->where(array('id'=>$_POST['game_id']))->find();
            $_POST['game_name']=$game['game_name'];
            if(empty($_POST['file_name'])){
                $this->error('未上传游戏原包');
            }else{
                $extend=substr($_POST['file_name'],strlen($_POST['file_name'])-3,3);
                if($_POST['file_type']==1&&$extend!='apk'){
                    $this->error('游戏原包格式不正确！');
                }else if($_POST['file_type']==2&&$extend!='ipa'||$_POST['file_type']==2&&empty($_POST['bao_name'])){
                    $this->error('游戏原包格式不正确/包名为空');
                }
            }
            $map['game_id']=$_POST['game_id'];
            $map['file_type'] = $_POST['file_type'];
            $d = D('Game_source')->where($map)->find();
            $source = A('Source','Event');
            if(empty($d)){
                $source->add_source();
            }
            else{
                $type = $_POST['develop_id'] == 0 ? 1:2;//判断是添加官方游戏还是开发者游戏
                $this->error('游戏已存在原包',U('GameSource/lists',array('type'=>$type)));
            }
    	}
    	else{
           if(empty(C('WEB_SITE'))){
            header("Content-type: text/html; charset=utf-8"); 
            redirect(U('Site/config_index'), 2, '请设置网站域名');
            }
            $this->meta_title = '新增游戏原包';
			$this->m_title = '原包管理';
			$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'GameSource/lists','status'=>1])->find());
				
						
    		$this->display();
    	}
    	
    }
    
    public function del($model = null, $ids=null){
        if ( empty($ids) ) {
            $this->error('请选择要操作的数据!');
        }
        $model = M('Model')->find($model);
        $model || $this->error('模型不存在！');
        $id = array_unique((array)I('ids',0));   //var_dump($id);exit;
        foreach ($id as $key => $value) {
            $arr=explode(',',$value);
            $ids[]=reset($arr);
            $file_type[]=next($arr);
            $game_id[]=end($arr);
        }
        $game=D('Game');
        $Model = D(get_table_name($model['id']));
        $map = array('id' => array('in', $ids) );
        for ($i=0; $i <count($game_id) ; $i++) { 
            $maps['id']=$game_id[$i];
            if($file_type[$i]==1){
                $dell = array('and_dow_address'=>'','game_size'=>0);
                $game->where($maps)->setField($dell);
            }else{
                $dell = array('ios_dow_address'=>'','game_size'=>0);
                $game->where($maps)->setField($dell);
            }
        }
        $souce=M("GameSource","tab_");
        $mapp['id']=array("in",$ids);
        $list=$souce->where($mapp)->select();
        foreach ($list as $key => $value) {
            @unlink($value['file_url']);
            @unlink($value['description_url']);
            @unlink($value['plist_url']);
        }
        if($Model->where($map)->delete()){
            $this->success('删除成功');
        } else {
            $this->error('删除失败！');
        }
    }

    public function edit($id){
         $map['id']=$id;
        if(IS_POST){
            if(empty($_POST['file_name'])){
                $this->error('未上传游戏原包');
            }else{
                $extend=substr($_POST['file_name'],strlen($str)-3,3);
                if($_POST['file_type']==1&&$extend!='apk'){
                    $this->error('游戏原包格式不正确！');
                }else if($_POST['file_type']==2&&$extend!='ipa'||$_POST['file_type']==2&&empty($_POST['bao_name'])){
                    $this->error('游戏原包格式不正确/包名为空');
                }
            }
            $map['file_type'] = $_POST['file_type'];
            $d = D('Game_source')->where($map)->find();
            $source = A('Source','Event');
            if(empty($d)){
                $source->add_source();
            }
            else{
                $source->update_source($d['id'],$d['file_name']);
            }
        }
        else{
            $d = M('GameSource',"tab_")->where($map)->find();
            $this->meta_title = '更新游戏原包';
            $this->assign("data",$d);
			$this->m_title = '原包管理';
			$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'GameSource/lists','status'=>1])->find());
				
						
            $this->display();
        }
        
    }
}
