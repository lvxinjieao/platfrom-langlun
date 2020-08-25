<?php

namespace Sdk\Controller;



use Think\Controller;

use User\Api\MemberApi;

use Org\XiguSDK\Xigu;

use Org\UcenterSDK\Ucservice;

use Com\WechatAuth;


class UserController extends BaseController

{

    /**

     *SDK用户登录

     */

    public function user_login()

    {
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $user = json_decode(base64_decode(file_get_contents("php://input")), true);
        #判断数据是否为空
        if (empty($user)) {
            $this->set_message(1001, "fail", "登录数据不能为空");
        }

            #实例化用户接口
            $userApi = new MemberApi();
            $result = $userApi->login_($user["account"], $user['password'], 1, $user["game_id"], get_game_name($user["game_id"]), $user['sdk_version']);#调用登录
            $res_msg = array();
            switch ($result) {
                case -1:
                    $this->set_message(1004, "fail", "用户不存在或被禁用");
                    break;
                case -2:
                    $this->set_message(1005, "fail", "密码错误");
                    break;
                default:
                    if (is_array($result)) {
                        $user["user_id"] = $result['user_id'];
                        $this->add_user_play($user);
                        $this->add_user_behavior($user);
                        $res_msg = array(
                            "status" => 200,
                            "return_code" => "success",
                            "return_msg" => "登录成功",
                            "user_id" => $user["user_id"],
                            "account" => $user["account"],
                            "token" => $result['token'],
                            "OTP_token" => think_encrypt(json_encode(array('uid' => $user["user_id"], 'time' => time())), 1),
                            'is_uc' => 0,
                        );
                    } else {
                        $this->set_message(1028, "fail", "未知错误");
                    }
                    break;
            }
            echo base64_encode(json_encode($res_msg));

    }



    protected function add_user_behavior($user){
        $map['user_id'] = $user['user_id'];
        $map['game_id'] = $user["game_id"];
        $map['status'] = ['in','-2,2'];
        $map['update_time'] = total(1,false);
        $data = M('user_behavior as ub','tab_')
            ->where($map)
            ->find();
        if($data){
            $save['status'] = 2;
            $save['id'] = $data['id'];
            $save['update_time'] = time();
            $res = M('user_behavior','tab_')->save($save);
            if($res){
                $result['code'] = 1;
                $result['data'] = $save['status'];
            }else{
                $result['code'] = 0;
            }
        }else{
            $save['user_id'] = $user['user_id'];;
            $save['game_id'] = $user["game_id"];;
            $save['sdk_version'] = $user['sdk_version'];
            $save['status'] = 2;
            $save['update_time'] = time();
            $save['create_time'] = $save['update_time'];
            $res = M('user_behavior','tab_')->add($save);
            if($res){
                $result['code'] = 1;
                $result['data'] = $save['status'];
            }else{
                $result['code'] = 0;
            }
        }
    }





    /**

     * 第三方登录

     */

    public function oauth_login()

    {
        $request = json_decode(base64_decode(file_get_contents("php://input")), true);
        if (empty($request)) {
            $this->set_message(1001, "fail", "登录数据不能为空");

        }
        $openid = $request['openid'];
        if ($request['login_type'] == "wx") {
              $param_set=get_game_wxlogin_param($request['game_id']);
                if(empty($param_set['wx_appid'])){
                     $result['status'] = 1083;
                     $result['message'] = '微信登录appid/appsecret为空';
                     echo base64_encode(json_encode($result));exit;
                }

            Vendor("WxPayPubHelper.WxPayPubHelper");
            // 使用jsapi接口
            $jsApi = new \JsApi_pub($param_set['wx_appid'], $param_set['appsecret'], $request['code']);
            $wx = $jsApi->create_openid($param_set['wx_appid'], $param_set['appsecret'], $request['code']);
            //unionid如果开发者有在多个公众号，或在公众号、移动应用之间统一用户帐号的需求，需要前往微信开放平台（open.weixin.qq.com）绑定公众号后，才可利用UnionID机制来满足上述需
            if (empty($wx['unionid'])) {
                echo base64_encode(json_encode(array("status" => 1071, "message" => "请到微信开放平台（open.weixin.qq.com）绑定公众号")));
                exit();
            }
            $openid = $wx['unionid'];
            $wx_param_set=get_game_wxlogin_param($request['game_id']);
            $auth = new WechatAuth($wx_param_set['wx_appid'], $wx_param_set['appsecret'], $wx['access_token']);
            $userInfo = $auth->getUserInfo($wx['openid']);
            $register_type = 3;
            $head_img = $userInfo['headimgurl'];
        }elseif ($request['login_type'] == "qq") {
            $register_type = 4;
            $qq_parm['access_token'] = $request['accessToken'];
            $qq_parm['oauth_consumer_key'] = C('qq_login.appid');
            $qq_parm['openid'] = C('qq_login.key');
            $qq_parm['format'] = "json";
            $openid=get_union_id($request['accessToken']);
            if(empty($openid)){
                $res['status'] = -1;
                $res['message'] = '腾讯公司应用未打通 未将所有appid设置统一unionID';
                echo base64_encode(json_encode($res));
                exit();
            }
            $url = "https://graph.qq.com/user/get_user_info?" . http_build_query($qq_parm);
            $qq_url = json_decode(file_get_contents($url), true);
            $head_img = $qq_url['figureurl_qq_1 '];
        }elseif ($request['login_type'] == "yk") {
            $register_type = 0;
            $head_img = "http://" . $_SERVER['HTTP_HOST'] . '/Public/Sdk/logoo.png';
        }
        $map['openid'] = $openid;
        if ($request['login_type'] == "yk" && isset($request['account'])) {
            unset($map['openid']);
            $map['account'] = $request["account"];
            $map['register_type'] = 0;
//            $map['imei'] = $request["imei"]; //一台设备只能一个账号玩
        }elseif ($request['login_type'] == "yk") {
            $map['id'] = -1;
//            $map['imei'] = $request["imei"];

        }
        $data = M('user', 'tab_')->where($map)->find();
        if (empty($data)) {//注册
            do {
                $data['account'] = $request['login_type'] . '_' . sp_random_string();
                $account = M('user', 'tab_')->where(['account' => $data['account']])->find();
            } while (!empty($account));
            $data['password'] = sp_random_string(8);
            $data['nickname'] = $data['account'];
            $data['openid'] = $openid;
            $data['game_id'] = $request['game_id'];
            $data['head_img'] = $head_img;//头像
            $data['game_name'] = get_game_name($request['game_id']);
            $data['promote_id'] = $request['promote_id'];
            $data['promote_account'] = get_promote_name($request['promote_id']);
            $data['register_way'] = 1;
            $data['register_type'] = $register_type;
            $data['sdk_version'] = $request['sdk_version'];
            $data['game_appid'] = $request['game_appid'];
            $data['imei'] = (isset($request['imei']) ? $request['imei'] : '');
            $userApi = new MemberApi();
            $uid = $userApi->tr_register($data);
            if (!is_array($uid)||empty($uid)) {
                $res['status'] = 1023;
                $res['message'] = '注册失败';
                echo base64_encode(json_encode($res));
                exit;
            }
        }
        //登录
        $userApi = new MemberApi();
        $result = $userApi->login_($data["account"], $data["account"], 1, $request["game_id"], get_game_name($request['game_id']), $request['sdk_version']);
        if ($result == -1) {
            $res['status'] = 1004;
            $res['message'] = '用户不存在或被禁用';
        }elseif ($result == -2) {
            $res['status'] = 1004;
            $res['message'] = '用户不存在或被禁用';
        } else {
            $request["user_id"] = $result['user_id'];
            $this->add_user_play($request);
            $res['status'] = 200;
            $res['message'] = '登录成功';
            $res['user_id'] = $result['user_id'];
            $res['account'] = $data['account'];
            $res['token'] = $result['token'];
            $res['password'] = $data['password'];
        }
        echo base64_encode(json_encode($res));
    }



