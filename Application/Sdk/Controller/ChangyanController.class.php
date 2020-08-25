<?php

namespace Sdk\Controller;
use Think\Controller;
/**
 * 支付游戏回调控制器
 * @author 小纯洁 
 */
class ChangyanController extends Controller {

    /**
    *畅言登录验证
    */
    public function getuserinfo(){
        $wgUser=session('member_auth'); //全局变量(注意：$wgUser变量用来表示用户在您网站登录信息，该变量得开发者自己实现，实现方式一般是通过cookie或session原理)
        if($wgUser['mid']!=0){
            $ret=array(  
            "is_login"=>1, //已登录，返回登录的用户信息
            "user"=>array(
            "user_id"=>$wgUser['mid'],
            "nickname"=>$wgUser['nickname']),
            "img_url"=>"",
            "profile_url"=>"",
            "sign"=>"**" //注意这里的sign签名验证已弃用，任意赋值即可
            );
        }else{
            $ret=array("is_login"=>0);//未登录
        }
        echo $_GET['callback'].'('.json_encode($ret).')';
    }
    public function loginout(){
        $wgUser=session('member_auth'); 
        if($wgUser['mid']==0){
            $return=array(
            'code'=>1,
            'reload_page'=>0
            );
        }else{
            $return=array(
            'code'=>1,
            'reload_page'=>1
            );
        }
    }
}
