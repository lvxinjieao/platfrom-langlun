<?php

namespace Admin\Controller;
use Com\Wechat;
use Com\WechatAuth;
use User\Api\UserApi as UserApi;
/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class GameController extends ThinkController {
    const model_name = 'game';

    /**
    *游戏信息列表
    */
    public function lists(){
        if(isset($_REQUEST['game_type_name'])){
            if($_REQUEST['game_type_name']=='全部'){
                unset($_REQUEST['game_type_name']);
            }else{
                $extend['game_type_name'] = $_REQUEST['game_type_name'];
                unset($_REQUEST['game_type_name']);
            }
        }
        if(isset($_REQUEST['game_name'])){
            if($_REQUEST['game_name']=='全部'){
                unset($_REQUEST['game_name']);
            }else{
                $extend['game_name'] = $_REQUEST['game_name'];
                unset($_REQUEST['game_name']);
            }
        }
        if(isset($_REQUEST['recommend_status'])){
            if($_REQUEST['recommend_status']==''){
                unset($_REQUEST['recommend_status']);
            }else{
                $extend['recommend_status'] = array('like','%'.$_REQUEST['recommend_status'].'%');
                unset($_REQUEST['recommend_status']);
            }
        }
        if(isset($_REQUEST['game_type_name'])){
            if($_REQUEST['game_type_name']=='全部'){
                unset($_REQUEST['game_type_name']);
            }else{
                $extend['game_type_name'] = $_REQUEST['game_type_name'];
                unset($_REQUEST['game_type_name']);
            }
        }
        
        if(isset($_REQUEST['game_appid'])){
            $extend['game_appid'] = array('like','%'.$_REQUEST['game_appid'].'%');
            unset($_REQUEST['game_appid']);
        }
        $extend['sdk_version'] = 3;
        $extend['order']="sort desc,id desc ";
        parent::lists(self::model_name,$_GET["p"],$extend);
    }

    /**
     *  第三方游戏添加
     */
    public function small_add(){

        if(IS_POST){
            $game   =   D('smallGame');//M('$this->$model_name','tab_');
            $pinyin = new \Think\Pinyin();
            $num=mb_strlen($_POST['game_name'],'UTF8');
            $short = '';
            for ($i=0; $i <$num ; $i++) {
                $str=mb_substr( $_POST['game_name'], $i, $i+1, 'UTF8');
                $short.=$pinyin->getFirstChar($str);
            }

            $_POST['short']=$short;
            $data = D('smallGame')->create();
            if(!$data){
                $this->error($game->getError());
            }
            if(empty($_POST['type'])){
                if(empty($_POST['wechat_id']))$this->error('请选择关联公众号');
                if(empty($_POST['thumbnail']))$this->error('请上传小程序卡片图');
                if(empty($_POST['appid']))$this->error('请输入公众号appid');
                if(empty($_POST['page_path']))$this->error('请输入小程序路径');
            }else{
                if(empty($_POST['qrcode']))$this->error('请上传小程序码');
            }
            $res = D('smallGame')->add($data);
            if(!$res){
                $this->success('新增失败');
            }else{
                //获取access_token去生成临时二维码存入数据库
                if(empty($data['type'])){
                    $wechat = M('wechat','tab_')->field('appid,secret')->where(['id'=>$data['wechat_id']])->find();
                    $appid = $wechat['appid'];
                    $appsecret = $wechat['secret'];
                    if($appid == C('wechat.appid')){
                        $filename = 'access_token_validity.txt';
                    }else{
                        $filename = 'access_token_validity_'.$data['wechat_id'].'.txt';
                    }
                    $result = auto_get_access_token($filename);
                    //获取access_token
                    if (!$result['is_validity']) {
                        $auth = new WechatAuth($appid, $appsecret);
                        $token = $auth->getAccessToken();
                        $token['expires_in_validity'] = time() + $token['expires_in'];
                        wite_text(json_encode($token), $filename);
                        $result = auto_get_access_token($filename);
                    }
                    $auth = new WechatAuth($appid, $appsecret,$result['access_token']);
                    $authres  = $auth->qrcodeCreate("SM_".$res,2505600);

                    if($authres['ticket']==''){
                        $this->error('小程序ticket获取失败');
                    }
                    $qrcodeurl = $auth->showqrcode($authres['ticket']);
                    $result = $auth->materialAddMaterial(substr(get_cover($data['thumbnail'],'path'),1),'image');
                    $save['media_id'] = $result['media_id'];
                    $save['update_time'] = time();
                    $save['qrcodeurl'] = $qrcodeurl;
                    $save['qrcode_time'] = time();
                    D('smallGame')->where(['id'=>$res])->save($save);
                }

                $this->success('新增成功',U('small_game'));
            }
        }
        else{
            $this->meta_title = '新增小程序';
            $this->display();
        }

    }
    /**
     *   第三方游戏编辑
     */
    public function small_edit($id=null){
        if(IS_POST){
            $game   =   D('smallGame');//M('$this->$model_name','tab_');
            $pinyin = new \Think\Pinyin();
            $num=mb_strlen($_POST['game_name'],'UTF8');
            $short = '';
            for ($i=0; $i <$num ; $i++) {
                $str=mb_substr( $_POST['game_name'], $i, $i+1, 'UTF8');
                $short.=$pinyin->getFirstChar($str);
            }
            $_POST['short']=$short;

            $data = D('smallGame')->create();
            if(!$data){
                $this->error($game->getError());
            }
            if(empty($_POST['type'])){
                if(empty($_POST['wechat_id']))$this->error('请选择关联公众号');
                if(empty($_POST['thumbnail']))$this->error('请上传小程序卡片图');
                if(empty($_POST['appid']))$this->error('请输入公众号appid');
                if(empty($_POST['page_path']))$this->error('请输入小程序路径');
            }else{
                if(empty($_POST['qrcode']))$this->error('请上传小程序码');
            }
            if(empty($data['type'])){
                //获取access_token去生成临时二维码存入数据库
                $wechat = M('wechat','tab_')->field('appid,secret')->where(['id'=>$data['wechat_id']])->find();
                $appid = $wechat['appid'];
                $appsecret = $wechat['secret'];
                if($appid == C('wechat.appid')){
                    $filename = 'access_token_validity.txt';
                }else{
                    $filename = 'access_token_validity_'.$data['wechat_id'].'.txt';
                }
                $result = auto_get_access_token($filename);
                //获取access_token
                if (!$result['is_validity']) {
                    $auth = new WechatAuth($appid, $appsecret);
                    $token = $auth->getAccessToken();
                    $token['expires_in_validity'] = time() + $token['expires_in'];
                    wite_text(json_encode($token), $filename);
                    $result = auto_get_access_token($filename);
                }
                $auth = new WechatAuth($appid, $appsecret,$result['access_token']);
                $authres  = $auth->qrcodeCreate("SM_".$data['id'],2505600);
                if($authres['ticket']==''){
                    $this->error('小程序ticket获取失败');
                }
                $qrcodeurl = $auth->showqrcode($authres['ticket']);
                $result = $auth->materialAddMaterial(substr(get_cover($data['thumbnail'],'path'),1),'image');
                $data['media_id'] = $result['media_id'];
                $data['update_time'] = time();
                $data['qrcodeurl'] = $qrcodeurl;
                $data['qrcode_time'] = time();
            }
            $res = D('smallGame')->save($data);
            if($res === false){
                $this->success('更新失败');
            }else{
                $this->success('更新成功',U('small_game'));
            }
        }
        else{
            $id || $this->error('id不能为空');
            $data = D('smallGame')->find($id);
            $data || $this->error('数据不存在！');
            $this->assign('data', $data);
            $this->meta_title = '编辑小程序';
            $this->display();
        }
    }

    /**
     *第三方游戏信息列表
     */
     public function small_game(){
        if(isset($_REQUEST['game_id'])){
            $extend['id'] = $_REQUEST['game_id'];
            unset($_REQUEST['game_id']);
        }

        if(isset($_REQUEST['appid'])){
            $extend['appid'] = $_REQUEST['appid'];
            unset($_REQUEST['appid']);
        }
        $extend['order']="sort desc,id desc ";
        parent::lists('SmallGame',$_GET["p"],$extend);
    } 

    /**
     * 第三方游戏删除
     */
    public function small_del($model = null, $ids=null){
        $model = M('Model')->getByName('smallGame'); /*通过Model名称获取Model完整信息*/
        parent::remove($model["id"],'Set',$ids);
    }

    /**
     * [公众号列表]
     * @param int $p
     * @author 郭家屯[gjt]
     */
    public function wechat($p=1){
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $row = C('LIST_ROWS') == 0 ? 10 : C('LIST_ROWS') ;
        $list_data = M('wechat','tab_')
            ->field('id,name,appid,secret,create_time')
            ->page($page,$row)
            ->select();
        $count = M('wechat','tab_')->field('id')->count();
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $this->assign('_page', $page->show());
        }
        $this->assign('list_data',$list_data);
        $this->meta_title = '公众号列表';
        $this->display();
    }

    /**
     * [添加公众号]
     * @author 郭家屯[gjt]
     */
    public function wechat_add(){
        if(IS_POST){
            $param = I('post.');
            if(empty($param['name']))$this->error("请输入公众号名称");
            if(empty($param['appid']))$this->error("请输入公众号appid");
            if(empty($param['secret']))$this->error("请输入公众号secret");
            if(empty($param['token']))$this->error("请输入公众号token");
            if(empty($param['key']))$this->error("请输入公众号key");
            $param['create_time'] = time();
            $result = M('wechat','tab_')->add($param);
            if($result){
                action_log('wechat_add','game',$param['name'],UID);
                $this->success('添加成功',U('wechat'));
            }else{
                $this->error("添加失败");
            }
        }
        $this->meta_title = '添加公众号';
        $this->display();
    }

    /**
     * [修改公众号]
     * @author 郭家屯[gjt]
     */
    public function wechat_edit($id=0){
        if(IS_POST){
            $param = I('post.');
            if(empty($param['id']))$this->error("参数错误");
            if(empty($param['name']))$this->error("请输入公众号名称");
            if(empty($param['appid']))$this->error("请输入公众号appid");
            if(empty($param['secret']))$this->error("请输入公众号secret");
            if(empty($param['token']))$this->error("请输入公众号token");
            if(empty($param['key']))$this->error("请输入公众号key");
            $result = M('wechat','tab_')->save($param);
            if($result){
                action_log('wechat_edit','game',$param['name'],UID);
                $this->success('修改成功',U('wechat'));
            }else{
                $this->error("修改失败");
            }
        }
        if(empty($id))$this->error('参数错误');
        $data = M('wechat','tab_')->where(['id'=>$id])->find();
        if(!$data)$this->error('数据不存在');
        $this->assign('data',$data);
        $this->meta_title = '修改公众号信息';
        $this->display();
    }

    /**
     * [删除公众号]
     * @param null $model
     * @param null $ids
     * @author 郭家屯[gjt]
     */
    public function wechat_del($ids=null){
        if(empty($ids))$this->error("请选择需要操作的数据");
        $model = M('wechat','tab_');
        if(!is_array($ids)){
            $wechat = $model->where(['id'=>$ids])->getField('name');
            action_log('wechat_del','game',$wechat['name'],UID);
            if($wechat){
                $result = $model->delete($ids);
            }else{
                $this->error('数据不存在');
            }
        }else{
            foreach ($ids as $key=>$vo){
                $result = $model->delete($vo);
            }
            action_log('wechat_del_batch','game',UID,UID);
        }
        if($result){
            $this->success('删除成功',U('wechat'));
        }else{
            $this->success('删除失败');
        }

    }
    public function add(){

        if(IS_POST){
            $_POST['recommend_status']=implode(',',$_POST['recommend_status']);
            $_POST['for_platform']= $_POST['for_platform'] ? implode(',',$_POST['for_platform']) : '';
            $_POST['introduction']=str_replace(array("\r\n", "\r", "\n"), "~~", $_POST['introduction']);
            $game   =   D(self::model_name);//M('$this->$model_name','tab_');
            $_POST['discount'] ==''?$_POST['discount'] = 10:$_POST['discount'];
            $_POST['developers']       = trim($_POST['developers']);
            $_POST['login_notify_url'] = trim($_POST['login_notify_url']);
            $_POST['pay_notify_url']   = trim($_POST['pay_notify_url']);
            $_POST['game_key']         = trim($_POST['game_key']);
            $_POST['game_pay_appid']   = trim($_POST['game_appid']);
            $_POST['relation_game_name'] = $_POST['game_name'];
            $_POST['sdk_version']   = 3;//H5游戏
            if($_POST['game_type_id']==''){
                $_POST['game_type_name']='';
            }
            $pinyin = new \Think\Pinyin();
            $num=mb_strlen($_POST['game_name'],'UTF8');
            $short = '';
            for ($i=0; $i <$num ; $i++) { 
                $str=mb_substr( $_POST['game_name'], $i, $i+1, 'UTF8');
                $short.=$pinyin->getFirstChar($str);
            }
            $_POST['short']=$short;
            $res = $game->update($_POST);  
            if(!$res){
                $this->error($game->getError());
            }else{
                action_log('add_game','game',$_POST['game_name'],UID);
                $this->success($res['id']?'更新成功':'新增成功',U('lists'));
            }
        }
        else{
            $this->meta_title = '新增游戏';
            $this->display();
        }
        
    }

    public function edit($id=null){
        if(IS_POST){
            $_POST['recommend_status']=implode(',',$_POST['recommend_status']);
            $_POST['for_platform']= $_POST['for_platform'] ? implode(',',$_POST['for_platform']) : '';
            $_POST['introduction']=str_replace(array("\r\n", "\r", "\n"), "~~", $_POST['introduction']);
            $game   =   D(self::model_name);//M('$this->$model_name','tab_');
            $_POST['developers']       = trim($_POST['developers']);
            $_POST['discount'] ==''?$_POST['discount'] = 10:$_POST['discount'];
            $_POST['login_notify_url'] = trim($_POST['login_notify_url']);
            $_POST['pay_notify_url']   = trim($_POST['pay_notify_url']);
            $_POST['game_key']         = trim($_POST['game_key']);
            $_POST['game_pay_appid']   = trim($_POST['game_pay_appid']);   
            $_POST['relation_game_name'] = $_POST['game_name'];
            if($_POST['game_type_id']==''){
                $_POST['game_type_name']='';
            }
            $pinyin = new \Think\Pinyin();
            $num=mb_strlen($_POST['game_name'],'UTF8');
            $short = '';
            for ($i=0; $i <$num ; $i++) { 
                $str=mb_substr( $_POST['game_name'], $i, $i+1, 'UTF8'); 
                $short.=$pinyin->getFirstChar($str);  
            }
            $_POST['short']=$short;
            $game_id['game_id'] = $_POST['id'];
            $game_name['game_name'] = $_POST['game_name'];
            $reslut = M('rebate','tab_')
            ->where($game_id)
            ->data($game_name)
            ->save();
            $reslut = M('spend','tab_')
            ->where($game_id)
            ->data($game_name)
            ->save();
            
            $res = $game->update();  
            if(!$res){
                $this->error($game->getError());
            }else{
                action_log('edit_game','game',$_POST['game_name'],UID);
                $this->success($res['id']?'更新成功':'新增成功',U('lists'));
            }
        }
        else{
            $id || $this->error('id不能为空');
            $data = D(self::model_name)->detail($id);
            $data || $this->error('数据不存在！');
            $data['recommend_status']=explode(',',$data['recommend_status']);
            $data['for_platform']=explode(',',$data['for_platform']);
            $this->assign('data', $data);
            $this->meta_title = '编辑游戏';
            $this->display();
        }
    }

    public function set_status($model='Game'){
        parent::set_status($model);
    }

    public function del($model = null, $ids=null){

        if(!is_array($ids)){
            $game_name = M('game','tab_')->where(['id'=>$ids])->getField('game_name');
            action_log('del_game','game',$game_name,UID);
        }else{
            action_log('del_game_batch','game',UID,UID);
        }

        $model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
        $ids = array_unique((array)I('request.ids',null));
        $del_map['game_id'] = ['in',$ids];
        M('giftbag','tab_')->where($del_map)->delete();
        M('server','tab_')->where($del_map)->delete();
        parent::remove($model["id"],'Set',$ids);
    }
    //开放类型
    public function openlist(){
        $extend = array(
        );
        parent::lists("opentype",$_GET["p"],$extend);
    }
    //新增开放类型
    public function addopen(){
        if(IS_POST){
            $game=D("opentype");
        if($game->create()&&$game->add()){
            $this->success("添加成功",U('openlist'));
        }else{
            $this->error("添加失败",U('openlist'));
        }
        }else{
            $this->display();
        }
        
    }
    //编辑开放类型
    public function editopen($ids=null){
          $game=D("opentype");
        if(IS_POST){
        if($game->create()&&$game->save()){
             $this->success("修改成功",U('openlist'));
        }else{
           $this->error("修改失败",U('openlist'));
        }
        }else{  
         $map['id']=$ids;
            $date=$game->where($map)->find();
            $this->assign("data",$date);
            $this->display();
        }
    }
    //删除开放类型
    public function delopen($model = null, $ids=null){
       $model = M('Model')->getByName("opentype"); /*通过Model名称获取Model完整信息*/
        parent::del($model["id"],$ids);
    }
    /**
     * 文档排序
     * @author huajie <banhuajie@163.com>
     */
    public function sort(){
        //获取左边菜单$this->getMenus()
       
        if(IS_GET){
            $map['status'] = 1;
            $list = D('Game')->where($map)->field('id,game_name')->order('sort DESC, id DESC')->select();

            $this->assign('list', $list);
            $this->meta_title = '游戏排序';
            $this->display();
        }elseif (IS_POST){
            $ids = I('post.ids');
            $ids = array_reverse(explode(',', $ids));
            foreach ($ids as $key=>$value){
                $res = D('Game')->where(array('id'=>$value))->setField('sort', $key+1);
            }
            if($res !== false){
                $this->success('排序成功！');
            }else{
                $this->error('排序失败！');
            }
        }else{
            $this->error('非法请求！');
        }
    }

    public function chgculumn(){
        $res = D('Game')->chgculumn();
        if($res==1){
            $data['status'] = 1;
            $data['msg'] = '修改成功';
        }elseif($res==2){
            $data['status'] = 2;
            $data['msg'] = '缺少字段';
        }elseif($res==-1){
            $data['status'] = 0;
            $data['msg'] = '缺少字段';
        }else{
            $data['status'] = 0;
            $data['msg'] = '修改失败';
        }
        $this->ajaxReturn($data);
    }

    public function change_small_gmae_status(){
        $res = D('smallGame')->chgculumn();
        if($res==1){
            $data['status'] = 1;
            $data['msg'] = '修改成功';
        }elseif($res==2){
            $data['status'] = 2;
            $data['msg'] = '缺少字段';
        }elseif($res==-1){
            $data['status'] = 0;
            $data['msg'] = '缺少字段';
        }else{
            $data['status'] = 0;
            $data['msg'] = '修改失败';
        }
        $this->ajaxReturn($data);
    }

}