    /**

     *第三方登录设置

     */

    public function thirdparty()

    {

        $request = json_decode(base64_decode(file_get_contents("php://input")), true);
        if (empty($request)) {

            $this->set_message(1001, "fail", "登录数据不能为空");

        }

        $map['game_id']=$request['game_id'];

        $select=M('param','tab_')->field('type,status')->where($map)->select();
        if(!empty($select)){

            foreach ($select as $key => $val) {

                if ($val['type'] == 1&&$val['status']==1) {

                    $data['config'] .= 'qq|';

                }

                if ($val['type'] == 2&&$val['status']==1) {

                    $data['config'] .= 'wx|';

                }

            }

        }else{

         $data['config'] = '';

        }

        echo base64_encode(json_encode($data));

        // $str = "qq_login,weixin_login,sina_login,baidu_login,fb_login,gg_login";

        // $this->BaseConfig($str);

    }



    /**

     *显示扩展设置信息

     */

    protected function BaseConfig($name = '')

    {

        $map['name'] = array('in', $name);

        $map['status'] = 1;

        $tool = M('tool', "tab_")->where($map)->select();

        $data['config'] = '';

        if (!empty($tool)) {

            foreach ($tool as $key => $val) {

                if ($val['name'] == 'qq_login') {

                    $data['config'] .= 'qq|';

                }

                if ($val['name'] == 'weixin_login') {

                    $data['config'] .= 'wx|';

                }

                if ($val['name'] == 'sina_login') {

                    $data['config'] .= 'wb|';

                }

                if ($val['name'] == 'baidu_login') {

                    $data['config'] .= 'bd|';

                }

                if ($val['name'] == 'fb_login') {

                    $data['config'] .= 'fb|';

                }

                if ($val['name'] == 'gg_login') {

                    $data['config'] .= 'gg|';

                }

            }

        }

        if ($data['config'] != '') {

            $data['config'] = substr($data['config'], 0, strlen($data['config']) - 1);

        }

        echo base64_encode(json_encode($data));

    }



    /**

     * 第三方登录参数请求

     */

    public function oauth_param()

    {

        $request = json_decode(base64_decode(file_get_contents("php://input")), true);

        $type = $request['login_type'];


        switch ($type) {

            case 'qq':
                $param_set=get_game_param($request['game_id'],1);
                $param['qqappid'] = $param_set['openid'];

                break;

            case 'wx':                                
                $param_set=get_game_param($request['game_id'],2);
                $param['weixinappid'] =$param_set['wx_appid'];

                break;

            

        }

        if (empty($param)) {

            $result['status'] = 1090;

            $result['message'] = '服务器未配置此参数';

        } else {

            $result['status'] = 200;

            $result['message'] = '请求成功';

            $result['param'] = $param;

        }

        echo base64_encode(json_encode($result));

    }





    public function user_register()

    {
        C(api('Config/lists'));
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $user = json_decode(base64_decode(file_get_contents("php://input")), true);
        #判断数据是否为空
        if (empty($user)) {
            $this->set_message(1001, "fail", "注册数据不能为空");
        }
            $this->reg_data($user);
    }



    /**

     * 邮箱注册

     */

    public function user_email_register()

    {
        C(api('Config/lists'));
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $user = json_decode(base64_decode(file_get_contents("php://input")), true);
        if (empty($user)) {
            $this->set_message(1001, "fail", "注册数据不能为空");
        }
        $v_res = $this->verify_email_code($user['account'], $user['code']);
        if ($v_res) {
            $this->reg_data($user, 3);
        }

    }



    /**

     *手机用户注册

     */

    public function user_phone_register()

    {
        C(api('Config/lists'));
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $user = json_decode(base64_decode(file_get_contents("php://input")), true);
        #判断数据是否为空
        if (empty($user)) {
            $this->set_message(1001, "fail", "注册数据不能为空");
        }
        #验证短信验证码
        $this->sms_verify($user['account'], $user['code']);
        $this->reg_data($user, 2);
    }



    //注册信息

    public function reg_data($user, $type = 1)

