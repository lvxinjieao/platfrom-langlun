<?php

namespace Home\Controller;
use OT\DataDictionary;
use Admin\Model\ApplyModel;

/**
 * 前台首页控制器
 * 主要获取首页聚合数据
 */
class ApplyController extends BaseController {

    public function jion_list($model=array(),$p,$map = array()){
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $name = $model['name'];
        $row    = empty($model['list_row']) ? 15 : $model['list_row'];
        $data = M($name,'tab_')
            /* 查询指定字段，不指定则查询所有字段 */
            ->field(empty($fields) ? true : $fields)
            ->join($model['jion'])
            // 查询条件
            ->where($map)
            /* 默认通过id逆序排列 */
            ->order($model['need_pk']?'id DESC':'')
            /* 数据分页 */
            ->page($page, $row)
            /* 执行查询 */
            ->select();

        /* 查询记录总数 */
        $count = M($name,"tab_")->where($map)->count();

        //分页
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $this->assign('_page', $page->show());
        }

        $this->assign('list_data', $data);
        $this->meta_title = $model['title'];
        $this->display($model['tem_list']);
    }
	//首页
    public function index($p = 0){

        //获取推广员可推广游戏id
        $game_ids = M('promote','tab_')->where(['id'=>get_pid()])->getField('game_ids');
        if(!empty($game_ids) || $game_ids === '0'){
            $game_ids = explode(',',$game_ids);
            $map['tab_game.id'] = ['in',$game_ids];
        }

        if(!empty(I('game_id'))){
            $map['tab_game.id'] = I('game_id');
            if(!in_array(I('game_id'), $game_ids)){
                $map['tab_game.id'] = -1;
            }
        }
        $map['tab_game.down_port'] = 1;
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $row    = 7;
        $map['game_status'] = 1;

        $data = M("game","tab_")
            /* 查询指定字段，不指定则查询所有字段 */
            ->field("count(tab_game.id) as count,tab_game.id,tab_game.game_size,tab_apply.register_url,tab_game.game_name,tab_game.money,tab_game.ratio,icon,game_type_name,recommend_status,promote_id,status,tab_game.sdk_version,relation_game_id,tab_game.create_time")
            ->join("tab_apply ON tab_game.id = tab_apply.game_id and tab_apply.promote_id = ".get_pid(),"LEFT")
            // 查询条件
            ->where($map)
            /* 默认通过id逆序排列 */
            ->order("sort desc,id desc")
            ->group('tab_game.relation_game_id')
            /* 数据分页 */
            ->page($page, $row)
            /* 执行查询 */
            ->select();
        /* 查询记录总数 */
        $count = M("game","tab_")
            /* 查询指定字段，不指定则查询所有字段 */
            ->field('count(distinct tab_game.relation_game_id) as count')
            ->join("tab_apply ON tab_game.id = tab_apply.game_id and tab_apply.promote_id = ".get_pid(),"LEFT")
            ->where($map)
            ->select();
        //分页
        if($count[0]['count'] > $row){
            $page = new \Think\Page($count[0]['count'], $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $this->assign('_page', $page->show());
        }
        foreach ($data as $key=>$vo){
            $vo['game_name'] = str_replace('(安卓版)','',$vo['game_name']);
            $data[$key]['game_name'] = str_replace('(苹果版)','',$vo['game_name']);
            $map['relation_game_id'] = $vo['relation_game_id'];
            $data[$key]['info'] = M("game","tab_")
                ->field('tab_game.id,tab_game.sdk_version,tab_game.game_size,tab_game.create_time,tab_apply.status,tab_game.ratio,tab_game.money,tab_apply.enable_status,tab_apply.id as applyid,tab_apply.register_url,tab_apply.dow_url,tab_apply.plist_url,tab_apply.dow_status,tab_game.material_url')
                ->join("tab_apply ON tab_game.id = tab_apply.game_id and tab_apply.promote_id = ".get_pid(),"LEFT")
                ->order("tab_game.sdk_version asc,apply_time desc")
                ->where($map)
                ->select();
            $is_shenqin = 1;
            foreach ($data[$key]['info'] as $k=>$v){
                if(!isset($v['status']) || $v['status'] == 2){
                    $is_shenqin = 0;
                }
            }
            $data[$key]['shenqing_status'] = $is_shenqin;
        }
        $this->assign("count",$count);
        $this->assign('model', $model);
        $this->assign('list_data', $data);
        $this->meta_title = "申请游戏";

        $this->display();
    }
    public function gapply(){
        if(isset($_POST['game_id'])) {
            $game_id = explode(',',$_POST['game_id']);
            $game_ids = M('promote', 'tab_')->where(['id' => get_pid()])->getField('game_ids');
            if (!empty($game_ids) || $game_ids === '0') {
                $game_ids = explode(',', $game_ids);
                $map['tab_game.id'] = ['in',$game_ids];
            }
            $map['tab_game.game_status'] = 1;
            $map['tab_game.relation_game_id'] = array('in',$game_id);
            $game = M("game", "tab_")/* 查询指定字段，不指定则查询所有字段 */
            ->field("tab_game.id,tab_apply.id as applyid,tab_game.game_name,tab_game.money,tab_game.ratio,game_type_name,recommend_status,tab_apply.promote_id,tab_game.sdk_version,relation_game_id,tab_apply.status,tab_game.dow_status")
                ->join("tab_apply ON tab_game.id = tab_apply.game_id and tab_apply.promote_id = ".get_pid(), "LEFT")
                ->where($map)
                ->select();
            foreach ($game as $key=>$v){
                if(($v['status'] == 1 || $v['status'] == 0) && $v['promote_id']){
                    unset($game[$key]);
                }
            }
            if (!$game) {
                $this->ajaxReturn(array("status" => "0", "msg" => "操作失败"));
            }
            $model = new ApplyModel(); //D('Apply');
            foreach ($game as $key => $v) {
                if($v['status'] == 2){
                    $res = $model->where(array('id'=>$v['applyid']))->save(array('status'=>0));
                }else{
                    $data['game_id'] = $v['id'];
                    $data['game_name'] = $v['game_name'];
                    $data['promote_id'] = session("promote_auth.pid");
                    $data['promote_account'] = session("promote_auth.account");
                    $data['apply_time'] = NOW_TIME;
                    $data['promote_ratio'] =  $v['ratio'];
                    $data['promote_money'] =  $v['money'];
                    $data['dispose_time'] = time();
                    $data['sdk_version'] = $v['sdk_version'];
                    C('PROMOTE_URL_AUTO_AUDIT') == 1 ? $data['status'] = 1 : $data['status'] = 0;
                    $data['enable_status'] = $v['dow_status'];
                    $data['register_url'] = "/media.php/" . "?s=" . "Game/open_game/pid/" . get_pid() . "/game_id/" . $data['game_id'] . ".html";
                    $res = $model->add($data);
                }

            }
            if ($res) {
                C('PROMOTE_URL_AUTO_AUDIT') == 1 ? $code = 1 : $code = 0;
                $this->ajaxReturn(array("status" => "1", "msg" => "申请成功", 'code' => $code, 'data' => $data['register_url']));
            } else {
                $this->ajaxReturn(array("status" => "0", "msg" => "申请失败"));
            }
        }else{
            $this->ajaxReturn(array("status" => "0", "msg" => "操作失败"));
        }
    }
    public function my_game($type = -1 ,$p=0){
        $map['promote_id'] = session("promote_auth.pid");
        if($type==-1 || $type == ''){
            $map['status'] =  1;
        }else{
            $map['status'] =  $type;
        }
        $map['tab_game.game_status'] = 1;
        $map['tab_game.test_status'] = 1;
        empty(I('game_id')) || $map['tab_game.id'] = I('game_id');
        if($_REQUEST['pattern']!=null){
            $map['tab_apply.pattern']=$_REQUEST['pattern'];
        }
    	$page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $row    = 6;
        $data = M("game","tab_")
            /* 查询指定字段，不指定则查询所有字段 */
            ->field("count(tab_game.id) as count,tab_game.id,tab_game.icon,tab_game.game_size,tab_apply.apply_time as create_time,tab_game.game_name,tab_game.relation_game_id,tab_game.sdk_version,tab_apply.promote_id,tab_apply.status,IF(tab_apply.promote_ratio <> 0,tab_apply.promote_ratio,tab_game.ratio) as ratio,IF(tab_apply.promote_money <> 0,tab_apply.promote_money,tab_game.money) as money,tab_apply.enable_status,tab_apply.id as applyid,tab_apply.register_url,tab_apply.dow_url,tab_apply.plist_url,tab_apply.dow_status,tab_game.dow_status as dow_status1,tab_game.material_url")
            ->join("tab_apply ON tab_game.id = tab_apply.game_id and tab_apply.promote_id = ".session('promote_auth.pid'))
            // 查询条件
            ->where($map)
            /* 默认通过id逆序排列 */
            ->order("tab_game.sdk_version asc,apply_time desc")
            ->group('tab_game.relation_game_id')
            /* 数据分页 */
            ->page($page, $row)
            /* 执行查询 */
            ->select();
        /* 查询记录总数 */
        $count =  M("game","tab_")
            /* 查询指定字段，不指定则查询所有字段 */
            ->field("count(distinct tab_game.relation_game_id) as count")
            ->join("tab_apply ON tab_game.id = tab_apply.game_id and tab_apply.promote_id = ".session('promote_auth.pid'))
            // 查询条件
            ->where($map)
            ->select();
        foreach ($data as $key=>$vo){
            $vo['game_name'] = str_replace('(安卓版)','',$vo['game_name']);
            $data[$key]['game_name'] = str_replace('(苹果版)','',$vo['game_name']);
            if($vo['count'] > 1){
                $map['relation_game_id'] = $vo['relation_game_id'];
                $data[$key]['info'] = M("game","tab_")
                    ->field('tab_game.id,tab_game.sdk_version,tab_game.game_size,tab_apply.apply_time as create_time,tab_apply.status,IF(tab_apply.promote_ratio <> 0,tab_apply.promote_ratio,tab_game.ratio) as ratio,IF(tab_apply.promote_money <> 0,tab_apply.promote_money,tab_game.money) as money,tab_apply.enable_status,tab_apply.id as applyid,tab_apply.register_url,tab_apply.dow_url,tab_apply.plist_url,tab_apply.dow_status,tab_game.dow_status as dow_status1,tab_game.material_url')
                    ->join("tab_apply ON tab_game.id = tab_apply.game_id and tab_apply.promote_id = ".get_pid(),"LEFT")
                    ->order("tab_game.sdk_version asc,apply_time desc")
                    ->where($map)
                    ->select();
            }
        }
        //分页
        if($count[0]['count'] > $row){
            $page = new \Think\Page($count[0]['count'], $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $this->assign('_page', $page->show());
        }
        $url="http://".$_SERVER['HTTP_HOST'].__ROOT__."/media.php/member/preg/pid/".session("promote_auth.pid");
        $this->assign("url",$url);
        $this->assign("count",$count[0]['count']);
        $this->assign('model', $model);
        $this->assign('list_data', $data);
        $this->meta_title = "我的游戏";

        $this->display();
    }



    //子渠道游戏
    public function child_game($p=0){

        if (PLEVEL>0) {echo '<script>window.history.go(-1);</script>';exit;}

        if(!empty($_REQUEST['game_id'])){
            $map['tab_apply.game_id']=$_REQUEST['game_id'];
        }
        if (!empty($_REQUEST['promote_id'])) {
            $map['tab_apply.promote_id']=$_REQUEST['promote_id'];
        } else {
            $sid = M('Promote','tab_')->field('id')->where(array('parent_id'=>PID,'status'=>1))->select();
            if ($sid){
                $map['tab_apply.promote_id']=array('in',array_column($sid,'id'));
            }else{
                $map['tab_apply.promote_id']=-1;
            }
        }

        $map['tab_game.game_status']  = 1;//游戏状态
        $start_time = strtotime(I('time_start'));
        $end_time   = strtotime(I('time_end'));
        if(!empty($start_time)&&!empty($end_time)){
            $map['tab_apply.dispose_time']  = ['BETWEEN',[$start_time,$end_time+24*60*60-1]];
            unset($_REQUEST['time_start']);unset($_REQUEST['time_end']);
        }else if(!empty($start_time)){
            $map['tab_apply.dispose_time'] = array('gt',$start_time);
        }else if(!empty($end_time)){
            $map['tab_apply.dispose_time'] = array('lt',$end_time+24*60*60-1);
        }
        $map['tab_apply.status']=1;
        $map['tab_game.game_status']  = 1;//游戏状态

        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $row  = 10;

        $data = M("game","tab_")
            /* 查询指定字段，不指定则查询所有字段 */
            ->field("count(tab_game.id) as count,tab_apply.dispose_time,tab_game.id,tab_game.icon,tab_game.game_size,tab_apply.id as applyid,tab_game.game_name,tab_game.relation_game_id,tab_game.sdk_version,tab_apply.promote_id,tab_apply.status,IF(tab_apply.promote_ratio <> 0,tab_apply.promote_ratio,tab_game.ratio) as ratio,IF(tab_apply.promote_money <> 0,tab_apply.promote_money,tab_game.money) as money,tab_apply.promote_account")
            ->join("tab_apply ON tab_game.id = tab_apply.game_id ")
            // 查询条件
            ->where($map)
            /* 默认通过id逆序排列 */
            ->order("tab_game.sdk_version asc,tab_apply.promote_id desc")
            ->group('tab_game.relation_game_id')
            /* 数据分页 */
            ->page($page, $row)
            /* 执行查询 */
            ->select();

        /* 查询记录总数 */
        $count =  M("game","tab_")
            /* 查询指定字段，不指定则查询所有字段 */
            ->field("count(distinct tab_game.relation_game_id) as count")
            ->join("tab_apply ON tab_game.id = tab_apply.game_id")
            // 查询条件
            ->where($map)
            ->select();
        foreach ($data as $key=>$vo){
            $vo['game_name'] = str_replace('(安卓版)','',$vo['game_name']);
            $data[$key]['game_name'] = str_replace('(苹果版)','',$vo['game_name']);
            if($vo['count'] > 1){
                $map['relation_game_id'] = $vo['relation_game_id'];
                $data[$key]['info'] = M("game","tab_")
                    ->field('tab_game.id,tab_apply.id as applyid,tab_apply.dispose_time,tab_game.sdk_version,tab_game.game_size,tab_apply.apply_time as create_time,tab_apply.status,IF(tab_apply.promote_ratio <> 0,tab_apply.promote_ratio,tab_game.ratio) as ratio,IF(tab_apply.promote_money <> 0,tab_apply.promote_money,tab_game.money) as money')
                    ->join("tab_apply ON tab_game.id = tab_apply.game_id ","LEFT")
                    ->order("tab_game.sdk_version asc,tab_apply.promote_id desc")
                    ->where($map)
                    ->select();
            }
        }
        if($count[0]['count'] > $row){
            $page = new \Think\Page($count[0]['count'], $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $page->parameter['p'] = I('p',1);
            $page->parameter['game_id'] = I('request.game_id');
            $this->assign('_page', $page->show());
        }

        $this->assign("count",$count[0]['count']);
        $this->assign('list_data', $data);


        $this->meta_title = "子渠道游戏";
        $this->display();
    }



    //修改注册单价和分成比例
    public function changevalue() {
        if (IS_POST) {
            if (!is_numeric($_REQUEST['id']) || $_REQUEST['id'] <=0) {
                echo json_encode(array('status'=>0,'info'=>'数据有误'));exit;
            }
            if (!is_numeric($_REQUEST['value']) || $_REQUEST['value']<0) {
                echo json_encode(array('status'=>0,'info'=>'数据有误'));exit;
            }
            $apply = M('apply','tab_');
            if ($_REQUEST['type']==1) {
                $res = $apply->where(array('id'=>$_REQUEST['id']))->setField(array('promote_money'=>$_REQUEST['value']));
                if ($res) {
                    echo json_encode(array('status'=>1,'info'=>'注册单价修改成功'));exit;
                } else {
                    echo json_encode(array('status'=>0,'info'=>'注册单价修改失败'));exit;
                }
            } elseif ($_REQUEST['type']==2) {
                $res = $apply->where(array('id'=>$_REQUEST['id']))->setField(array('promote_ratio'=>$_REQUEST['value']));
                if ($res) {
                    echo json_encode(array('status'=>1,'info'=>'分成比例修改成功'));exit;
                } else {
                    echo json_encode(array('status'=>0,'info'=>'分成比例修改失败'));exit;
                }
            } else {
                echo json_encode(array('status'=>0,'info'=>'数据有误'));exit;
            }
        } else {
            echo json_encode(array('status'=>0,'info'=>'数据有误'));exit;
        }
    }



    /**
	申请游戏
    */
    public function apply(){
        if(isset($_POST['game_id'])) {
            $game_ids = M('promote', 'tab_')->where(['id' => get_pid()])->getField('game_ids');
            if (!empty($game_ids) || $game_ids === '0') {
                $game_ids = explode(',', $game_ids);
                $map['tab_game.id'] = ['in',$game_ids];
            }
            $map['tab_game.game_status'] = 1;
            $map['tab_game.relation_game_id'] = $_POST['game_id'];
            $game = M("game", "tab_")/* 查询指定字段，不指定则查询所有字段 */
                ->field("tab_game.id,tab_apply.id as applyid,tab_game.game_name,tab_game.money,tab_game.ratio,game_type_name,recommend_status,tab_apply.promote_id,tab_game.sdk_version,relation_game_id,tab_apply.status,tab_game.dow_status")
                ->join("tab_apply ON tab_game.id = tab_apply.game_id and tab_apply.promote_id = ".get_pid(), "LEFT")
                ->where($map)
                ->select();
            foreach ($game as $key=>$v){
                if(($v['status'] == 1 || $v['status'] == 0) && $v['promote_id']){
                    unset($game[$key]);
                }
            }
            if (!$game) {
                $this->ajaxReturn(array("status" => "0", "msg" => "操作失败"));
            }
            $model = new ApplyModel(); //D('Apply');
            foreach ($game as $key => $v) {
                if($v['status'] == 2){
                    $res = $model->where(array('id'=>$v['applyid']))->save(array('status'=>0));
                }else{
                    $data['game_id'] = $v['id'];
                    $data['game_name'] = $v['game_name'];
                    $data['promote_id'] = session("promote_auth.pid");
                    $data['promote_account'] = session("promote_auth.account");
                    $data['apply_time'] = NOW_TIME;
                    $data['promote_ratio'] =  $v['ratio'];
                    $data['promote_money'] =  $v['money'];
                    $data['dispose_time'] = time();
                    $data['sdk_version'] = $v['sdk_version'];
                    C('PROMOTE_URL_AUTO_AUDIT') == 1 ? $data['status'] = 1 : $data['status'] = 0;
                    $data['enable_status'] = $game['dow_status'];
                    $data['register_url'] = "/media.php/" . "?s=" . "Game/open_game/pid/" . get_pid() . "/game_id/" . $data['game_id'] . ".html";
                    $res = $model->add($data);
                }

            }
            if ($res) {
                C('PROMOTE_URL_AUTO_AUDIT') == 1 ? $code = 1 : $code = 0;
                $this->ajaxReturn(array("status" => "1", "msg" => "申请成功", 'code' => $code, 'data' => $data['register_url']));
            } else {
                $this->ajaxReturn(array("status" => "0", "msg" => "申请失败"));
            }
        }else{
            $this->ajaxReturn(array("status" => "0", "msg" => "操作失败"));
        }
    }

    /*
     * APP申请
     */
    public function app_index($version=0) {
        if(!empty($version)){
            if($version == 1) $map['tab_app.app_version'] = 1;
            if($version == 2) $map['tab_app.app_version'] = 0;
        }
        $map['tab_app.name'] = array('like','%联运平台%');
        $promote_id = PID;
        $data = M('app','tab_')
            ->field('tab_app.*,p.status as apply_status,p.app_name,p.dow_url,p.promote_id,p.apply_time,p.icon,p.enable_status,p.dispose_time')
            ->join("left join tab_app_apply p on p.app_id = tab_app.id and p.promote_id = {$promote_id}")
            ->where($map)
            ->select();
        $url = 'http://'.$_SERVER['HTTP_HOST'].U('Home/app_down');
        $this->assign("url",$url);
        $this->assign('data',$data);
        $this->meta_title = "APP列表";
        $this->display();
    }

    /*
     * APP信息修改
     */
    public function app_edit($id=0){
        $model = M("app_apply","tab_");
        if(IS_POST){
            $arr_post = I("post.");
            $id = $arr_post['id'];
            $condition['id'] = $arr_post['id'];
            $condition['promote_id'] = PID;
            unset($arr_post['id']);
            $result = $model->where($condition)->save($arr_post);
            if($result){
                $map['id'] = array('neq',$id);
                $map['promote_id'] = PID;
                $data = $model->where($map)->field("id,status")->find();
                if($data['status'] == 1){
                    $model->where(array("id"=>$data['id']))->save($arr_post);
                }
                $res['status'] = 1;
                $res['msg'] = "修改成功";
                $res['url'] = U('Apply/app_index');
            }else{
                $res['status'] = 0;
                $res['msg'] = "修改失败";
            }
            $this->ajaxReturn($res);
            exit;
        }
        $map['app_id'] = $id;
        $map['promote_id'] = PID;
        $data = $model->where($map)->field("id,app_name,version,icon,back_img,status")->find();
        if(!$data)$this->error("游戏不存在");
        if($data['status'] != 1)$this->error("游戏未通过审核");
        $this->assign("data",$data);
        $this->meta_title = "APP修改";
        $this->display();
    }

    //app申请
    public function apply_app($app_id){
        $app = M('app','tab_')->find($app_id);
        $map['app_id'] = $app_id;
        $map['promote_id'] = PID;
        $data = M('app_apply','tab_')->where($map)->find();
        if(!empty($data)){
            if($data['status'] == 2){
                $result = M('app_apply','tab_')->where(array('id'=>$data['id']))->save(array('status'=>0));
                if($result !== false){
                    $res['status'] = 1;
                    $res['msg'] = '申请成功';
                }else{
                    $res['status'] = 2;
                    $res['msg'] = '申请失败';
                }
            }else{
                $res['status'] = 2;
                $res['msg'] = '该渠道已经申请过此APP！';
            }
        }else{
            $data['promote_id'] = PID;
            $data['app_id'] = $app_id;
            $data['app_name'] = $app['name'];
            $data['app_version'] = $app['app_version'];
            $data['apply_time'] = time();
            C('PROMOTE_APP_AUTO') == 1 ? $data['status'] = 1 : $data['status'] = 0;
            $data['icon'] = C('APP_ICO');
            $data['version']=$app['version'];
            $data['enable_status'] = 0;

            $result = M('app_apply','tab_')->add($data);
            if($result !== false){
                $res['status'] = 1;
                $res['msg'] = '申请成功';
            }else{
                $res['status'] = 2;
                $res['msg'] = '申请失败';
            }
        }
        $this->ajaxReturn($res);
    }
    //生成二维码
    public function qrcode($url='',$level=3,$size=4,$type=0,$pid=0){
        $url =base64_decode( base64_decode($url));
        $url = str_replace(".html","",$url);
        Vendor('phpqrcode.phpqrcode');
        $errorCorrectionLevel =intval($level) ;//容错级别
        $matrixPointSize = intval($size);//生成图片大小
        //生成二维码图片
        //echo $_SERVER['REQUEST_URI'];
        ob_clean();
        $object = new \QRcode();
        echo $object->png($url, false, $errorCorrectionLevel, $matrixPointSize, 2);
    }

}