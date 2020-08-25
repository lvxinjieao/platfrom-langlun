<?php

namespace Home\Controller;
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
use Think\Think;
/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class PackageController extends Controller {


     protected function _initialize(){
        C(api('Config/lists'));
    }



    public function package()
    {
        $map['id']=$_POST['applyid'];
        $zip = new \ZipArchive();
        
        $find_web_stie=M('config')->field('value')->where(['name'=>'WEB_SITE'])->find();        
        if(null==$find_web_stie){
            \Think\Log::record('请先到站点->网站设置->网站域名处设置官网域名');exit;
        }
        $apply_data=M('apply','tab_')->field('id,game_id,game_name,promote_id,promote_account,sdk_version')->where($map)->select();
        foreach ($apply_data as $key => $value) {
            $game_so=M('Game_source','tab_')->field('id')->where(['game_id'=>$value['game_id']])->find();

            if(null==$game_so){
                M('apply','tab_')->where(['id'=>$value['id']])->setField('enable_status',-1);
                $this->ajaxReturn(['status'=>0,'msg'=>'原包不存在']);
                  continue;
            }
            M('apply','tab_')->where(['id'=>$value['id']])->setField('enable_status',3);
          if($value['sdk_version']==1){
                $str_ver=".apk";
                $file_name="GamePack";
                $url_ver="META-INF/mch.properties";
          }else{
                $zip_open_game_source = zip_open(get_game_source_file_url($value['game_id']));
                if ($zip_open_game_source) {
                while ($zip_entry = zip_read($zip_open_game_source)) {
                if(preg_match("/.app/", zip_entry_name($zip_entry))){
                   $ios_app=substr(zip_entry_name($zip_entry),8) ."<br/>";
                }
                $new_ios_1=explode("/", $ios_app);
                    }
                 }
                $str_ver=".ipa";
                $file_name="IosGamePack";
                $url_ver="Payload/".$new_ios_1[0]."/_CodeSignature/MCHChannelID";

          }
           $new_name = "game_package" . $value['game_id'] . "-" . $value['promote_id'] . $str_ver;
            $to = "./Uploads/".$file_name."/" . $new_name;
            copy(get_game_source_file_url($value['game_id']), ROOTTT.ltrim($to,'./'));
            $zip_res = $zip->open(ROOTTT.ltrim($to,'./'), \ZipArchive::CREATE);

            if ($zip_res == TRUE) {
                #打包数据
                $pack_data = array( 
                    "game_id" => $value["game_id"],
                    "game_name" => $value['game_name'],
                    "game_appid" => get_game_appid($value["game_id"], "id"),
                    "promote_id" => $value['promote_id'],
                    "promote_account" =>$value['promote_account'],
                );
                $zip->addFromString($url_ver, json_encode($pack_data));
                $zip->close();

                if(get_tool_status("oss_storage")==1){
                    $newname = "game_package" .$value["game_id"]."-".$value['promote_id'].$str_ver;
                    $to = "https://".C("oss_storage.bucket").".".C("oss_storage.domain")."/".str_replace("IosGamePack","GamePack",$file_name)."/".$newname;
                    $to = str_replace('-internal', '', $to);
                    if(!empty(C('oss_storage.bd_domain'))&&strlen(C('oss_storage.bd_domain'))>5){
                    $to = C('oss_storage.bd_domain')."/GamePack/".$newname;
                    }
                    $new_to=ROOTTT."Uploads/".$file_name."/".$newname;
                    $updata['savename'] = $newname;
                    $updata['path'] = $new_to;    
                    $this->upload_game_pak_oss($updata);
                    @unlink ($new_to);

                }elseif(get_tool_status("qiniu_storage")==1){
                    $newname = "game_package" .$value["game_id"]."-".$value['promote_id'].$str_ver;
                    $this->dleteQiNiuFile($newname);
                    $url = $this->upQiNiuFile($newname,ROOTTT.ltrim($to,'./'));
                    if(empty($url)){
                        $this->error('七牛错误，请检查七牛配置，并确保七牛空间权限正确！');
                    }
                    @unlink (ROOTTT.ltrim($to,'./'));
                    $to = "http://".$url;

                }elseif(get_tool_status("cos_storage")==1){
                    $cos=A('Cos');
                    $cos->cosupload("","/".$file_name."/".$newname,2);
                    $cos_res=$cos->cosupload(ROOTTT.ltrim($to,'./'),"/".$file_name."/".$newname);
                    if(strlen($cos_res)>10){
                        @unlink (ROOTTT.ltrim($to,'./'));
                        $to=$cos_res;

                    }else{
                        $this->error("Cos参数错误",U('ios_lists'));
                    }
                }elseif(get_tool_status("bos_storage")==1){
                    $newname = "game_package" .$value["game_id"]."-".$value['promote_id'].$str_ver;
                    $to = "http://".C("bos_storage.bucket").".".C("bos_storage.domain")."/".$file_name."/".$newname;
                    $to = str_replace('-internal', '', $to);
                    $new_to=ROOTTT."Uploads/".$file_name."/".$newname;
                    $updata['savename'] = $newname;
                    $updata['path'] = $new_to;
                    $this->upload_bos($updata);
                }
                $promote = array('game_id'=>$value['game_id'],'promote_id'=>$value['promote_id']);
                if($value['sdk_version']==2){
                   $plist_url = $this->create_plist($promote['game_id'],$promote['promote_id'],get_payload_name($value['game_id']),$to);
                }
                $jieguo = $this->updateinfo($value['id'],$to,$promote,$plist_url);
                $this->ajaxReturn(['status'=>1,'msg'=>'成功']);
            }
        }

    }


        /**
        *修改申请信息
        */
        public function updateinfo($id,$pack_url,$promote,$plist_url){
            $model = M('Apply',"tab_");
            $data['id'] = $id;
            $data['pack_url'] = $pack_url;
            $data['dow_url']  = '/index.php?s=/Down/down_file/game_id/'.$promote['game_id'].'/promote_id/'.$promote['promote_id'];
            $data['dow_status'] = 1;
            $data['enable_status']=1;
            $data['dispose_id'] = UID;
            $data['dispose_time'] = NOW_TIME;
            $data['plist_url'] = $plist_url;
            $res = $model->save($data);
            return $res;
        }



 //生成游戏渠道plist文件
    public function create_plist($game_id=0,$promote_id=0,$marking="",$url=""){
        $find_web_stie=M('config')->field('value')->where(['name'=>'WEB_SITE'])->find();      
        $xml = new \DOMDocument();
        $xml->load(ROOTTT.'Uploads/Plist/testdemo.plist');
        $online = $xml->getElementsByTagName('dict');//查找节点
        $asd=$online->item(1)->getElementsByTagName('string');//第二个节点下所有string
        foreach ($asd as $key=>$value) {
            switch ($value->textContent) {
                case 'ipa_url':
                if(preg_match("/Uploads/", $url)){
                  $value->nodeValue="https://".$find_web_stie['value'].ltrim($url,".");//"https://iosdemo.vlcms.com/app/MCHSecretary.ipa";//替换xml对应的值    
                }else{
                $value->nodeValue=$url;
                }
                    break;
                case 'icon':
                $value->nodeValue="https://".$find_web_stie['value'].get_cover(get_game_icon_id($game_id),'path',2);;                
                    break;
                 case 'com.dell':
                $value->nodeValue=$marking;
                    break;
                case '1.0.0':
                $value->nodeValue=game_version($game_id);
                    break;
                case 'mchdemo':
                $value->nodeValue=get_ios_game_name($game_id);
                    break;

            }
            if($promote_id==0){
            $xml->save(ROOTTT."Uploads/SourcePlist/$game_id.plist");
            }else{
            $pname=$game_id."-".$promote_id;
            $xml->save(ROOTTT."Uploads/GamePlist/$pname.plist");
            }
        }
        if($promote_id==0){
          return "./Uploads/SourcePlist/$game_id.plist";
        }else{
          return "./Uploads/GamePlist/$pname.plist";
        }


    }






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






    public function game_source($game_id,$type){
        $model = D('GameSource');
        $map['game_id'] = $game_id;
        $map['type'] = $type;
        $data = $model->where($map)->find();
        return $data;
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