    {
//        if(!array_key_exists('imei',$user)) {
//            $this->set_message(1037, "fail", "参数错误，设备imei不能为空");
//        }
        #实例化用户接口
        $userApi = new MemberApi();
        //一台设备一个账号玩
//        $isExist = M("User","tab_")->where(['imei'=>$user['imei']])->field('id')->find();
//        if(!empty($isExist)) {
//            $this->set_message(1037, "fail", "此设备已经注册过账号");
//        }
        // user表加game_id
        if ($type == 2) {//手机2
            $result = $userApi->register_($user['account'], $user['password'], 1, 2, $user['promote_id'], get_promote_name($user['promote_id']), $user['account'], $user["game_id"], get_game_name($user["game_id"]), $user['sdk_version'],$user['imei']);
        } elseif($type == 3) {//邮箱3
            $result = $userApi->register_($user['account'], $user['password'], 1, 5, $user['promote_id'], get_promote_name($user['promote_id']), '', $user["game_id"], get_game_name($user["game_id"]), $user['sdk_version'],$user['imei']);
        }else {//用户1
            $result = $userApi->register_($user['account'], $user['password'], 1, 1, $user['promote_id'], get_promote_name($user['promote_id']), $phone = "", $user["game_id"], get_game_name($user["game_id"]), $user['sdk_version'],$user['imei']);
        }
        $res_msg = array();
        if ($result > 0) {
            $this->set_message(200, "success", "注册成功");
        } else {
            switch ($result) {
                case -3:
                    $this->set_message(1017, "fail", "用户名已存在");
                    break;
                default:
                    $this->set_message(1027, "fail", "注册失败");
                    break;
            }
        }

    }



    /**

     *修改用户数据

     */

    public function user_update_data()

    {

        $user = json_decode(base64_decode(file_get_contents("php://input")), true);

        C(api('Config/lists'));
        #判断数据是否为空

        if (empty($user)) {

            $this->set_message(1001, "fail", "操作数据不能为空");

        }

        #实例化用户接口

        $data['id'] = $user['user_id'];

        $userApi = new MemberApi();


        switch ($user['code']) {

            case 'phone':
                //对于手机号 加上是否重复的验证过程 和 是否预计绑定的验证过程
                $where['id'] = $data['id'];
                $getphone = M('User','tab_')->where($where)->getField('phone');
                if (!empty($getphone)){
                    $this->set_message(1125,'fail','该用户已经绑定过手机号，请解绑后再来');
                }
                $tt['phone'] = $user['phone'];
                $getuser = M('User','tab_')->where($tt)->field('id')->find();
                if ($getuser){
                    $this->set_message(1098,'fail','该手机号已被绑定');
                }
                #验证短信验证码

                $this->sms_verify($user['phone'], $user['sms_code']);

                $data['phone'] = $user['phone'];

                break;

            case 'nickname':

                $data['nickname'] = $user['nickname'];

                break;

            case 'pwd':
                if($user['old_password']==$user['password']){
                     $this->set_message(1126,'fail','新密码与原始密码不能相同');
                }
                $data['old_password'] = $user['old_password'];

                $data['password'] = $user['password'];

                break;

            case 'account':
             if(!preg_match("/^[a-z\d]{6,15}$/i",$user['account'])){
                    $this->set_message(1025, "fail", "用户名为6-15位的数字和字母的组合");
                }

                $data['account'] = $user['account'];

                $data['password'] = $user['password'];

                $data['register_type'] = 1;  //游客改为账号注册

                $map['account'] = $user['account'];

                $res = M('user', 'tab_')->where($map)->find();

                if ($res) {

                    $this->set_message(1017, "fail", "已存在该用户名");

                }

                $result = $this->updata_user_youke($data);

                break;

            default:

                $this->set_message(1074, "fail", "修改信息不明确");

                break;

        }
        $result = $userApi->updateUser($data);

        if ($result == -2) {

            $this->set_message(1006, "fail", "旧密码输入不正确");

        } else if ($result !== false) {

            if ($user['code'] == 'pwd') {

                $u_uid['uid'] = $user['user_id'];

                M('user_pwd')->where($u_uid)->setField('password', think_encrypt($data['password']));

            }



            $this->set_message(200, "success", "修改成功");

        } else {

            $this->set_message(1012, "fail", "修改失败");

        }

    }



    //游客改名，对应修改数据

    private function updata_user_youke($data)

    {

        $map['user_id'] = $data['id'];

        $data1['user_account'] = $data['account'];

        $res = M('user_play_info', 'tab_')->where($map)->setField($data1);

        $res1 = M('user_play', 'tab_')->where($map)->setField($data1);

        $res2 = M('user_login_record', 'tab_')->where($map)->setField($data1);

        $res3 = M('spend', 'tab_')->where($map)->setField($data1);

        $res4 = M('provide', 'tab_')->where($map)->setField($data1);

        $res5 = M('mend', 'tab_')->where($map)->setField($data1);

        $res6 = M('gift_record', 'tab_')->where($map)->setField($data1);

        $res7 = M('deposit', 'tab_')->where($map)->setField($data1);

        $res8 = M('bind_spend', 'tab_')->where($map)->setField($data1);

        $res9 = M('balance_edit', 'tab_')->where($map)->setField($data1);

        $res10 = M('agent', 'tab_')->where($map)->setField($data1);

    }



    //验证验证码

    public function verify_sms()

    {

        $user = json_decode(base64_decode(file_get_contents("php://input")), true);

        if (empty($user)) {

            $this->set_message(1001, "fail", "操作数据不能为空");

        }

        $this->sms_verify($user['phone'], $user['code'],1);

    }

       //sdk验证邮箱

    public function verify_email()

    {

        $user = json_decode(base64_decode(file_get_contents("php://input")), true);

        if (empty($user)) {

            $this->set_message(1001, "fail", "操作数据不能为空");

        }



        $code_result = $this->emailVerify($user['email'], $user['code']);

        if ($code_result == 1) {

           $this->set_message(200, "success", "验证成功");

        } else {

            if ($code_result == 2) {

                $this->set_message(1020, "fail","请先获取验证码");

            } elseif ($code_result == -98) {

                $this->set_message(1021, "fail", "验证码超时");

            } elseif ($code_result == -97) {

                $this->set_message(1022, "fail", "验证码错误");

            }

        }

    }




    /**

     *忘记密码接口

     */

    public function forget_password()

    {
		//请求数据
		/* {
			"code": "934558",
			"code_type": "1",
			"game_id": "1",
			"password": "666666",
			"phone": "13760839356",
			"sdk_version": "1",
			"user_id": "1",
			"md5_sign": "c7a934778ce8f3f01515720a06c1bd3f"
		} */
		
        $user = json_decode(base64_decode(file_get_contents("php://input")), true);
        $userApi = new MemberApi();
        /* $user['phone'] = 13760839356;
        $user['code'] = 830357;
        $user['password'] = 666666;
        $user['user_id'] = 1; */
        #验证短信验证码
        if($user['code_type']!=2){
            $this->sms_verify($user['phone'], $user['code']);
        }

        $result = $userApi->updatePassword($user['user_id'], $user['password']);

        if ($result == true) {

            $this->set_message(200, "success", "修改成功");

        } else {

            $this->set_message(1012, "fail", "修改失败");

        }

    }



