<?php
/**
 * Created by PhpStorm.
 * User: xmy
 * Date: 2016/11/7
 * Time: 16:34
 */

namespace Sdk\Controller;
use Think\Controller;
use User\Api\MemberApi;
use Org\XiguSDK\Xigu;

class OTPLoginController extends Controller{
    /**
     * 用户登录
     */
    public function login(){
        $req = json_decode(base64_decode(file_get_contents("php://input")),true);
        $account = $req['account'];
        $password = $req['password'];
        $IMEI = $req['IMEI'];
        $userApi = new MemberApi();
        $result = $userApi->login($account,$password);
        if ($result > 0) {
            $user_data = M('user','tab_')->find($result);
            switch ($user_data){
                case empty($user_data['phone']):
                    $res_msg = array(
                        "status" => 3,
                        "return_msg" => "未绑定手机",
                    );
                    break;
                case empty($IMEI):
                    $res_msg = array(
                        "status" => 4,
                        "return_msg" => "参数错误",
                    );
                    break;
                case  !empty($user_data['pkey']) && $user_data['pkey'] !== $IMEI:
                    $res_msg = array(
                        "status" => 5,
                        "return_msg" => "请先解绑手机",
                        'phone' =>  $user_data['phone'],
                    );
                    break;
                case  $user_data['pkey'] === $IMEI:
                    $res_msg = array(
                        "status" => 6,
                        "return_msg" => "请勿重复登录",
                        'phone' =>  $user_data['phone'],
                    );
                    break;
                default:
                    $res_msg = array(
                        "status" => 1,
                        "return_msg" => "登录成功",
                        "user_id" => $result,
                        'phone' =>  $user_data['phone'],
                        "token" => think_encrypt(json_encode(array('uid'=>$result,'time'=>time(),'IMEI'=>$IMEI)),1),//返回验签,
                    );
            }
        } else {
            $res_msg = array(
                "status" => 2,
                "return_msg" => "帐号或密码错误",
            );
        }
        echo base64_encode(json_encode($res_msg));
    }


    /**
     * 发送手机验证码
     */
    public function send_sms()
    {
        $data = json_decode(base64_decode(file_get_contents("php://input")),true);
        $time = NOW_TIME - session($data['phone'].".create_time");
        if($time < 60){
            $result_data = array(
                'status'    =>  0,
                'return_msg'    =>  '请一分钟后再次尝试',
            );
            echo base64_encode(json_encode($result_data));exit;
        }
        $phone = $data['phone'];
        /// 产生手机安全码并发送到手机且存到session
        sdk2checksendcode($phone,C('sms_set.limit'));
        $rand = rand(100000,999999);
        $xigu = new Xigu(C('sms_set.smtp'));
        $param = $rand.",".'1';
        $result = json_decode($xigu->sendSM(C('sms_set.smtp_account'),$phone,C('sms_set.smtp_port'),$param),true);
        $result['create_time'] = time();
	    $result['pid'] = 0;
        $result['create_ip']=get_client_ip();
        M('Short_message')->add($result);
        #TODO 短信验证数据
        if($result['send_status'] == '000000') {
            session($phone,array('code'=>$rand,'create_time'=>NOW_TIME));
            $result_data = array(
                'status'    =>  1,
                'return_msg'    =>  '验证码发送成功',
            );
        }
        else{
            $result_data = array(
                'status'    =>  0,
                'return_msg'    =>  '验证码发送失败，请重新获取',
            );
        }
        echo base64_encode(json_encode($result_data));

    }


    /**
     * 手机绑定
     */
    public function OTPBind(){
        $req = json_decode(base64_decode(file_get_contents("php://input")),true);
        $account = $req['account'];
        $password = $req['password'];
        $userApi = new MemberApi();
        $user = $userApi->login($account,$password);
        if($user > 0){
            $result = $this->verify_code();
            if($result){
                $data['phone'] = $req['phone'];
                $data['pkey'] = $req['IMEI'];
                $data['otp_status'] = 1;
                $res = M('user','tab_')->where(array('id'=>$user))->setField($data);
                if ($res !== flase){
                    $user_data = M('user','tab_')->field('nickname,account')->find($user);
                    $result_data = array(
                        'status'    =>  1,
                        'return_msg'    =>  '绑定成功',
                        'uid'   =>  $user,
                        'icon'  =>  '',
                        'nickname'  =>  $user_data['nickname'],
                        'account'   =>  $user_data['account'],
                        'phone' =>  $req['phone'],
                        'token' =>  think_encrypt(json_encode(array('uid'=>$user,'time'=>time())),1),//返回验签
                        'protect_status'    =>  1,
                    );
                }else{
                    $result_data = array(
                        'status'    =>  0,
                        'return_msg'    =>  '操作数据不能为空',
                    );
                }
                echo base64_encode(json_encode($result_data));
            }
        }
    }


