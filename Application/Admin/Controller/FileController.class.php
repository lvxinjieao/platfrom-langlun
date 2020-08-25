<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------
namespace Admin\Controller;
use Think\ShardUpload;
/**
 * 文件控制器
 * 主要用于下载模型的文件上传和下载
 */
class FileController extends AdminController {

    /* 文件上传 */
    public function upload(){
        $return  = array('status' => 1, 'info' => '上传成功', 'data' => '');
        /* 调用文件上传组件上传文件 */
        $File = D('File');
        $file_driver = C('DOWNLOAD_UPLOAD_DRIVER');
        $info = $File->upload(
            $_FILES,
            C('DOWNLOAD_UPLOAD'),
            C('DOWNLOAD_UPLOAD_DRIVER'),
            C("UPLOAD_{$file_driver}_CONFIG")
        );

        /* 记录附件信息 */
        if($info){
            $return['data'] = think_encrypt(json_encode($info['download']));
            $return['info'] = $info['download']['name'];
        } else {
            $return['status'] = 0;
            $return['info']   = $File->getError();
        }

        /* 返回JSON数据 */
        $this->ajaxReturn($return);
    }

    /* 文件分片上传 */
    public function shard_upload(){
        //关闭缓存
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        $uploader = new ShardUpload();
        if(I('get.type') == 1){
            $uploader->set('path','./Uploads/Material');
        }
        if(I('get.typee') == 1){
            $uploader->set('path','./Uploads/Ios/description');
        }

        //var_dump($_POST);
        //用于断点续传，验证指定分块是否已经存在，避免重复上传
        if(isset($_POST['status'])){
            if($_POST['status'] == 'chunkCheck'){
                $target = C('DOWNLOAD_UPLOAD.rootPath')."/".$_POST['name'].'/'.$_POST['chunkIndex'];
                if(file_exists($target) && filesize($target) == $_POST['size']){
                    die('{"ifExist":1}');
                }
                die('{"ifExist":0}');

            }elseif($_POST['status'] == 'md5Check'){
                
            }elseif($_POST['status'] == 'chunksMerge'){
                if($result = $uploader->chunksMerge($_POST['name'], $_POST['chunks'], $_POST['ext'])){
                    //todo 把md5签名存入持久层，供未来的秒传验证
                    echo $result;
                    exit();
                }
            }
        }
        if(($path = $uploader->upload('file', $_POST)) !== false){
            die($path);
        }
        
    }
    /* 苹果渠道包文件分片上传 */
    public function ios_shard_upload(){
        //关闭缓存
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        $uploader = new ShardUpload();
        //用于断点续传，验证指定分块是否已经存在，避免重复上传
        if(isset($_POST['status'])){
            if($_POST['status'] == 'chunkCheck'){
                $target = C('DOWNLOAD_UPLOAD.rootPath')."/".$_POST['name'].'/'.$_POST['chunkIndex'];
                if(file_exists($target) && filesize($target) == $_POST['size']){
                    die('{"ifExist":1}');
                }
                die('{"ifExist":0}');

            }elseif($_POST['status'] == 'md5Check'){
                
            }elseif($_POST['status'] == 'chunksMerge'){
                if($result = $uploader->chunksMerge($_POST['name'], $_POST['chunks'], $_POST['ext'])){
                    //todo 把md5签名存入持久层，供未来的秒传验证
                    echo $result;
                    exit();
                }
            }
        }
        if(($path = $uploader->iosUpload('file', $_POST)) !== false){
            die($path);
        }
        
    }
    /* 下载文件 */
    public function download($id = null){
        if(empty($id) || !is_numeric($id)){
            $this->error('参数错误！');
        }

        $logic = D('Download', 'Logic');
        if(!$logic->download($id)){
            $this->error($logic->getError());
        }

    }
    /*下载文件*/
    public function downloadFile($id='') {
        if(empty($id) || !is_numeric($id)){
            $this->error('参数错误！');
        }

        $File = D('File');
        $root = C('DOWNLOAD_UPLOAD.rootPath');
        $call = array($this, 'setDownload');
        if(false === $File->download($root, $id, $call)){
            $this->error = $File->getError();
        }
    }
    /**
     * 新增下载次数（File模型回调方法）
     */
    public function setDownload($id){
        $map = array('id' => $id);
        D('File')->where($map)->setInc('download');
    }
    /**
     * 上传图片
     * @author huajie <banhuajie@163.com>
     * @param [flag] $[name] [<控制是否需要水印>]
     */
    public function uploadPicture(){
        //TODO: 用户登录检测

        /* 返回标准数据 */
        $return  = array('status' => 1, 'info' => '上传成功', 'data' => '');

        /* 调用文件上传组件上传文件 */
        $Picture = D('Picture');
        $pic_driver = C('PICTURE_UPLOAD_DRIVER');
        $info = $Picture->upload(
            $_FILES,
            C('PICTURE_UPLOAD'),
            C('PICTURE_UPLOAD_DRIVER'),
            C("UPLOAD_{$pic_driver}_CONFIG"),
                        $_REQUEST['flag']
        ); //TODO:上传到远程服务器
        // 添加水印
        if ($_REQUEST['flag']) {
            $this->addWatermarkOnPic($info['download']);
        }
                
        /* 记录图片信息 */
        if($info){
            $return['status'] = 1;
            $return = array_merge($info['download'], $return);
        } else {
            $return['status'] = 0;
            $return['info']   = $Picture->getError();
        }
         ob_clean();
        /* 返回JSON数据 */
        $this->ajaxReturn($return);
    }
        
        
        /**
         * 给图片添加水印
         * @author 鹿文学
         * @date 2018-05-03
         */
        public function addWatermarkOnPic($info) {
            
            if(empty($info['water'])) {return false;}
            
            // watermark
            $tool = M('tool',"tab_")->field('config')->where(['name'=>'watermark'])->select();
            //没有此设置
            if(empty($tool)){return false;}
            
            $water = json_decode($tool[0]['config'],true);
            
            $temp = explode(',',$water['position']);
            $position = 3*$temp[0]+$temp[1]+1;
            
            $image = new \Think\Image(); 
            
            if ($water['types']==1) { // 文字
            
                $offset = array(
                    $water['hoffset']?$water['hoffset']:0,
                    $water['voffset']?$water['voffset']:0
                );
                
                $image->open(ROOTTT.$info['path'])->text($water['font_text'],FONTS.$water['font'],$water['font_size'],$water['font_color'],$position,$offset)->save(ROOTTT.$info['water'],$image->type(),$water['quality']); 
                
            } else { // 图片
                
                    $image->open(ROOTTT.$info['path'])->water(ROOTTT.get_cover($water['icon'],'path'),$position,$water['transparency'])->save(ROOTTT.$info['water'],$image->type(),$water['quality']); 
                
            }
            
            
        }
        
        
}