    /**

     *添加玩家信息

     */

    private function add_user_play($user = array())

    {

        $user_play = M("UserPlay", "tab_");

        $map["game_id"] = $user["game_id"];

        $map["user_id"] = $user["user_id"];

        $map['sdk_version'] = $user['sdk_version'];

        $res = $user_play->where($map)->find();

        if (empty($res)) {

            $user_entity = get_user_entity($user["user_id"]);

            $data["user_id"] = $user["user_id"];

            $data["user_account"] = $user_entity["account"];

            $data["user_nickname"] = $user_entity["nickname"];

            $data["game_id"] = $user["game_id"];

            $data["game_appid"] = $user["game_appid"];

            $data["game_name"] = get_game_name($user["game_id"]);

            $data["server_id"] = 0;

            $data["server_name"] = "";

            $data["role_id"] = 0;

            $data['parent_id'] = $user_entity["parent_id"];

            $data['parent_name'] = $user_entity["parent_name"];

            $data["role_name"] = "";

            $data["role_level"] = 0;

            $data["bind_balance"] = 0;

            $data["promote_id"] = $user_entity["promote_id"];

            $data["promote_account"] = $user_entity["promote_account"];

            $data['play_time'] = time();

            $data['play_ip'] = get_client_ip();

            $data["sdk_version"] = $user["sdk_version"];

            $user_play->add($data);

        }

    }



    //修改角色名称

    public function update_user_play()

    {

        $user = json_decode(base64_decode(file_get_contents("php://input")), true);

        if (empty($user)) {

            $this->set_message(1001, "fail", "操作数据不能为空");

        }

        $map['user_id'] = $user['user_id'];

        $map['game_id'] = $user['game_id'];

        $userplay = M('user_play', 'tab_')->where($map)->find();

        if (null == $userplay) {

            $this->set_message(1004, "fail", "玩家不存在");

        } else {

            $user_play_map['id'] = $userplay['id'];

            if ($user['type'] == 1) {

                $update = M('user_play', 'tab_')->where($user_play_map)->setField('role_name', $user['role_name']);

            } else {

                $update = M('user_play', 'tab_')->where($user_play_map)->setField('role_level', $user['role_level']);

            }

            if ($update) {

                $this->set_message(1, "success", "修改成功");

            } else {

                $this->set_message(0, "fail", "修改失败");



            }

        }

    }



    /**

     * 添加游戏角色数据

     * @param $request

     */

    public function save_user_play_info()

    {

        $request = json_decode(base64_decode(file_get_contents("php://input")), true);

        if (empty($request)) {

            $this->set_message(1001, "fail", "操作数据不能为空");

        }


        $user_id = $request['user_id'];

        $server_id = empty($request['server_id']) ? 0 : $request['server_id'];

        $map['user_id'] = $user_id;

        $map['game_id'] = $request['game_id'];

        $map['server_id'] = $request['server_id'];
		
		$map['server_name'] = $request['server_name'];

        $user_play = M('user_play_info', 'tab_');

        $res = $data = $user_play->where($map)->find();

        $user = M('user', 'tab_');

        $user_data = $user->find($user_id);

        $data['promote_id'] = $request['promote_id'];

        $data['promote_account'] = get_promote_account($request['promote_id']);

        $data['game_id'] = $request['game_id'];

        $data['game_name'] = get_game_name($request['game_id']);

        $data['server_id'] = $request['server_id'];

        $data['server_name'] = $request['server_name'];

        $data['role_name'] = $request['game_player_name'];

        $data['role_level'] = $request['role_level'];

        $data['user_id'] = $user_id;

        $data['user_account'] = $user_data['account'];

        $data["user_nickname"] = $user_data["nickname"];

        $data['play_time'] = time();

        $data["sdk_version"] = $request["sdk_version"];

        $data['play_ip'] = get_client_ip();
		
		$data['user_play_id'] = $user_id;
		
		$data['update_time'] = time();

        if (empty($res)) {

            $user_play->add($data);

        } else {

            $user_play->save($data);

        }

        $this->set_message(200, "success", "成功");

    }





    /**

     * @param $email

     * @param $v_code

     * 验证邮箱验证码

     */

     public function verify_email_code($email, $v_code)

    {

        if (empty($email)) {

            $this->set_message(1016, "邮箱不能为空");

        } elseif (empty($v_code)) {

            $this->set_message(1019, "验证码不能为空");

        }

        $code_result = $this->emailVerify($email, $v_code);

        if ($code_result == 1) {

            return true;

        } else {

            if ($code_result == 2) {

                $this->set_message(1020, "fail","请先获取验证码");

            } elseif ($code_result == -98) {

                $this->set_message(1021, "fail", "验证码超时");

            } elseif ($code_result == -97) {

                $this->set_message(1022, "fail", "验证码错误");

            }

        }

    }


    /**

     * @param $email

     * @param $code

     * @param int $time

     * @return int

     * 验证 邮箱验证码

     */

    public function emailVerify($email, $code, $time = 10)

    {

        $session = session($email);

        if (empty($session)) {

            return 2;

        } elseif ((NOW_TIME - $session['create_time']) > $time * 60 * 60) {

            return -98;

        } elseif ($session['code'] != $code) {

            return -97;

        }

        session($email, null);

        return 1;

    }



    /**

     * 发送邮件验证码

     */

    public function send_email()
    {
        $data = json_decode(base64_decode(file_get_contents("php://input")), true);

        if($data['code_type']==1){//注册
            if (!$this->checkUserExist($data['email'])) {
                $this->set_message(1017, "fail", "用户已存在");
                exit;
            }
        }
        
        $session = session($data['email']);
        if (!empty($session) && (NOW_TIME - $session['create_time']) < 60) {
            $this->set_message(1024, "fail", "验证码发送过于频繁，请稍后再试");
            exit;
        }
        $email = $data['email'];
        $code = rand(100000, 999999);

        $bool = sendMail($data['email'], '<strong>' . $code . '<strong>');

        if ($bool) {
            session($email, ['code' => $code, 'create_time' => NOW_TIME]);
            $this->set_message(200, "success", "发送成功");
        } else {
            $this->set_message(1018, "fail", "发送失败，请检查邮箱地址是否正确");
        }
    }