    /**
     * 动态密码解绑
     */
    public function OTPUnBind(){
        $req = json_decode(base64_decode(file_get_contents("php://input")),true);

        $res = $this->verify_code();
        if($res){
            $map['phone'] = $req['phone'];
            $user = M('user','tab_')->where($map)->find();
            if(empty($user)){
                echo base64_encode(json_encode(array('status' => 3,'return_msg'=>"用户不存在")));
            }
            $map = [];
            $map['id'] = $user['id'];
            if($req['IMEI'] === $user['pkey']){
                $res = M('user','tab_')->where($map)->setField(array('pkey'=>"",'opt_status'=>0));
                if ($res !== false) {
                    echo base64_encode(json_encode(array('status' => 1,'return_msg'=>"解绑成功",'uid' => $user['id'])));
                } else {
                    echo base64_encode(json_encode(array('status' => 2,'return_msg'=>"解绑失败")));
                }
            }else{
                $IMEI = empty($req['IMEI']) ? "" : $req['IMEI'];
                $res = M('user','tab_')->where($map)->setField(array('pkey'=>$IMEI,'opt_status'=>1));
                if($res !== false){
                    $result_data = array(
                        'status'    =>  1,
                        'return_msg'    =>  '绑定成功',
                        'uid'   =>  $user['id'],
                        'icon'  =>  '',
                        'nickname'  =>  $user['nickname'],
                        'account'   =>  $user['account'],
                        'phone' =>  $req['phone'],
                        'token' =>  think_encrypt(json_encode(array('uid'=>$user['id'],'time'=>time())),1),//返回验签
                        'protect_status'    =>  1,
                    );
                }else{
                    $result_data = array(
                        'status'    =>  0,
                        'return_msg'    =>  M('user','tab_')->getError(),
                    );
                }
                echo base64_encode(json_encode($result_data));
            }
        }
    }

    //验证验证码
    public function verify_code(){
        $req = json_decode(base64_decode(file_get_contents("php://input")),true);
        if (empty($req['phone'])) {
            $result = array(
                'status'    =>  0,
                'return_msg'    =>  '操作数据不能为空',
            );
            echo base64_encode(json_encode($result));exit;
        }
        $phone = $req['phone'];
        $code = $req['code'];
        $session = session($phone);
        $time = NOW_TIME - session($phone.".create_time");
        if(empty($session)){
            $result = array(
                'status'    =>  0,
                'return_msg'    =>  '数据获取失败',
            );
            echo base64_encode(json_encode($result));exit;
        }
        #验证码是否超时
        else if($time > 60){//$tiem > 60
            $result = array(
                'status'    =>  0,
                'return_msg'    =>  '验证码已过期！请重新获取',
            );
            echo base64_encode(json_encode($result));exit;
        }
        #验证短信验证码
        else if((int)session($phone.".code") !== (int)$code){
            $result = array(
                'status'    =>  0,
                'return_msg'    =>  '输入验证码不正确',
            );
            echo base64_encode(json_encode($result));exit;
        }else{
           return true;
        }
    }


    //验证手机序列号
    public function check_pkey(){
        $req = json_decode(base64_decode(file_get_contents("php://input")),true);
        $map['account']=$req['account'];
        $map['pkey']=$req['IMEI'];
        $user=M('user','tab_')->where($map)->find();
        if(null!==$user){
            echo base64_encode(json_encode(array('status'=>1)));
        }else{
            echo base64_encode(json_encode(array('status'=>2)));
        }
    }

    //验证手机号是否已被绑定
    public function check_phone(){
        $req = json_decode(base64_decode(file_get_contents("php://input")),true);
        $map['phone'] = $req['phone'];
        $user=M('user','tab_')->where($map)->find();
        if(null!==$user){
            echo base64_encode(json_encode(array('status'=>2,'msg'=>'该手机号已被绑定')));
        }else{
            echo base64_encode(json_encode(array('status'=>1,'msg'=>'该手机号未被绑定')));
        }
    }

}