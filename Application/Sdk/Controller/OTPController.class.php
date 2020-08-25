<?php
/**
 * Created by PhpStorm.
 * User: xmy
 * Date: 2016/10/24
 * Time: 14:38
 */
namespace Sdk\Controller;
use Think\Controller;

class OTPController extends Controller{

    private $uid;//用户ID

    //接入安卓时 修改用户ID为验签解密后的用户ID
    public function verifyToken(){
        $request = json_decode(base64_decode(file_get_contents("php://input")),true);
        $otp_token = $request['token'];
        $otp_token = think_decrypt($otp_token,1);
        $data = json_decode($otp_token,true);
        if(empty($data['uid'])){
            echo base64_encode(json_encode(array('status'=>-1,'return_msg'=>'验签错误')));exit();
        }else{
            $user = M('member')->find($data['uid']);
            if($user['pkey'] !== $data['IMEI']){
                echo base64_encode(json_encode(array('status'=>-1,'return_msg'=>'IMEI错误')));exit();
            }
        }
        $this->uid = $data['uid'];
    }

    /**
     * 验证动态密码
     */
    public function verifyKey($uid,$code){
        $data = M('user','tab_')->field('pkey,otp_time_lag')->where(array('id'=>$uid))->find();
        $optKey = $this->Otp($uid,$data['pkey'],$data['time_lag']);
        if(strlen($optKey) < 6){
            return false;
        }
        if((int)$code === (int)$optKey){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 手机扫描二维码登录接口
     */
    public function qrLogin($token){
        $this->verifyToken();
        $data['token'] = $token;
        $token_data = think_decrypt($token,1);
        if(empty($token_data) || !empty(M('qr_login','tab_')->where(array('token'=>$token))->find())){
            echo base64_encode(json_encode(array('status'=>3,'msg'=>'二维码过期')));exit;
        }
        $uid = $this->uid;
        $data['uid'] = $uid;
        $data['create_time'] = time();
        M('qr_login','tab_')->add($data);
        R('Media/QrLogin/send',array('token'=>$token));
    }

    /**
     * 时间校准
     */
    public function timeCalibration(){
        $this->verifyToken();
        $request = json_decode(base64_decode(file_get_contents("php://input")),true);
        $uid = $this->uid;
        $time = $request['time'];
        $time_lag = date('YmdHis', time()) - $time;
        $map['id'] = $uid;
        $res = M('user', 'tab_')->where($map)->setField(array('otp_time_lag' => $time_lag));
        if ($res !== false) {
            echo base64_encode(json_encode(array('status' => 1,'return_msg'=>"校准成功")));
        } else {
            echo base64_encode(json_encode(array('status' => 2,'return_msg'=>"校准失败")));
        }
    }



    /**
     * 动态密码绑定
     */
    public function OTPBind(){
        $this->verifyToken();
        $request = json_decode(base64_decode(file_get_contents("php://input")),true);
        $map['id'] = $request['uid'];
        $pkey = $request['pkey'];
        $res = M('user','tab_')->where($map)->setField(array('pkey'=>$pkey));
        if ($res > 0) {
            echo base64_encode(json_encode(array('status' => 1)));
        } else {
            echo base64_encode(json_encode(array('status' => 2)));
        }
    }

    /**
     * 动态密码生成
     * @param $uid  用户ID
     * @param $pkey 用户手机身份识别码
     * @param $time_lag 用户与服务器的时差
     * @return int
     */
    private function Otp($uid, $pkey, $time_lag)
    {
        $time = date('YmdHis', time()) - $time_lag;
        $ext = mb_substr($time, -2);
        $head = mb_substr($time, 0, -2);
        if ($ext < 30) {
            $time = $head . "00";
            $str = $time . $uid . $pkey;
            $key = $this->BKDRHash($str);
            //小于6重新加密
            if (strlen((int)$key) < 6) {
                $time++;
                $str = $time . $uid . $pkey;
                $key = $this->BKDRHash($str);
            }
            $key = mb_substr((int)$key, 0, 6);
        } else {
            $time = $head . "30";
            $str = $time . $uid . $pkey;
            $key = $this->BKDRHash($str);
            //小于6重新加密
            if (strlen((int)$key) < 6) {
                $time++;
                $str = $time . $uid . $pkey;
                $key = $this->BKDRHash($str);
            }
            $key = mb_substr((int)$key, -6);
        }
        return $key;

    }

    /**
     * 修改保护状态
     */
    public function change_status(){
        $this->verifyToken();
        $data = M('user', 'tab_')->where(array('id' => $this->uid))->find();
        if($data['otp_status'] == 1){
            $res = M('user', 'tab_')->where(array('id' => $this->uid))->setField(array('otp_status' => 0));
            echo base64_encode(json_encode(array('protect_status' => 0,'return_msg'=>"未保护")));
        }else{
            $res = M('user', 'tab_')->where(array('id' => $this->uid))->setField(array('otp_status' => 1));
            echo base64_encode(json_encode(array('protect_status' => 1,'return_msg'=>"保护中")));
        }
    }

    public function get_partner(){
      echo base64_encode(json_encode(array('partner' => C('set_cooperate.partner'),'key'=>C('set_cooperate.key'))));
    }
    /**
     * BKDRhash加密
     * @param $str
     * @return int
     */
    private function BKDRHash($str = "1")
    {
        if (pay_set_status("set_cooperate") == 0) {
            return false;
        } else {
            $req['str'] = $str;
            $url = "http://yun.vlcms.com/sdk.php/OTP/BKDRHash/partner/" . C('set_cooperate.partner') . "/key/" . C('set_cooperate.key') . "/str/$str";
            $data = json_decode(base64_decode(file_get_contents($url)), true);
            if ($data['status'] == 1) {
                return $data['key'];
            } else {
                return false;
            }
        }
    }

}