    /**

     * 检查账号是否存在

     * @param $account

     * @return bool

     * author: xmy 280564871@qq.com

     */

    public function checkUserExist($account)

    {

        $map['account'] = $account;

        $data = M('user', 'tab_')->where($map)->find();

        if (empty($data)) {

            return true;

        } else {

            return false;

        }

    }


    public function smsCfg(){
        return array(
            'account'=>'C45691449',
            'password'=>'0f755fd404badef5991a53c559bac359',
        );
    }
    //将 xml数据转换为数组格式。
    public function xml_to_array($xml){
        $reg = "/<(\w+)[^>]*>([\\x00-\\xFF]*)<\\/\\1>/";
        if(preg_match_all($reg, $xml, $matches)){
            $count = count($matches[0]);
            for($i = 0; $i < $count; $i++){
                $subxml= $matches[2][$i];
                $key = $matches[1][$i];
                if(preg_match( $reg, $subxml )){
                    $arr[$key] = $this->xml_to_array( $subxml );
                }else{
                    $arr[$key] = $subxml;
                }
            }
        }
        return $arr;
    }
    /**
     * 以post的方式发送http请求，并返回http请求的结果
     * @param string $url
     * @param array $param
     * @return array|false    只返回文件内容，不包括http header
     */
    public function curlPost($curlPost,$url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
        $return_str = curl_exec($curl);
        curl_close($curl);
        return $return_str;
    }
    //新版发送短信
    public function sendSms(){
        $data = json_decode(base64_decode(file_get_contents("php://input")), true);
        $phone = $data['phone'];
        if($data['type']==1){  //判断手机号是否绑定
            $user=M('user','tab_')->field('id')->where(['phone'=>$phone])->find();
            if($user){
                $this->set_message(1098, "fail", "该手机号已被绑定");die;
            }
        }
        /// 产生手机安全码并发送到手机且存到session
        $rand = rand(100000, 999999);
        sdkchecksendcode($phone, C('sms_set.limit'),2); //验证限制
        $cfg = $this->smsCfg($phone,$rand);
        $url = 'http://106.wx96.com/webservice/sms.php?method=Submit';

        $post_data = "account={$cfg['account']}&password={$cfg['password']}&mobile=".$phone."&content=".rawurlencode("您的验证码是：".$rand."。请不要把验证码泄露给其他人。");
        $ret = $this->xml_to_array($this->curlPost($post_data, $url));

        if (isset($ret['SubmitResult']['code']) && $ret['SubmitResult']['code'] == 2) {
            $result['create_time'] = time();
            $result['pid'] = 0;
            $result['phone'] = $phone;
            $result['smsId'] = $ret['SubmitResult']['smsid'];
            $result['send_time'] =0;
            $result['send_status'] = $rand;
            $result['ratio'] = 0;
            $result['status'] = 0;
            $result['create_ip'] = get_client_ip();
            M('Short_message')->add($result);
            session($phone, array('code' => $rand, 'create_time' => NOW_TIME));
            echo base64_encode(json_encode(array('status' => 200, 'return_code' => $rand, 'return_msg' => '验证码发送成功')));
        } else {
            $this->set_message(1010, "fail", "验证码发送失败，请重新获取");
        }
    }
    /**
     *短信发送
     */
    public function send_sms()
    {
        $data = json_decode(base64_decode(file_get_contents("php://input")), true);
		//$data = json_decode(file_get_contents("php://input"), true);
        $phone = $data['phone'];
        if($data['type']==1){  //判断手机号是否绑定
            $user=M('user','tab_')->field('id')->where(['phone'=>$phone])->find();
            if($user){
                $this->set_message(1098, "fail", "该手机号已被绑定");die;
            }
        }
        /// 产生手机安全码并发送到手机且存到session
        $rand = rand(1000, 9999);
        //$param = $rand . "," . '1';
        if (get_tool_status("sms_set")) {
            $xigu = new Xigu(C('sms_set.smtp'));
			//验证短信是否频繁
            sdkchecksendcode($phone, C('sms_set.limit'),2);
            $result = json_decode($xigu->sendSM("C51695792","1b7c2885a57e47aaa872b069db5e3fbe", $phone, $rand), true);
            // result = json_decode($xigu->sendSM("HUOKXHVT7FNY","1NM3E7F5ITW2JH2G", $phone, $rand), true);
            $result['create_time'] = time();
            $result['pid'] = 0;
            $result['create_ip'] = get_client_ip();
            M('Short_message')->add($result);
            #TODO 短信验证数据
            if ($result['send_status'] == '000000') {
                session($phone, array('code' => $rand, 'create_time' => NOW_TIME));
                echo base64_encode(json_encode(array('status' => 200, 'return_code' => 'success', 'return_msg' => '验证码发送成功')));
            } else {
                $this->set_message(1010, "fail", "验证码发送失败，请重新获取");
            }
        } elseif (get_tool_status("alidayu")) {
            $xigu = new Xigu('alidayu');
            sdkchecksendcode($phone, C('alidayu.limit'),2);
            $result = $xigu->alidayu_send($phone, $rand);
            if ($result == false) {
                $this->set_message(1010, "fail", "验证码发送失败，请重新获取");
            }
            // 存储短信发送记录信息
            $result2['send_status'] = '000000';
            $result2['send_time'] = time();
            $result2['phone'] = $phone;
            $result2['create_time'] = time();
            $result2['pid'] = 0;
            $result2['create_ip'] = get_client_ip();
            M('Short_message')->add($result2);
            session($phone, array('code' => $rand, 'create_time' => NOW_TIME));
            echo base64_encode(json_encode(array('status' => 200, 'return_code' => 'success', 'return_msg' => '验证码发送成功')));
        } elseif (get_tool_status('jiguang')) {
            $xigu = new Xigu('jiguang');
            sdkchecksendcode($phone, C('jiguang.limit'),2);
            $result = $xigu->jiguang($phone, $rand, '');
            if ($result == false) {
                echo base64_encode(json_encode(array('status' => 1010, 'return_msg' => '发送失败，请重新获取')));
                exit;
            }
            // 存储短信发送记录信息
            $result2['send_status'] = '000000';
            $result2['send_time'] = time();
            $result2['phone'] = $phone;
            $result2['create_time'] = time();
            $result2['pid'] = 0;
            $result2['create_ip'] = get_client_ip();
            M('Short_message')->add($result2);
            session($phone, array('code' => $rand, 'create_time' => NOW_TIME));
            echo base64_encode(json_encode(array('status' => 200, 'return_code' => 'success', 'return_msg' => '验证码发送成功')));
        } elseif (get_tool_status("alidayunew")) {
            $xigu = new Xigu('alidayunew');
            sdkchecksendcode($phone, C('alidayunew.limit'),2);
            $result = $xigu->alidayunew_send($phone, $rand);
            if ($result == false) {
                $this->set_message(1010, "fail", "验证码发送失败，请重新获取");
            }
            // 存储短信发送记录信息
            $result2['send_status'] = '000000';
            $result2['send_time'] = time();
            $result2['phone'] = $phone;
            $result2['create_time'] = time();
            $result2['pid'] = 0;
            $result2['create_ip'] = get_client_ip();
            M('Short_message')->add($result2);
            session($phone, array('code' => $rand, 'create_time' => NOW_TIME));
            echo base64_encode(json_encode(array('status' => 200, 'return_code' => 'success', 'return_msg' => '验证码发送成功')));
        } else {
            $this->set_message(1008, "fail", "没有配置短信发送");
        }
    }



