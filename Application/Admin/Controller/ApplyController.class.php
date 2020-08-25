<?php

namespace Admin\Controller;
use Qiniu\Storage\UploadManager;
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
use Admin\Model\ApplyModel;
/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class ApplyController extends ThinkController {
    const model_name = 'Apply';

    public function lists(){
        if(isset($_REQUEST['game_name'])){
            if($_REQUEST['game_name']=='全部'){
                unset($_REQUEST['game_name']);
            }else{
                $map['game_id']=get_game_id($_REQUEST['game_name']);
                unset($_REQUEST['game_name']);
            }
        }
        if(isset($_REQUEST['promote_name'])){
            if($_REQUEST['promote_name']=='全部'){
                unset($_REQUEST['promote_name']);
            }else if($_REQUEST['promote_name']=='自然注册'){
                $map['promote_id']=array("elt",0);
                unset($_REQUEST['promote_name']);
            }else{
                $map['promote_id']=get_promote_id($_REQUEST['promote_name']);
                unset($_REQUEST['promote_name']);
            }
        }
        if(isset($_REQUEST['time-start'])&&isset($_REQUEST['time-end'])){
            $map['apply_time'] =array('BETWEEN',array(strtotime($_REQUEST['time-start']),strtotime($_REQUEST['time-end'])+24*60*60-1));
            unset($_REQUEST['time-start']);unset($_REQUEST['time-end']);
        }
        if(isset($_REQUEST['start'])&&isset($_REQUEST['end'])){
            $map['apply_time'] =array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));
            unset($_REQUEST['start']);unset($_REQUEST['end']);
        }
        $map['sdk_version'] = 3;
        $PROMOTE_URL_AUTO_AUDIT = C('PROMOTE_URL_AUTO_AUDIT');
        $this->assign('PROMOTE_URL_AUTO_AUDIT',$PROMOTE_URL_AUTO_AUDIT);

        parent::lists(self::model_name,$_GET["p"],$map);
    }

    public function edit($id=null){
        $id || $this->error('请选择要编辑的用户！');
        $model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
        parent::edit($model['id'],$id);
    }

    public function set_status($model='Apply'){
        if(I('get.msg_type')==5){
            action_log('audit_game_link','apply',UID,UID);
        }
        parent::set_status($model);
    }

    public function del($model = null, $ids=null){
        if(empty($ids))$this->error('请选择要操作的数据');
        $source = D(self::model_name);
        $id = array_unique((array)$ids);
        $map = array('id' => array('in', $id) );
        $list = $source->where($map)->select();
        foreach ($list as $key => $value) {
            $file_url = APP_ROOT.$value['pack_url'];
            unlink($file_url);
        }
        action_log('del_apply','apply',UID,UID);
        $model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
        parent::del($model["id"],$ids,"tab_");
    }
    /**
    *修改申请信息
    */
    public function updateinfo($id,$pack_url,$promote){
        $model = M('Apply',"tab_");
        $data['id'] = $id;
        $data['pack_url'] = $pack_url;
        $data['dow_url']  = '/index.php?s=/Home/Down/down_file/game_id/'.$promote['game_id'].'/promote_id/'.$promote['promote_id'];
        $data['dispose_id'] = UID;
        $data['dispose_time'] = NOW_TIME;
        $res = $model->save($data);
        return $res;
    }

    public function game_source($game_id,$type){
        $model = D('Source');
        $map['game_id'] = $game_id;
        $map['type'] = $type;
        $data = $model->where($map)->find();
        return $data;
    }

    /**
     * [修改推广链接自动审核]
     * @author 郭家屯[gjt]
     */
    public function change_pro_app_auto_audit(){
        if($_REQUEST['value']==1){
            $value = 0;
            action_log('close_game_auto_audit','config',UID,UID);
        }else{
            $value = 1;
            action_log('open_game_auto_audit','config',UID,UID);
        }
        $config['value'] = $value;
        $res = M('config')->where(array('name'=>'PROMOTE_URL_AUTO_AUDIT'))->save($config);
        S('DB_CONFIG_DATA',null);
        $this->ajaxReturn(array('status'=>1));
    }

    /**
     * [渠道APP自动申请状态修改]
     * @author 郭家屯[gjt]
     */
    public function change_pro_app_auto(){
        if($_REQUEST['value']==1){
            $value = 0;
            action_log('close_app_auto','config',UID,UID);
        }else{
            $value = 1;
            action_log('open_app_auto','config',UID,UID);
        }
        $config['value'] = $value;
        $res = M('config')->where(array('name'=>'PROMOTE_APP_AUTO'))->save($config);
        S('DB_CONFIG_DATA',null);
        $this->ajaxReturn(array('status'=>1));
    }

    /*
     * APP分包
     *   */
    public function app_lists($p=0)
    {
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $row = intval(C('LIST_ROWS')) ? :10;
        "" == I('status') || $map['status'] = I('status');
        "" == I('app_version') || $map['app_version'] = I('app_version');
        "" == I('promote_id') || $map['promote_id'] = I('promote_id');
        "" == I('enable_status') || $map['enable_status'] = I('enable_status');
    
        $data = M('app_apply','tab_')->where($map)->order('apply_time desc')->page($page,$row)->select();
        $count = M('app_apply','tab_')->where($map)->count();
        //分页
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%UP_PAGE% %FIRST% %LINK_PAGE% %END% %DOWN_PAGE% %HEADER%');
            $this->assign('_page', $page->show());
        }
        $PROMOTE_APP_AUTO = C('PROMOTE_APP_AUTO');
        $this->assign('PROMOTE_APP_AUTO',$PROMOTE_APP_AUTO);
        $this->assign('list_data',$data);
        $this->meta_title = 'APP分包';
        $this->display();
    }
    

    //app审核
    public function app_audit($ids=array(),$status=1){
        if(!$ids)$this->error('请选择要操作的数据');
        $map['id'] = ['in',$ids];
        $data['status'] = $status;
        $data['dispose_id'] = UID;
        $data['dispose_time'] = time();
        $res = M('app_apply','tab_')->where($map)->setField($data);
        if($res !== false) {
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }
    
    /*
     * APP分包打包
     *   */
    public function app_package($ids=null)
    {
        if(!$ids)$this->error('请选择要操作的数据');
        if(!is_array($ids)){
            $ids = [$ids];
        }

        try{
            $ids || $this->error("打包数据不存在",U('Apply/app_lists'),1);
            static $a=0;//无数据或没审核
            static $b=0;//原包不存在
            static $c=0;//成功
            static $d=0;//操作失败
            static $e=0;//分包失败
            foreach ($ids as $key => $value) {
                $apply_data = M('app_apply','tab_')->find($value);
                //验证数据正确性
                if (empty($apply_data) || $apply_data["status"] != 1) {
                    $a++;
                    continue;
                }
                #获取原包数据
                $source_file = M('app','tab_')->find($apply_data['app_id']);
                //验证原包是否存在
                if (empty($source_file) || !file_exists($source_file['file_url'])) {
                    $b++;
                    continue;
                }
                if($apply_data['app_version']==1){
                    $app_type=".apk";
                    $url_ver="META-INF/mch.properties";
                }else{
                    $app_type=".ipa";
                    $url_ver="Payload/H5App.app/_CodeSignature/mch.txt";
                }
                $newname = "app_package" . $apply_data["app_id"] . "-" . $apply_data['promote_id'] . $app_type;                
                $to = "./Uploads/GamePack/" . $newname;
                copy($source_file['file_url'], $to);
                #打包新路径
                $zip = new \ZipArchive;
                $res = $zip->open($to, \ZipArchive::CREATE);
                if ($res == TRUE) {
                    #打包数据
                    $pack_data = array(
                        "promote_id" => $apply_data['promote_id'],
                        "promote_account" => get_promote_name($apply_data["promote_id"]),
                    );
                    $zip->addFromString($url_ver, json_encode($pack_data));
                    $zip->close();
                    if (get_tool_status("oss_storage") == 1) {
                        $to = "http://" . C("oss_storage.bucket") . "." . C("oss_storage.domain") . "/GamePack/" . $newname;
                        $to = str_replace('-internal', '', $to);
                        $new_to = "./Uploads/GamePack/" . $newname;
                        $updata['savename'] = $newname;
                        $updata['path'] = $new_to;

                        $this->upload_game_pak_oss($updata);
                        @unlink($new_to);
                    }elseif(get_tool_status("qiniu_storage")==1){
                        $this->dleteQiNiuFile($newname);
                        $url = $this->upQiNiuFile($newname,$to);
                        @unlink ($to);
                        $to = "http://".$url;
                    }elseif(get_tool_status("cos_storage")==1){
                        $cos=A('Cos');
                        $cos->cosupload("","/App/".$newname,2);
                        $cos_res=$cos->cosupload($to,"/App/".$newname);
                        if(strlen($cos_res)>10){
                            @unlink ($to);
                            $to=$cos_res;
                           
                        }else{
                             $this->error("Cos参数错误");
                        }
                    }
                    if($apply_data['app_version']==0){
                       $plist_url = A('Plist')->create_plist_app('1',$apply_data['promote_id'],'app',$to);
                       $apply_data['plist_url'] = $plist_url;
                    }
                    $apply_data['dow_url'] = $to;
                    $apply_data['enable_status'] = 1;
                    $res = M('app_apply','tab_')->save($apply_data);
                    if ($res !== false) {
                        $c++;                    
                    } else {
                        $d++;
                    }
                } else {
                    $e++;
                }
            }
            $f=$a+$b+$d+$e;

            $this->success('成功'.$c.',失败'.$f);

        }
        catch(\Exception $e){
            $this->error($e->getMessage());
        }
    }
    /*
     * 删除APP分包申请
     *   */
    public function app_del($ids=null){
        if(empty($ids))$this->error('请选择要操作的数据');
        $map['id'] = ['in',$ids];
        $res = M('app_apply','tab_')->where($map)->delete();
        if($res !== false){
            action_log('app_del','appapply',UID,UID);
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }

    /**
     * 设置分成比例
     */
    public function setRatio(){

        $applyModel = new ApplyModel();
        $data['game_id'] = $_POST['game_id'];
        $data['promote_id'] = $_POST['promote_id'];
        $data['field'] = $_POST['field'];
        $data['value'] = $_POST['value'];
        if($applyModel->setRatio($data)){
            $this->success("修改成功");
        }else{
            $this->error("修改失败");
        }

    }
    public function sy_lists(){
        if(isset($_REQUEST['game_name'])){
            if($_REQUEST['game_name']=='全部'){
                unset($_REQUEST['game_name']);
            }else{
                $map['game_id']=get_game_id($_REQUEST['game_name']);
                unset($_REQUEST['game_name']);
            }
        }
                if (is_numeric($_REQUEST['game_id']) && $_REQUEST['game_id']>0) {
                    $map['game_id'] = $_REQUEST['game_id'];
                    unset($_REQUEST['game_id']);
                }
                if (is_numeric($_REQUEST['promote_id']) && $_REQUEST['promote_id']>0) {
                    $map['tab_apply.promote_id'] = $_REQUEST['promote_id'];
                    unset($_REQUEST['promote_id']);
                }
        if(isset($_REQUEST['promote_account'])){
           $map['account']=get_promote_id($_REQUEST['promote_account']);
            unset($_REQUEST['promote_account']);
        }
        if(isset($_REQUEST['status'])){
            if($_REQUEST['status']=='全部'){
                unset($_REQUEST['status']);
            }else{
                $map['status']=$_REQUEST['status'];
                unset($_REQUEST['status']);
            }
        }
        if($_REQUEST['enable_status'] != ''){
            if($_REQUEST['enable_status']=='全部'){
                unset($_REQUEST['enable_status']);
            }elseif($_REQUEST['enable_status']=='0'){
                //$map['dow_url'] = '';
                $map['enable_status'] = $_REQUEST['enable_status'];
                unset($_REQUEST['enable_status']);
            }else{
                //$map['dow_url']=array('neq','');
                $map['enable_status'] = $_REQUEST['enable_status'];
                unset($_REQUEST['enable_status']);
            }
        }
        if(isset($_REQUEST['dow_status'])){
            if($_REQUEST['dow_status']=='全部'){
                unset($_REQUEST['dow_status']);
            }else{
                $map['tab_apply.dow_status']=$_REQUEST['dow_status'];
                unset($_REQUEST['dow_status']);
            }
        }
        if(I('get.type',1) == 1){
            $map['tab_apply.sdk_version']= 1;
        }else{
            $map['tab_apply.sdk_version']= 2;
            $this->assign('show_status',1);
        }
        $applyModel = new ApplyModel();
        $fields = "tab_apply.*,tab_game.ratio as game_ratio,tab_game.money as game_money";
        $join = "LEFT JOIN tab_game ON tab_apply.game_id = tab_game.id";
        $order="id desc";
        $data = $applyModel->joinList($fields,$join,$map,$order);
        $this->meta_title = '游戏分包列表';
        $this->assign('_page',$data['page']);
        $this->assign('list_data',$data['data']);
                
        $this->m_title = '游戏分包';
        $this->assign('commonset',M('Kuaijieicon')->where(['url'=>'Apply/and_lists','status'=>1])->find());

        $PROMOTE_URL_AUTO_AUDIT = C('PROMOTE_URL_AUTO_AUDIT');
        $this->assign('PROMOTE_URL_AUTO_AUDIT',$PROMOTE_URL_AUTO_AUDIT);
        $this->display();
    }
    //打包
    public function package($ids=null)
    {
        $url = U('Apply/sy_lists',array('p'=>$_GET['p'],'type'=>$_GET['type'],'promote_id'=>$_GET['promote_id'],'game_id'=>$_GET['game_id'],'status'=>$_GET['status'],'dow_status'=>$_GET['dow_status'],'enable_status'=>$_GET['enable_status']));
        $ids || $this->error("打包数据不存在",$url);
        $apply_data = D('Apply')->find($ids);
        //验证数据正确性
        if(empty($apply_data) || $apply_data["status"] != 1){$this->error("未审核或数据错误",$url); exit();}
        #获取原包数据
        $source_file = $this->game_source($apply_data["game_id"],1);
        if(substr($source_file['file_url'] , 0 , 2)==".."){
            $source_file['file_url']=substr($source_file['file_url'],'1',strlen($source_file['file_url']));
        }
        //验证原包是否存在
        if(empty($source_file) || !file_exists($source_file['file_url'])){$this->error("游戏原包不存在",$url); exit();}
        M('apply','tab_')->where(['id'=>$ids])->setField('enable_status',2);
        $this->success("已加入打包队列,刷新此页面可查看当前打包状态",$url);exit;
       
    }
    //批量打包
    public function allpackage($ids=null,$p=1,$type=1)
    {
        $ids || $this->error("请选择要操作的数据",U('Apply/sy_lists',array('p'=>$p,'type'=>$type)),1);
        $successNum = 0; $errorNuem = 0;
        foreach ($ids as $key => $value) {
            $apply_data = M('apply','tab_')->find($value);
            $source_file = $this->game_source($apply_data["game_id"],1);
            if(substr($source_file['file_url'] , 0 , 2)==".."){
                $source_file['file_url']=substr($source_file['file_url'],'1',strlen($source_file['file_url']));
            }
            if(empty($apply_data) || $apply_data["status"] != 1){
                $errorNuem++; continue;
            }
            else if(empty($source_file) || !file_exists($source_file['file_url'])){//验证原包是否存在
               $errorNuem++; continue;
            }
            else{
                $map['id'] = $value;
                $map['status'] = 1;
                M('apply','tab_')->where($map)->setField('enable_status',2);
                $successNum++;
            }
        }
        $msg = "已加入打包队列，刷新此页面可查看当前打包状态;\r\n加入成功：".$successNum."个。"."失败：".$errorNuem."个";
        $this->success($msg,U('Apply/sy_lists',array('p'=>$p,'type'=>$type)));
        exit;
    }
    //七牛
    public function qiniu_ios_upload($promote_id, $game_id)
    {
        if (get_tool_status("qiniu_storage") == 1) {
            $map['channelid'] = $promote_id;
            $map['game_id'] = $game_id;
            $find = M('iospacket')->where($map)->find();
            if(file_exists("./Uploads/Ios/".$find['channelpath'])&&!empty($find['channelpath'])) {
                $newname = "game_package" . $find["game_id"] . "-" . $find['channelid'] . ".ipa";
                $to = "./Uploads/Ios/".$find['channelpath'];
                $this->dleteQiNiuFile($newname);
                $url = $this->upQiNiuFile($newname, $to);
                if (empty($url)) {
                    $this->error('七牛错误，请检查七牛配置，并确保七牛空间权限正确！');
                }
                unset($map['channelid']);
                $map['promote_id'] = $promote_id;
                $data['pack_url'] = $url;
                $result = M('apply', 'tab_')->where($map)->save($data);
                if ($result !== false) {
                    @unlink($to);
                    $this->AjaxReturn(['status' => 1, 'msg' => '上传成功']);
                } else {
                    $this->AjaxReturn(['status' => 0, 'msg' => '上传失败']);
                }
            }else{
                $this->AjaxReturn(['status'=>0,'msg'=>'文件不存在或已上传云空间']);
            }
        }else{
            $this->AjaxReturn(['status'=>0,'msg'=>'未开启七牛上传']);
        }
    }
    /**
    *上传到OSS
    */
    public function upload_game_pak_oss($return_data=null){
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
        // if(preg_match('/.apk/',$return_data['savename']) ){
              $oss_name="GamePack";
        // }else{
        //       $oss_name="IosGamePack";
        // }
        $oss_file_path =$oss_name."/". $return_data["savename"];

        $avatar = $return_data["path"];
        try {
            $this->multiuploadFile($ossClient,$bucket,$oss_file_path,$avatar);        
            return true;
        } catch (OssException $e) {
            /* 返回JSON数据 */
           $this->error($e->getMessage());
        }
    }

    /**
    *删除OSS
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
        $objectname="GamePack/".$objectname;
        

        $ossClient->deleteObject($bucket, $objectname);
       
    }

    /**
    *上传到BOS
    */
    public function upload_bos($return_data=null){
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
        $bos_file_path ="GamePack/". $return_data["savename"]; //在bos的路径
        $avatar = $return_data["path"];   
        try {
            
            $client->putObjectFromFile($bucket,$bos_file_path,$avatar);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

    }  


     /*
    删除bos的object
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
        $path ="GamePack/". $name;
       
       
        $client->deleteObject($bucket, $path);
            
        
    }
    public function multiuploadFile($ossClient, $bucket,$url,$file){
        //$file = __FILE__;
        $options = array();
        try{
            #初始化分片上传文件
            $uploadId = $ossClient->initiateMultipartUpload($bucket, $url);
            //$ossClient->multiuploadFile($bucket, $url, $file, $options);
        } catch(OssException $e) {
            printf(__FUNCTION__ . ": initiateMultipartUpload FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }
        /*
         * step 2. 上传分片
         */
        $partSize = 5 * 1000 * 1024;
        $uploadFile = $file;
        $uploadFileSize = filesize($uploadFile);
        $pieces = $ossClient->generateMultiuploadParts($uploadFileSize, $partSize);
        $responseUploadPart = array();
        $uploadPosition = 0;
        $isCheckMd5 = true;
        foreach ($pieces as $i => $piece) {
            $fromPos = $uploadPosition + (integer)$piece[$ossClient::OSS_SEEK_TO];
            $toPos = (integer)$piece[$ossClient::OSS_LENGTH] + $fromPos - 1;
            $upOptions = array(
                $ossClient::OSS_FILE_UPLOAD => $uploadFile,
                $ossClient::OSS_PART_NUM => ($i + 1),
                $ossClient::OSS_SEEK_TO => $fromPos,
                $ossClient::OSS_LENGTH => $toPos - $fromPos + 1,
                $ossClient::OSS_CHECK_MD5 => $isCheckMd5,
            );
            if ($isCheckMd5) {
                $contentMd5 = \OSS\Core\OssUtil::getMd5SumForFile($uploadFile, $fromPos, $toPos);
                $upOptions[$ossClient::OSS_CONTENT_MD5] = $contentMd5;
            }
            //2. 将每一分片上传到OSS
            try {
                $responseUploadPart[] = $ossClient->uploadPart($bucket, $url, $uploadId, $upOptions);
            } catch(OssException $e) {
                printf(__FUNCTION__ . ": initiateMultipartUpload, uploadPart - part#{$i} FAILED\n");
                printf($e->getMessage() . "\n");
                return;
            }
            //printf(__FUNCTION__ . ": initiateMultipartUpload, uploadPart - part#{$i} OK\n");
        }
        $uploadParts = array();
        foreach ($responseUploadPart as $i => $eTag) {
            $uploadParts[] = array(
                'PartNumber' => ($i + 1),
                'ETag' => $eTag,
            );
        }
        /**
         * step 3. 完成上传
         */
        try {
            $ossClient->completeMultipartUpload($bucket, $url, $uploadId, $uploadParts);
        }  catch(OssException $e) {
            printf(__FUNCTION__ . ": completeMultipartUpload FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }
    }

    /**
     * 获取七牛上传token
     */
    public function getQiNiuToken(){
        $this->dleteQiNiuFile($_REQUEST['key']);
        Vendor('Qiniu.autoload');
        $config = C('qiniu_storage');
        $accessKey = $config['AccessKey'];
        $secretKey = $config['SecretKey'];
        $Qiniu = new Auth($accessKey,$secretKey);
        $bucket = $config['bucket'];
        //定义上传后返回客户端的值
        $policy = array(
            'returnBody'  =>  '{"name":$(fname),"size":$(fsize),"key":$(key)}',
        );
        //生成上传token
        $result['uptoken'] = $Qiniu->uploadToken($bucket,null,3600,$policy);
        $this->ajaxReturn($result);
    }

    /**
     * 删除七牛空间文件
     * @param $key
     * @return mixed
     */

    public function dleteQiNiuFile($key){
        Vendor('Qiniu.autoload');
        $config = C('qiniu_storage');
        $accessKey = $config['AccessKey'];
        $secretKey = $config['SecretKey'];
        $auth  = new Auth($accessKey,$secretKey);
        //初始化BucketManager
        $bucketMgr = new BucketManager($auth);
        $bucket = C('qiniu_storage.bucket');
        $res = $bucketMgr->delete($bucket, $key);
        return $res;
    }

    /**
     * 七牛上传
     * @param $newName  上传到七牛的文件名称
     * @param $filePath 文件路径
     */
    public function upQiNiuFile($newName,$filePath){
        Vendor('Qiniu.autoload');
        //读取七牛配置
        $config = C('qiniu_storage');
        $accessKey = $config['AccessKey'];
        $secretKey = $config['SecretKey'];
        //实例化鉴权对象
        $auth  = new Auth($accessKey,$secretKey);
        $bucket = $config['bucket'];
        //生成token
        $token = $auth->uploadToken($bucket);
        //实例化上传类
        $uploadMgr = new UploadManager();
        //上传附件
        list($ret,$err) = $uploadMgr->putFile($token,$newName,$filePath);
        if($ret){
            return $url = $config['domain'].'/'.$newName;
        }else{
            return '';
        }
    }
}
