<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;
use OSS\OssClient;
use OSS\Core\OSsException;
use Qiniu\Storage\BucketManager;
use Qiniu\Auth;
use Think\Controller;
use BaiduBce\BceClientConfigOptions;
use BaiduBce\Util\Time;
use BaiduBce\Util\MimeTypes;
use BaiduBce\Http\HttpHeaders;
use BaiduBce\Services\Bos\BosClient;
use BaiduBce\Services\Bos\CannedAcl;
use BaiduBce\Services\Bos\BosOptions;
use BaiduBce\Auth\SignOptions;
use BaiduBce\Log\LogFactory;
/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class SyGameController extends ThinkController {
    //private $table_name="Game";
    const model_name = 'game';

    /**
    *游戏信息列表
    */
     public function lists(){
        if(isset($_REQUEST['game_name'])){
                $extend['game_name'] = $_REQUEST['game_name'];
                unset($_REQUEST['game_name']);
        }
        if(isset($_REQUEST['sdk_version'])){
            $extend['sdk_version']=$_REQUEST['sdk_version'];
            unset($_REQUEST['sdk_version']);
        }else{
            $extend['sdk_version']=['in',[1,2]];
        }
        if(isset($_REQUEST['recommend_status'])){
            $extend['recommend_status']=['like','%'.$_REQUEST['recommend_status'].'%'];
            unset($_REQUEST['recommend_status']);
        }
        if(isset($_REQUEST['game_status'])){
            $extend['game_status']=$_REQUEST['game_status'];
            unset($_REQUEST['game_status']);
        }
        if(isset($_REQUEST['apply_status'])){
            $extend['apply_status']=$_REQUEST['apply_status'];
            unset($_REQUEST['apply_status']);
        }
        if(isset($_REQUEST['online_status'])){
            $extend['online_status']=$_REQUEST['online_status'];
            unset($_REQUEST['online_status']);
        }
        $extend['order']='id desc,sort desc';
		$this->m_title = '游戏列表';
		$this->assign('commonset',M('Kuaijieicon')->where(['url'=>'Game/lists','status'=>1])->find());
         $GAME_AUTO_GUANLIAN = C('GAME_AUTO_GUANLIAN');
         $this->assign('GAME_AUTO_GUANLIAN',$GAME_AUTO_GUANLIAN);
        parent::lists(self::model_name,$_GET["p"],$extend);
    }


    public function get_game_set(){
        $map["game_id"] =$_REQUEST['game_id'];
        $find=M('game_set','tab_')->where($map)->find();
//        $find['mdaccess_key']=get_ss($find['access_key']);
        $find['mdaccess_key']=$find['access_key'];
        echo json_encode(array("status"=>1,"data"=>$find));
    }

    public function get_game_source(){
        $map["game_id"] =$_REQUEST['game_id'];
        $data = M('GameSource','tab_')->field('file_url')->where($map)->find();
        if(empty($data)){
            $result = array('status'=>0,'dow_url'=>'');
        }else{
            $result = array('status'=>1,'dow_url'=>$data['file_url']);
        }
        
        echo json_encode($result);

    }



    /**
    *游戏原包列表
    */
    public function source(){
        $extend = array('field_time'=>'create_time');
        parent::lists('Source',$_GET["p"],$extend);
    }

    /**
    *游戏更新列表
    */
    public function update(){
        parent::lists('Update',$_GET["p"]);
    }

    /**
    *添加游戏原包
    */
    public function add_source(){
        if(IS_POST){
            if(empty($_POST['game_id']) || empty($_POST['file_type'])){
                $this->error('游戏名称或类型不能为空');
            }
            $map['game_id']=$_POST['game_id'];
            $map['file_type'] = $_POST['file_type'];
            $d = D('Source')->where($map)->find();
            $source = A('Source','Event');
            if(empty($d)){
                $source->add_source();
            }
            else{
                $source->update_source($d['id']);
            }
        }
        else{

            $this->display();
        }
    }

    /**
    *删除原包
    */
    public function del_game_source($model = null, $ids=null){
        $source = D("Source");
        $id = array_unique((array)$ids);
        $map = array('id' => array('in', $id) );
        $list = $source->where($map)->select();
        foreach ($list as $key => $value) {
            $file_url = APP_ROOT.$value['file_url'];
            unlink($file_url);
        }
        $model = M('Model')->getByName("source"); /*通过Model名称获取Model完整信息*/
        parent::del($model["id"],$ids,"tab_game_");
    }

    public function add(){
        
    	if(IS_POST){
            $_POST['introduction']=str_replace(array("\r\n", "\r", "\n"), "~", $_POST['introduction']);
            if($_POST['game_name']==''){
                $this->error('游戏名称不能为空！');exit;
            }
            $_POST['relation_game_name']=$_POST['game_name'];
            if(strpos($_POST['game_name'],'(安卓版)')||strpos($_POST['game_name'],'(苹果版)')){
                $this->error('游戏名称格式错误，不能含有安卓版或苹果版字符！');exit;
            }
            if($_POST['down_port'] == 2){
                if(empty($_POST['add_game_address']) && empty($_POST['ios_game_address'])){
                    $this->error('请输入第三方下载地址');
                }
                if(!is_url($_POST['add_game_address']) && !is_url($_POST['ios_game_address'])){
                    $this->error('第三方下载地址格式错误');
                }
                if(empty($_POST['game_address_size'])){
                    $this->error('请输入原包大小');
                }
            }
            if($_POST['sdk_version']==1){
                unset($_POST['ios_game_address']);
                $_POST['game_name']=$_POST['game_name'].'(安卓版)';
            }else{
                unset($_POST['add_game_address']);
                $_POST['game_name']=$_POST['game_name'].'(苹果版)';
            }
            $pinyin = new \Think\Pinyin();
            $num=mb_strlen($_POST['game_name'],'UTF8');
            $short = '';
            for ($i=0; $i <$num ; $i++) { 
                $str=mb_substr( $_POST['game_name'], $i, $i+1, 'UTF8'); 
                $short.=$pinyin->getFirstChar($str);  
            }
            $_POST['material_url'] = $_POST['file_url'].$_POST['file_name'];
            $_POST['discount'] ==''?$_POST['discount'] = 10:$_POST['discount'];
            $_POST['bind_recharge_discount'] ==''?$_POST['bind_recharge_discount'] = 10:$_POST['bind_recharge_discount'];
            $_POST['short']=$short;
            $_POST['game_pay_appid']=$_POST['game_appid'];
            $_POST['recommend_status']=implode(',',$_POST['recommend_status']);
            $_POST['for_platform']= $_POST['for_platform'] ? implode(',',$_POST['for_platform']) : '';
            $game   =   D(self::model_name);
	        $res = $game->sy_update($_POST);
	        if(!$res){
	            $this->error($game->getError());
	        }else{
	            if(empty($res['id']) && C('GAME_AUTO_GUANLIAN')){
                    if($_POST['sdk_version']==2){
                        if($_POST['dow_prot'] == 0) {unset($_POST['add_game_address']);}
                        $_POST['game_name']=$_POST['relation_game_name'].'(安卓版)';
                        $_POST['sdk_version'] = 1;
                    }else{
                        if($_POST['dow_prot'] == 0) {unset($_POST['ios_game_address']);}
                        $_POST['game_name']=$_POST['relation_game_name'].'(苹果版)';
                        $_POST['sdk_version'] = 2;
                    }
                    $_POST['game_key'] = generate_game_server_key(32);
                    $_POST['access_key'] = generate_game_client_key(16);
                    $_POST['relation_game_id'] = $res['relation_game_id'];
                    $_POST['marking'] = generate_game_appid(1);
                    $_POST['relationAll'] = 1;
                    $game->sy_update($_POST);
                }
	            $this->success($res['id']?'更新成功':'新增成功',U('lists'));
	        }
    	}else{
            if($_GET['type'] == 1 || $_GET['type'] == ""){
                $show_status = 1;$this->assign('show_status',$show_status);
            }
            $this->meta_title = '新增游戏';
						
            $this->m_title = '游戏列表';
            $this->assign('commonset',M('Kuaijieicon')->where(['url'=>'Game/lists','status'=>1])->find());
						
            $this->display();
    	}
    }

    public function relation(){
        if(IS_POST){
            if($_POST['game_name']==''){
                $this->error('游戏名称不能为空！');exit;
            }
            $_POST['introduction']=str_replace(array("\r\n", "\r", "\n"), "~", $_POST['introduction']);
            $_POST['recommend_status']=implode(',',$_POST['recommend_status']);
            $_POST['discount'] ==''?$_POST['discount'] = 10:$_POST['discount'];
            $_POST['bind_recharge_discount'] ==''?$_POST['bind_recharge_discount'] = 10:$_POST['bind_recharge_discount'];
            $_POST['relation_game_name']=$_POST['game_name'];
            if($_POST['sdk_version']==1){
				if($_POST['dow_prot'] == 0) {unset($_POST['add_game_address']);}
                $_POST['game_name']=$_POST['game_name'].'(安卓版)';
            }else{
				if($_POST['dow_prot'] == 0) {unset($_POST['ios_game_address']);}
                $_POST['game_name']=$_POST['game_name'].'(苹果版)';
            }
            $pinyin = new \Think\Pinyin();
            $num=mb_strlen($_POST['game_name'],'UTF8');
            for ($i=0; $i <$num ; $i++) { 
                $str=mb_substr( $_POST['game_name'], $i, $i+1, 'UTF8'); 
                $short.=$pinyin->getFirstChar($str);  
            }
            $_POST['short']=$short;
            $_POST['game_pay_appid']=$_POST['game_appid'];
            $game   =   D(self::model_name);//M('$this->$model_name','tab_');
            $res = $game->sy_update();  
            if(!$res){
                $this->error($game->getError());
            }else{
                $this->success($res['id']?'更新成功':'新增成功',U('lists'));
            }
        }else{
            $_REQUEST['id'] || $this->error('id不能为空');
            $map['relation_game_id']=$_REQUEST['id'];
            $map['id']=$_REQUEST['id'];
            $map1=$map;
            $map1['id']=array('neq',$_REQUEST['id']);
            $inv=D(self::model_name)->where($map)->find();
            $invalid=D(self::model_name)->where($map1)->find();
            if($invalid||$inv==''){
               $this->error('关联数据错误'); 
            }
            $suffix = $inv['sdk_version'] == 1?"PGB":"AZB";
            $inv['short'] = substr($inv['short'],0,-3).$suffix;
            $inv['recommend_status']=explode(',',$inv['recommend_status']);
            $this->assign('data',$inv);
            $this->meta_title = '关联游戏';
            $this->display();
        }
    }
    public function relationAll(){
        $game   =   D(self::model_name);//M('$this->$model_name','tab_');
        $related = $game->field('id,relation_game_id,COUNT(relation_game_id) as num')->group('relation_game_id')->having('num>1')->select();
        $related_id = implode(',', array_column($related, 'relation_game_id'));
        $no_related = $game->field('game_name,sort,game_type_id,game_type_name,game_score,features,recommend_level,apply_status,icon,game_detail_cover,cover,screenshot,introduction,dow_num,recommend_status,pay_status,dow_status,game_status,sdk_version,category,relation_game_id,relation_game_name,material_url')->where(['relation_game_id'=>['not in',$related_id],'sdk_version'=>['neq',3]])->select();
        static $ok = 0;
        foreach ($no_related as $key => &$value) {
            if($value['sdk_version']==2){
                if($value['dow_prot'] == 0) {unset($value['add_game_address']);}
                $value['game_name']=$value['relation_game_name'].'(安卓版)';
                $value['sdk_version'] = 1;
            }else{
                if($value['dow_prot'] == 0) {unset($value['ios_game_address']);}
                $value['game_name']=$value['relation_game_name'].'(苹果版)';
                $value['sdk_version'] = 2;
            }
            $pinyin = new \Think\Pinyin();
            $num=mb_strlen($value['game_name'],'UTF8');
            for ($i=0; $i <$num ; $i++) { 
                $str=mb_substr( $value['game_name'], $i, $i+1, 'UTF8'); 
                $short.=$pinyin->getFirstChar($str);  
            }
            $value['short']=$short;
            $value['game_appid'] = generate_game_appid();
            $value['game_pay_appid']=$value['game_appid'];
            $value['marking'] = generate_game_appid(1);
            $value['relationAll'] = 1;
            $res = $game->sy_update($value); 
            ++$ok; 
        }
        $this->ajaxReturn(['status'=>1,'info'=>'已自动关联'.$ok.'个']);
    }
    public function edit($id = null)
    {
        if (IS_POST) {
            if($_POST['down_port'] == 2){
                if(empty($_POST['add_game_address']) && empty($_POST['ios_game_address'])){
                    $this->error('请输入第三方下载地址');
                }
                if(!is_url($_POST['add_game_address']) && !is_url($_POST['ios_game_address'])){
                    $this->error('第三方下载地址格式错误');
                }
                if(empty($_POST['game_address_size'])){
                    $this->error('请输入原包大小');
                }
            }else{
                $_POST['add_game_address'] = '';
                $_POST['ios_game_address'] = '';
                $_POST['game_address_size'] = '';
            }
            $_POST['introduction']=str_replace(array("\r\n", "\r", "\n"), "~", $_POST['introduction']);
            $game = D(self::model_name);
            $_POST['discount'] ==''?$_POST['discount'] = 10:$_POST['discount'];
            $_POST['bind_recharge_discount'] ==''?$_POST['bind_recharge_discount'] = 10:$_POST['bind_recharge_discount'];
            $_POST['recommend_status']=implode(',',$_POST['recommend_status']);
            $_POST['for_platform']= $_POST['for_platform'] ? implode(',',$_POST['for_platform']) : '';
            $_POST['game_key'] = ($_POST['game_key'] ? $_POST['game_key'] : generate_game_server_key(32));
            $_POST['access_key'] = ($_POST['access_key'] ? $_POST['access_key'] : generate_game_client_key(16));
//            $game->startTrans();
            $res = $game->sy_update($_POST);
            //双版本公用参数
            $id = $res["id"];
            $sibling = D("Game")->find($id);
            $map['relation_game_id'] = $sibling['relation_game_id'];
            $sid=$sibling['id'];
            $map['id'] = array('neq',$sid);
            $another=D("Game")->field('id')->where($map)->find();  //获取另一个所有
            $phone['game_type_id'] =$sibling['game_type_id'];
            $phone['dow_num'] = $sibling['dow_num'];
            $phone['game_type_name'] =$sibling['game_type_name'];
            $phone['category']=$sibling['category'] ;
            $phone['recommend_status']= $sibling['recommend_status'];
            //$phone['game_status']= $sibling['game_status'];
            $phone['sort']= $sibling['sort'];
            $phone['game_score']=$sibling['game_score'] ;
            $phone['features']= $sibling['features'];
            $phone['introduction']= $sibling['introduction'];
            $phone['icon']= $sibling['icon'];
            $phone['cover']= $sibling['cover'];
            $phone['screenshot']=$sibling['screenshot'] ;
            $phone['material_url']=$sibling['material_url'];
            $phone['for_platform'] = $sibling['for_platform'];
            M('Game','tab_')->data($phone)->where(array('id'=>$another['id']))->save();
            //同时修改代充游戏折扣
            $set_fidel['status']   = 1;
            $set_fidel['game_id']  = $id;
            $set_fidel['discount'] = $_POST['discount'];
            $promoteModel = new \Admin\Model\PromoteWelfareModel();
            $promoteModel->set_game_discount($set_fidel);
            if(!$res){
                $this->error($game->getError());
            } else {
                $this->success($res['id'] ? '更新成功' : '新增成功', U('lists',array('type'=>I('post.type'),'p'=>I('request.p'))));
            }

        } else {
            $id || $this->error('id不能为空');
            $data = D(self::model_name)->detailback($id);
            $data || $this->error('数据不存在！');
            if (!empty($data['and_dow_address'])) {
                $data['and_dow_address'] = ltrim($data['and_dow_address'], '.');
            }
            if (!empty($data['ios_dow_address'])) {
                $data['ios_dow_address'] = ltrim($data['ios_dow_address'], '.');
            }
            if($_GET['type'] != 2){
                $this->assign('show_status',1);
            }
            $data['recommend_status']=explode(',',$data['recommend_status']);
            $data['for_platform']=explode(',',$data['for_platform']);
            $this->assign('data', $data);
            $this->meta_title = '编辑游戏';
            $this->m_title = '游戏列表';
            $this->assign('commonset',M('Kuaijieicon')->where(['url'=>'Game/lists','status'=>1])->find());
            $this->display();
        }
    }

    public function set_status($model='Game'){
        parent::set_status($model);
    }

    public function del($model = null, $ids=null){
        $model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
        $model = M('Model')->find($model["id"]);
        $model || $this->error('模型不存在！');
        $ids = array_unique((array)I('request.ids',null));
        if ( empty($ids) ) {
            $this->error('请选择要操作的数据!');
        }
        foreach ($ids as $key => $value) {
           $id=$value;
           $gda=M('Game','tab_')->where(array('id'=>$id))->find();
           $map['id']=array('neq',$id);
           $map['relation_game_id']=$gda['relation_game_id'];
           $anogame=M('Game','tab_')->where($map)->find();
           if($anogame){
                M('Game','tab_')->where($map)->data(array('relation_game_id'=>$anogame['id']))->save();
           }
           
           // $pic = M('Picture')->find($gda['icon']);
           // $count=M('Game','tab_')->where(array('icon'=>$gda['icon']))->count();//统计icon是否为多个游戏的图标
           // if($pic!='' && $count ==1){  //只有一个游戏指向这个图标 
           //      if($pic['oss_url']!=''){
           //          $this -> del_oss($gda['icon']);   //删除oss里图片
           //      }elseif($pic['bos_url']!=''){
           //          $this ->del_bos($gda['icon']); //删除bos里的图片
           //      }
           //      unlink('.'.$pic['path']);  //删除图片
           //      M('Picture')->where(array('id'=>$gda['icon']))->delete();
           // }


           $gs= M('GameSource','tab_')->where(array('game_id'=>$id))->find();
           if($gs){
                unlink($gs['file_url']);   //删除原包
                M('GameSource','tab_')->where(array('game_id'=>$id))->delete();
           }

           $apply=M('apply','tab_')->where(array('game_id'=>$id))->find();
           if($apply){
                if(substr($apply['pack_url'],0,4)=='http'){
                    if(strpos($apply['pack_url'],'bcebos')!== false){  //$value['pack_url']这个字符串是否包含'bcebos'.'bcebos'可以判断是否为bos存储
                        $objectname=basename($apply['pack_url']);
                        $this->delete_bos($objectname);   //删除bos里的原包
                    }elseif(strpos($apply['pack_url'],'oss')!== false){
                        $objectname=basename($apply['pack_url']);
                        $this->delete_oss($objectname);
                    }
                }

                $file_url = "./Uploads/GamePack/".basename($apply['pack_url']);//删除本地原包
                unlink($file_url);
                M('Apply','tab_')->where(array('game_id'=>$id))->delete();
           }


        }
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

    /*
    删除oss里的object   
    param  id   图片id
     */
    public function del_oss($id){
        $data=M('picture')->where("id=$id")->find();
        if(!empty($data)){
            if(!empty($data['oss_url'])){
                $objectname= basename($data['oss_url']);  //返回路径中的文件名部分(带后缀)
                $oss = A('Admin/Oss');
                $res = $oss ->delete_game_pak_oss($objectname);
            }
            
        }
    }

    /**
    *删除OSS原包
    * param name 原包名
    */
    public function delete_oss($objectname){
         /**
        * 根据Config配置，得到一个OssClient实例
        */
        try {
            Vendor('OSS.autoload');
            $ossClient = new \OSS\OssClient(C("oss_storage.accesskeyid"), C("oss_storage.accesskeysecr"), C("oss_storage.domain"));
        } catch (OssException $e) {
            $this->error($e->getMessage());
        }
        $bucket = C('oss_storage.bucket');
        $objectname="GamePak/".$objectname;
        try {

            $ossClient->deleteObject($bucket, $objectname);
        return true;
        } catch (OssException $e) {
            /* 返回JSON数据 */
           $this->error($e->getMessage());
        }
    }


    /*
    删除bos里的object   
    param  id   图片id
     */
    public function del_bos($id){
        $data=M('picture')->where("id=$id")->find();
        if(!empty($data)){
            if(!empty($data['bos_url'])){
                $objectname= basename($data['bos_url']); //返回路径中的文件名部分(带后缀)
                $oss = A('Admin/Bos');
                $res = $oss ->delete_bos($objectname);
            }
            
        }
        
    }
    /*
    删除bos的原包
    param name 原包名
     */
    public function delete_bos($name){
         /**
        * 根据Config配置，得到一个OssClient实例
        */
        try {
            $BOS_TEST_CONFIG =
            array(
                'credentials' => array(
                    'accessKeyId' => C("bos_storage.AccessKey"),
                    'secretAccessKey' => C("bos_storage.SecretKey"),
                ),
                'endpoint' => C("bos_storage.domain"),
            );
            require VENDOR_PATH.'BOS/BaiduBce.phar';
            $client = new BosClient($BOS_TEST_CONFIG);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

        $bucket = C('bos_storage.bucket');
        //$path ="icon/". $name; //在bos的路径
        $path ="GamePak/". $name;
       
        
        $client->deleteObject($bucket, $path);
    }

    /**
     * 商务专员状态修改
     * @author 小纯洁
     */
    public function changeStatus($field = null,$value=null)
    {
        $id = array_unique((array)I('ids', 0));
        $id = is_array($id) ? implode(',', $id) : $id;
        if (empty($id)) {
            $this->error('请选择要操作的数据!');
        }
        if($field == "online_status"){
            if($_REQUEST['apply_status'] == 0){
                $this->error('未审核不能设置为上线状态');
            }
        }
        $map['id'] = array('in', $id);
        $result = M('game','tab_')->where($map)->setField($field,$value);
        $msg = "操作";
        if($result !== false){
            $this->success($msg.'成功');
        }else{
            $this->error($msg.'失败');
        }
    }

    public function game_h5_show(){
        $qrcode_path ='http://'.$_SERVER['HTTP_HOST']. __ROOT__ . '/Public/' . MODULE_NAME . '/images'."/game_h5_show/admin123.png";
        $content = "http://www.baidu.com";
        $matrixPointSize = 4;
        $matrixMarginSize = 4;
        $errorCorrectionLevel = 3;
        $url = false;
        // /$url = __ROOT__ . '/Public/' . MODULE_NAME . '/images'."/game_h5_show/admin123.png";
        $url = $this->makecode($qrcode_path,$content,$matrixPointSize,$matrixMarginSize,$errorCorrectionLevel,$url);
        $this->assign('url',$url);
        $this->display();
    }

    function makecode($qrcode_path,$content,$matrixPointSize,$matrixMarginSize,$errorCorrectionLevel,$url){
        /**     参数详情：
         *      $qrcode_path:logo地址
         *      $content:需要生成二维码的内容
         *      $matrixPointSize:二维码尺寸大小
         *      $matrixMarginSize:生成二维码的边距
         *      $errorCorrectionLevel:容错级别
         *      $url:生成的带logo的二维码地址
         * */
        ob_clean ();
        Vendor('phpqrcode.phpqrcode');
        $object = new \QRcode();
        $qrcode_path_new = './Public/Admin/images/QRcode/code'.'_'.date("Ymdhis").'.png';//定义生成二维码的路径及名称
        //$filename = $pathname . "/qrcode_" . time() . ".png";
        $object::png($content,$qrcode_path_new, $errorCorrectionLevel, $matrixPointSize, $matrixMarginSize);
        $QR = imagecreatefromstring(file_get_contents($qrcode_path_new));//imagecreatefromstring:创建一个图像资源从字符串中的图像流

        $logo = imagecreatefromstring(file_get_contents($qrcode_path));
        $QR_width = imagesx($QR);// 获取图像宽度函数
        $QR_height = imagesy($QR);//获取图像高度函数
        $logo_width = imagesx($logo);// 获取图像宽度函数
        $logo_height = imagesy($logo);//获取图像高度函数
        $logo_qr_width = $QR_width / 4;//logo的宽度
        $scale = $logo_width / $logo_qr_width;//计算比例
        $logo_qr_height = $logo_height / $scale;//计算logo高度
        $from_width = ($QR_width - $logo_qr_width) / 2;//规定logo的坐标位置
        imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);
        /**     imagecopyresampled ( resource $dst_image , resource $src_image , int $dst_x , int $dst_y , int $src_x , int $src_y , int $dst_w , int $dst_h , int $src_w , int $src_h )
         *      参数详情：
         *      $dst_image:目标图象连接资源。
         *      $src_image:源图象连接资源。
         *      $dst_x:目标 X 坐标点。
         *      $dst_y:目标 Y 坐标点。
         *      $src_x:源的 X 坐标点。
         *      $src_y:源的 Y 坐标点。
         *      $dst_w:目标宽度。
         *      $dst_h:目标高度。
         *      $src_w:源图象的宽度。
         *      $src_h:源图象的高度。
         * */
        Header("Content-type: image/png");
        //$url:定义生成带logo的二维码的地址及名称
        imagepng($QR,$qrcode_path_new);
        return $qrcode_path_new;
    }

    /**
     * [修改自动关联状态]
     * @author 郭家屯[gjt]
     */
    public function change_auto_game(){
        if($_REQUEST['value']==1){
            $value = 0;
            action_log('close_GAME_AUTO_GUANLIAN','config',UID,UID);
        }else{
            $value = 1;
            action_log('open_GAME_AUTO_GUANLIAN','config',UID,UID);
        }
        $config['value'] = $value;
        $res = M('config')->where(array('name'=>'GAME_AUTO_GUANLIAN'))->save($config);
        S('DB_CONFIG_DATA',null);
        $this->ajaxReturn(array('status'=>1));
    }
    public function gameQrcodeIme(){
        

    }

}