    /**

     *用户基本信息

     */

    public function user_info()

    {

        C(api('Config/lists'));

        $user = json_decode(base64_decode(file_get_contents("php://input")), true);

        $model = M("user", "tab_");

        $data = array();

        switch ($user['type']) {

            case 0:

                $data = $model

                    ->field("account,nickname,phone,balance,bind_balance,game_name,register_way,age_status,idcard,real_name")

                    ->join("INNER JOIN tab_user_play ON tab_user.id = tab_user_play.user_id and tab_user.id = {$user['user_id']} and tab_user_play.game_id = {$user['game_id']}")

                    ->find();

                break;

            default:

                $map['account'] = $user['user_id'];

                $data = $model->field("id,account,nickname,phone,balance,age_status,idcard,real_name")->where($map)->find();

                break;

        }

        if (empty($data)) {

            $this->set_message(1004, "fail", "用户不存在或被禁用");

        }

        $data['phone'] = empty($data["phone"]) ? " " : $data["phone"];

        $data['status'] = 200;
        $data['register_type'] = $data['register_way'];


        echo base64_encode(json_encode($data));

    }



    /**

     *用户平台币充值记录

     */

    public function user_deposit_record()

    {

        $data = json_decode(base64_decode(file_get_contents("php://input")), true);

        $map["user_id"] = $data["user_id"];

        $map["pay_status"] = 1;

        $deposit = M("deposit", "tab_")->field('pay_way,pay_amount,create_time')->where($map)->order('id desc')->page($data['index'], 10)->select();

        $count = M("deposit", "tab_")->field('pay_way,pay_amount,create_time')->where($map)->count();

        if (empty($deposit)) {

            echo base64_encode(json_encode(array("status" => 1061, "return_code" => "fail", "return_msg" => "暂无记录")));

            exit();

        }

        $return_data['status'] = 200;

        $return_data['total'] = $count;

        $return_data['data'] = $deposit;

        echo base64_encode(json_encode($return_data));

    }



    /**

     *用户领取礼包记录

     */

    public function user_gift_record()

    {

        $data = json_decode(base64_decode(file_get_contents("php://input")), true);
        $map["user_id"] = $data["user_id"];

        $map["game_id"] = $data["game_id"];

        $gift = M("GiftRecord", "tab_")

            ->field("tab_gift_record.game_id,tab_gift_record.game_name,tab_giftbag.giftbag_name ,tab_giftbag.digest,tab_gift_record.novice,tab_gift_record.status,tab_giftbag.start_time,tab_giftbag.end_time")

            ->join("LEFT JOIN tab_giftbag ON tab_gift_record.gift_id = tab_giftbag.id where user_id = {$data['user_id']} and tab_gift_record.game_id = {$data['game_id']}")

            ->select();

        if (empty($gift)) {

            echo base64_encode(json_encode(array("status" => 1061, "return_code" => "fail", "return_msg" => "暂无记录")));

            exit();

        }

        foreach ($gift as $key => $val) {

            $gift[$key]['icon'] = $this->set_game_icon($val[$key]['game_id']);

            $gift[$key]['now_time'] = NOW_TIME;

        }



        $return_data['status'] = 200;

        $return_data['data'] = $gift;

        echo base64_encode(json_encode($return_data));

    }



    /**

     *用户平台币(绑定和非绑定)

     */

    public function user_platform_coin()

    {

        $data = json_decode(base64_decode(file_get_contents("php://input")), true);

        C(api('Config/lists'));
        $user_play = M("UserPlay", "tab_");

        $platform_coin = array();

        $user_data = array();

        #非绑定平台币信息

        $user_data = get_user_entity($data["user_id"]);

        $platform_coin['status'] = 200;

        $platform_coin["balance"] = $user_data["balance"]?:0;

        #绑定平台币信息

        $map["user_id"] = $data["user_id"];

        $map["game_id"] = $data["game_id"];

        $user_data = $user_play->where($map)->find();

        $platform_coin["bind_balance"] = $user_data["bind_balance"]?:0;
        echo base64_encode(json_encode($platform_coin));

    }



    //判断帐号是否存在

    public function account_exist()

    {

        $data = json_decode(base64_decode(file_get_contents("php://input")), true);
        $map['account'] = $data['account'];

        $user = M('user', 'tab_')->where($map)->find();

        if (empty($user)) {

            echo json_encode(array('status' => 1004, 'msg' => '帐号不存在'));

        } else {

            echo json_encode(array('status' => 200));

        }

    }



    //解绑手机

    public function user_phone_unbind()

    {

        $data = json_decode(base64_decode(file_get_contents("php://input")), true);

        $this->sms_verify($data['phone'], $data['code']);

        $map['id'] = $data['user_id'];

        $user = M('user', 'tab_')->where($map)->setField('phone', "");

        if ($user) {

            echo base64_encode(json_encode(array('status' => 200, 'return_msg' => '解绑成功')));



        } else {

            echo base64_encode(json_encode(array('status' => -1, 'return_msg' => '解绑失败')));



        }

    }



