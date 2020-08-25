<?php
/**
 * Created by PhpStorm.
 * User: xmy
 * Date: 2016/11/7
 * Time: 16:34
 */

namespace Sdk\Controller;
use Think\Controller;

class OTPServerController extends Controller{
    protected function _initialize(){
        /* 读取站点配置 */
        $config = api('Config/lists');
        C($config); //添加配置
    }

    //分享
    public function share(){
        $msg=array(
            'title'=>C('MB_TITLE'),
            'content'=>C('MB_CONTENT'),
            'logourl'=> icon_url(C('MB_SMAIL_LOGIN')),
            'shareurl'=>"http://".$_SERVER["HTTP_HOST"]."/media.php/share/index"// 分享出去点击跳转的页面链接
            );
        echo json_encode($msg);
    }
    //推荐
    public function tui($type=2){//1苹果  2安卓
        $map['id']=$type==1?3:2;
        $app=M('app','tab_')->where($map)->find();
         if($type==3){
            $qrcode= icon_url(C('IOS_MB_QREU'));
            $url="https://".$_SERVER["HTTP_HOST"].$app['file_url'];
        }else{
            $qrcode= icon_url(C('MB_QREU'));
            $url="http://".$_SERVER["HTTP_HOST"].$app['file_url'];
        }
        $msg=array(
            'qrcode'=>$qrcode,
            'url'=>$url//动态密保下载链接
            );
        echo json_encode($msg);
           
    }
    //关于我们
    public function about(){
            $msg=array(
                'otplogo'=>icon_url(C('MB_LOGIN')),
                'versio'=>C('MB_VERSION'),
                'xieyi'=>"",//用户协议
                'copyright'=>C('MB_COPYRIGHT'),
                );
            echo json_encode($msg);
               
        }


    //闪屏
    public function splash($type=2){ //1苹果 2安卓
        $map['id']=$type==2?2:3;
        $data=M('App','tab_')->where($map)->find();
        if($type==3){
            $url="https://".$_SERVER["HTTP_HOST"].$data['file_url'];
        }else{
            $url="http://".$_SERVER["HTTP_HOST"].$data['file_url'];
        }
            $msg=array(
                'splogo'=>icon_url(C('MB_SLOGIN')),
                'versio'=>C('MB_VERSION'),
                'version_name'=>C('MB_VERSION_NAME'),
                'tel'=>C('MB_TEL'),
                'url'=>$url
                );
            echo json_encode($msg);
               
        }


    //使用帮助
    public function get_help(){
        echo json_encode(array('status'=>1,'url'=>"http://".$_SERVER["HTTP_HOST"].U('help')));
    }

    //使用帮助
    public function help(){
        $this->display(); 
    }



}