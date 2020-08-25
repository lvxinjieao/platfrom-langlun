<?php

namespace Sdk\Controller;
use Think\Controller;
use Common\Api\GameApi;
/**
 * 支付游戏回调控制器
 * @author 小纯洁 
 */
class LoginNotifyController extends Controller {

    /**
    *服务器登录验证
    */
    public function login_verify(){
        $request = json_decode(file_get_contents("php://input"),true);
       if (empty($request)) {
            echo json_encode(array('status'=>1066,'msg'=>'数据异常'));exit();
        }
         $user = M("user","tab_")->find($request['user_id']);
         if($request['token'] == $user['token']){
            echo json_encode(array('status'=>200,'user_id'=>$user['id'],"user_account"=>$user['account']));exit();
         }else{
            echo json_encode(array('status'=>1003,'msg'=>'token验证失败'));exit();
         }
    }
}