    //常见问题

    public function get_problem()

    {

        $data = M('document')

            ->join("left join sys_category c on c.name='FAQ'")

            ->where('c.id = sys_document.category_id AND sys_document.status = 1')

            ->field('sys_document.id,sys_document.title,sys_document.description')

            ->select();

        echo base64_encode(json_encode($data));

    }



    //留言

    public function get_question()

    {

        $request = json_decode(base64_decode(file_get_contents("php://input")), true);

        $type = $request['type'];

        $user_id = $request['user_id'];

        $content = $request['content'];

        $map['user_id'] = $user_id;

        $data = M('question', 'tab_')->where($map)->find();

        if ($type == 1) {

            if (empty($data)) {

                $data['create_time'] = time();

                $question[time()] = $content;

                $data['question'] = json_encode($question);

                $data['user_id'] = $user_id;

                $data['account'] = get_user_entity($user_id)['account'];

                M('question', 'tab_')->where($map)->add($data);

            } else {

                $question = json_decode($data['question'], true);

                $question[time()] = $content;

                $data['question'] = json_encode($question);

                $data['account'] = get_user_entity($user_id)['account'];

                M('question', 'tab_')->where($map)->save($data);

            }

        }

        $question = json_decode($data['question'], true);

        foreach ($question as $k => $v) {

            $res[$k][1] = $v;

        }

        $answer = json_decode($data['answer'], true);

        foreach ($answer as $key => $value) {

            $res[$key][2] = $value;

        }

        ksort($content);

        echo base64_encode(json_encode($res));

    }



    //获取开启的支付

    public function get_pay_server()

    {

        $request = json_decode(base64_decode(file_get_contents("php://input")), true);

        if (pay_set_status('wei_xin') == 1 || pay_set_status('goldpig') == 1) {
                $wx_game = 1;

        } 

        if (pay_set_status('alipay') == 1  || (pay_set_status('goldpig') == 1 && C('ZFB_TYPE') == 1)) {

            if (get_game_appstatus($request['game_id'])) {

                $zfb_game = 1;

            } else {

                $zfb_game = 0;

            }

        } else {

            $zfb_game = 0;

        }

        // if (pay_set_status('jubaobar') == 1) {

        //     $jby_game = 1;

        // } else {

        //     $jby_game = 0;

        // }
        $jby_game = 0;
        // if (pay_set_status('jft') == 1) {

        //     $jft_game = 1;

        // } else {

        //     $jft_game = 0;

        // }
        $jft_game = 0;

        $zfb_type = empty(C('ZFB_TYPE'))?0:C('ZFB_TYPE');
        echo base64_encode(json_encode(array('status' => 200, 'wx_game' => $wx_game, 'zfb_game' => $zfb_game, 'jby_game' => $jby_game, 'jft_game' => $jft_game,'zfb_type'=>$zfb_type)));

    }



    //获取渠道折扣

    public function get_user_discount()

    {

        $request = json_decode(base64_decode(file_get_contents("php://input")), true);

        $game_id = $request['game_id'];

        $user_id = $request['user_id'];

        $user = get_user_entity($user_id);

        $promote_id = $user['promote_id'];

        $discount = $this->get_discount($game_id, $promote_id, $user_id);

        echo base64_encode(json_encode($discount));

    }





    /**

     * 实名认证信息   获得传递过来的UID，返回该玩家是否已经通过审核

     * @return mixed

     */

    public function return_age()

    {

        $request = json_decode(base64_decode(file_get_contents("php://input")), true);

        if (empty($request)) {

            $this->set_message(1001, "fail", "操作数据不能为空");

        }



        $mmm['account'] = $request['account'];

        $user = M('User', 'tab_')->where($mmm)->find();


        //添加登录记录

        $da = array(

            'user_id' => $user['id'],

            'user_account' => $user['account'],

            'user_nickname' => $user['nickname'],

            'game_id' => $request['game_id'],

            'game_name' => get_game_name($request["game_id"]),

            'server_id' => null,

            'type' => 1,

            'server_name' => null,

            'login_time' => NOW_TIME,

            'login_ip' => get_client_ip(),

            'sdk_version' => $request['sdk_version'],

            'promote_id' => $user['promote_id']

        );

        //$denglu = M('UserLoginRecord', 'tab_')->add($da);
        $uloginrecord= M('UserLoginRecord','tab_')
            ->field('id,login_time,down_time')->where(['user_id'=>$user['id'],'game_id'=>$request['game_id'],'sdk_version'=>$request['sdk_version'],'type'=>1])->order('id desc')->find();

        if(empty($uloginrecord) || ($uloginrecord['login_time']<1 && $uloginrecord['down_time']>0)) {
            $denglu = M('UserLoginRecord', 'tab_')->add($da);
        } else {
            $denglu= $uloginrecord['id'];
        }

        $data = C('sy_age_prevent');
        $data['on-off'] = C('sy_age.status')==1?0:1;

        $data['hours'] = C('sy_age_prevent.hours')==null?1:C('sy_age_prevent.hours'); //为空转换成1

        $data['contents_off'] =  C('sy_age.tip');;



        unset($data['status']);

        $res['date'] = $data;

        $where['account'] = $request['account'];

        $re = M('User', 'tab_')->field('age_status')->where($where)->find();

        if ($re) {

            $data['age_status'] = $re['age_status'];

        } else {

            $data['age_status'] = -1;

        }

        //计算用户的游戏时间 和 休息时间

        $map['user_id'] = $request['user_id'];

        $map['login_time'] = period(0);

        $map['is_down'] = 0;

        $map['status'] = 0;

        $map['type'] = 1;

        $map2['user_id'] = $request['user_id'];

        $map2['login_time'] = period(0);

        $map2['is_down'] = 1;

        $map2['status'] = 0;

        $map2['type'] = 1;

        $login_ = M('UserLoginRecord', 'tab_')->field('login_time')->where($map)->order('login_time ASC')->select();

        $down_ = M('UserLoginRecord', 'tab_')->field('down_time')->where($map2)->order('down_time ASC')->select();

        $login_count = count($login_);

        $down_count = count($down_);

        $play = 0;

        $down = 0;

        if ($login_count >= $down_count && $down_count != 0) {

            for ($i = 0; $i < $down_count; $i++) {

                $play += $down_[$i]['down_time'] - $login_[$i]['login_time'];

                if ($down_[$i + 1]['down_time'] == 0 && $login_[$i + 1]['login_time'] != 0) {

                    $play += time() - $login_[$i + 1]['login_time'];

                }

                if ($login_[$i + 1]['login_time'] != 0) {

                    $down += $login_[$i + 1]['login_time'] - $down_[$i]['down_time'];

                }

            }

        }

        if ($down_count == 0 && $login_count > 0) {

            $play += time() - $login_[0]['login_time'];

        }



        $data['play_time'] = floor($play / 60);

        $data['down_time'] = floor($down / 60);



        //累计在线时间大于最长在线时间（两个未满18岁防沉迷时间的和） 继续在线就算在休息时间里面了

        if ($data['play_time'] / 60 >= ($data['hours_off_one'] + $data['hours_off_two'])) {

            $data['down_time'] += $data['play_time'] - $data['hours_off_one'] * 60 - $data['hours_off_two'] * 60;

            $data['play_time'] = $data['hours_off_one'] * 60 + $data['hours_off_two'] * 60;

        }



        //一旦游戏时间满足恢复时间  两种时间全部清零

        if ($data['down_time'] - $data['hours_cover'] * 60 >= 0) {

            $where2['user_id'] = $request['user_id'];

            $where2['login_time'] = period(0);

            $mmp['status'] = 200;

            M('UserLoginRecord', 'tab_')->where($where2)->save($mmp);

            $deng['id'] = $denglu;

            $de['status'] = 0;

            M('UserLoginRecord', 'tab_')->where($deng)->save($de);

        }



        echo base64_encode(json_encode(array('status' => 200, 'data' => $data)));

    }



