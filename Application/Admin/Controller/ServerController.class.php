<?php

namespace Admin\Controller;
use Admin\Event\BatchImportExcelEvent;
use User\Api\UserApi as UserApi;

/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class ServerController extends ThinkController {
	const model_name = 'Server';

    public function lists(){
        if(isset($_REQUEST['server_name'])){
            $extend['server_name']=array('like','%'.$_REQUEST['server_name'].'%');
            unset($_REQUEST['server_name']);
        }
        if(isset($_REQUEST['sdk_version'])){
            $extend['server_version']=$_REQUEST['sdk_version'];
            unset($_REQUEST['sdk_version']);
        }

        if(isset($_REQUEST['time-start']) && isset($_REQUEST['time-end'])){
            $extend['start_time']  =  array('BETWEEN',array(strtotime($_REQUEST['time-start']),strtotime($_REQUEST['time-end'])+24*60*60-1));
            unset($_REQUEST['time-start']);unset($_REQUEST['time-end']);
        }elseif (!empty($_REQUEST['time-start']) ) {
            $extend['start_time'] = array('BETWEEN',array(strtotime($_REQUEST['time-start']),time()));

        } elseif (!empty($_REQUEST['time-end']) ) {
            $extend['start_time'] = array('elt',strtotime($_REQUEST['time-end'])+24*60*60-1);
        }
				
        if(isset($_REQUEST['game_name'])){
            if($_REQUEST['game_name']=='全部'){
                unset($_REQUEST['game_name']);
            }else{
                $extend['game_name']=$_REQUEST['game_name'];
                unset($_REQUEST['game_name']);
            }
        }
        $extend['order']="start_time desc";
    	parent::lists(self::model_name,$_GET["p"],$extend);
    }

    public function add(){
        if(IS_POST){
            if(empty(I('game_id',0)))$this->error('请选择游戏');
            $server = M('server','tab_')->field('id')->where(array('server_name'=>I('server_name',''),'game_id'=>I('game_id',0)))->find();
            if($server)$this->error('区服已存在');
            action_log('add_server','server',I('game_name',''),UID);
        }
        $model = M('Model')->getByName(self::model_name);
    	parent::add($model["id"]);
    }

    public function edit($id=0){
		$id || $this->error('请选择要编辑的用户！');
        if(IS_POST){
            $server = M('server','tab_')->field('id')->where(array('server_name'=>I('server_name',''),'game_id'=>I('game_id',0),'id'=>array('neq',I('id',0))))->find();
            if($server)$this->error('区服已存在');
            action_log('edit_server','server',I('game_name',''),UID);
        }
		$model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
		parent::edit($model['id'],$id);
    }

    public function del($model = null, $ids=null){
        if(empty($ids))$this->error('请选择要操作的数据');
        if(!is_array($ids)){
            $game_name = M('server','tab_')->where(['id'=>$ids])->getField('game_name');
            action_log('del_server','server',$game_name,UID);
        }else{
            action_log('del_server_batch','server',UID,UID);
        }

        $model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
        parent::del($model["id"],$ids);
    }
     //批量新增
    public function batch(){
        if(IS_POST){

            $type = $_REQUEST['type'];
            switch ($type) {
                case 1:
                    $this->batchImport();
                    break;
                case 2:
                    $this->batchExcel();
                    break;
            }
        }else{
            $this->meta_title = '新增区服管理';
            $this->display();
        }
    }


    //导入excel
    private function batchExcel(){

        $excel = new BatchImportExcelEvent();
        if (empty($_FILES['excelData'])) {$this->ajaxReturn(["status"=>0,"info"=>'请选择文件']);exit;}
        $info = $excel->uploadExcel($_FILES['excelData']);
        $data = [];
        if(is_array($info)){
            $filename = './Uploads/' . $info['savepath'] . $info['savename'];
            $data = $excel->importExcel($filename);
            if(is_array($data)){
                $excel->serverDataInsert($data);
            }else{
                $this->ajaxReturn(["status"=>0,"info"=>$data]);
            }
        }else{
            $this->ajaxReturn(["status"=>0,"info"=>$info]);
        }

    }



    //批量新增
    private function batchImport(){

        $server_str = str_replace(array("\r\n", "\r", "\n"), "", I('server'));
        $server_ar1 = explode(';',$server_str);
        array_pop($server_ar1);
        $num = count($server_ar1);
        if($num > 100 ){
            $this->error('区服数量过多，最多只允许添加100个！');
        }
        $verify = ['game_id','server_name','time'];
        static $error = 0;
        foreach ($server_ar1 as $key=>$value) {
            $arr = explode(',',$value);
            foreach ($arr as $k=>$v) {
                $att = explode('=',$v);
                if(in_array($att[0],$verify)){
                    switch ($att[0]){
                        case 'time' :
                            $time = $server[$key]['start_time'] = strtotime($att[1]);
                            break;
                        case 'game_id':
                            $game = M('Game','tab_')->field('id')->find($att[1]);
                            if(empty($game)){
                                ++$error;
                                continue;
                            }
                            $server[$key]['game_id'] = $att[1];
                            break;
                        default:
                            $server[$key][$att[0]] = $att[1];
                    }
                }
            }
            $gameinfo = M('Game','tab_')->field('id,game_name,sdk_version')->find($server[$key]['game_id']);
            $old = M('server','tab_')->field('id')->where(array('server_name'=>$server[$key]['server_name'],'game_id'=>$server[$key]['game_id']))->find();
            if(!$old){
                unset($server[$key]);
                continue;
            }
            $server[$key]['game_name'] = $gameinfo['game_name'];
            $server[$key]['server_num'] = 0;
            $server[$key]['recommend_status'] = 1;
            $server[$key]['show_status'] = 1;
            $server[$key]['stop_status'] = 0;
            $server[$key]['server_status'] = 0;
            $server[$key]['parent_id'] = 0;
            $server[$key]['server_version'] = $gameinfo['sdk_version'];
            $server[$key]['create_time'] = time();
        }
        $res = M('server','tab_')->addAll($server);
        if($res !== false){
            action_log('add_server_batch','server',UID,UID);
            $this->success('添加成功！',U('Server/lists'));
        }else{
            $this->error('添加失败！'.M()->getError());
        }

    }
}