    /**

     * 更改身份证账户   获得传递过来的UID，idcard，name进行更改数据库

     * @return mixed

     */

    public function idcard_change()

    {

        C(api('Config/lists'));

        $user = json_decode(base64_decode(file_get_contents("php://input")), true);

        if (empty($user['user_id']) || empty($user['idcard']) || empty($user['real_name'])) {

            $this->set_message(1066, "fail", "用户数据异常");

        }

        $map['id'] = $user['user_id'];

        $data['idcard'] = $user['idcard'];

        $data['real_name'] = $user['real_name'];



        if(isset($data['idcard'])){
            if(strlen($data['idcard'])!=18){
                $this->set_message(1086, "fail", "身份证号码填写不正确！");
            }
            if(substr($data['idcard'],-1)==='X'){
                $this->set_message(1086, "fail", "身份证号码填写不正确，如有字母请小写");
            }
            $checkidcard = new \Think\Checkidcard();

            $invidcard=$checkidcard->checkIdentity($data['idcard']);

            if(!$invidcard){

                $this->set_message(1086, "fail", "身份证号码填写不正确！");

            }

            $cardd=M('User','tab_')->where(array('idcard'=>$data['idcard']))->find();

            if($cardd){

                $this->set_message(1087, "fail", "身份证号码已被使用！");

            }

        }



        //身份证认证

        if (C('tool_age.status') == 0) {
            $data['age_status'] = 2;
        } else {

            $re = age_verify($data['idcard'], $data['real_name']);

            switch ($re) {

                case -1:

                    $this->set_message(1067, "fail", "短信数量已经使用完！");

                    break;

                case -2:

                    $this->set_message(1068, "fail", "连接接口失败");

                    break;

                case 0:

                    $this->set_message(1069, "fail", "用户数据不匹配");

                    break;

                case 1://成年

                    $data['age_status'] = 2;

                    break;

                case 2://未成年

                    $data['age_status'] = 3;

                    break;

                default:

            }

        }

        $return = M('User', 'tab_')->where($map)->save($data);

        if (!$return) {

            $this->set_message(1070, "fail", "用户数据更新失败");

        }

        $data['status'] = 200;

        echo base64_encode(json_encode($data));

    }



    /**

     * 通过用户的user_id 返回用户的下线时间 必要user_id  可选game_id role_id

     */

    public function down_time()

    {

        C(api('Config/lists'));

        $user = json_decode(base64_decode(file_get_contents("php://input")), true);

        $map['user_id'] = $user['user_id'];

        $map['login_time'] = 0;

        if (!empty($user['game_id'])) {

            $map['game_id'] = $user['game_id'];

        }

        if (!empty($user['role_id'])) {

            $map['role_id'] = $user['role_id'];

        }

        $return = M('UserLoginRecord', 'tab_')->where($map)->limit(1)->order('id DESC')->select();

        if (empty($return)) {

            $this->set_message(0, "fail", "该用户没有下线记录");

        }

        echo base64_encode(json_encode($return));

    }





    /**

     * 接口  获得用户的下线数据并且存到数据库大众

     */

    public function get_down_time()

    {

        C(api('Config/lists'));

        $request = json_decode(base64_decode(file_get_contents("php://input")), true);

        if (empty($request)) {

            $this->set_message(0, "fail", "参数错误");

        }

        $mmm['account'] = $request['account'];

        $user = M('User', 'tab_')->where($mmm)->find();

        if (!$user) {

            $this->set_message(0, "fail", "找不到该用户!");

        }

        $da = array(

            'user_id' => $user['id'],

            'user_account' => $user['account'],

            'user_nickname' => $user['nickname'],

            'promote_id' => $user['promote_id'],

            'game_id' => $request['game_id'],

            'game_name' => get_game_name($request["game_id"]),

            'server_id' => null,

            'type' => 1,

            'server_name' => null,

            'down_time' => NOW_TIME,

            'login_ip' => get_client_ip(),

            'sdk_version' => $request['sdk_version'],

            'is_down'  => 1,

        );

        $return = M('UserLoginRecord', 'tab_')->add($da);

        if ($return) {

            echo base64_encode(json_encode(array('status' => 200, 'return_msg' => '数据新增成功！')));

        } else {

            $this->set_message(0, "fail", "数据新增失败!");

        }

    }
    
    /**
     * 退出按钮
     * 1:开启  0:关闭
     */
    public function loginout_status(){
        $status = C('LOGINOUT_STATUS');
        echo base64_encode(json_encode(array('status' => 200, 'data' => $status)));
    }

}

