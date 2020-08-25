<?php
namespace Admin\Controller;
use Think\Controller;
use Org\XiguSDK\Xigu;
class PhoneController extends Controller {

	protected function _initialize(){
        /* 读取站点配置 */
        $config = api('Config/lists');
        C($config); //添加配置
    }
	/**
     * [手机号不被使用才可以]
     * @param null $phone
     * @param int $delay
     * @param int $way
     * @param string $type
     * @author 幽灵[syt]
     */
    public function not_account_telsvode($phone = null, $delay = 10, $way = 1, $type = "phone"){
        // $map['mobile'] = $phone;
        // $map['id'] = C('USER_ADMINISTRATOR');
        // $data = M('UcenterMember')->where($map)->getField('mobile');
        // if($data!=''){
        //     $phone = $data;
        // }
        $this->telsvcode($phone, $delay = 10, $way = 1, $type = "phone");
    }

    // 发送手机安全码
    public function telsvcode($phone = null, $delay = 10, $way = 1, $type = "phone")
    {
        /// 产生手机安全码并发送到手机且存到session
        $rand = rand(100000, 999999);
        $param = $rand . "," . $delay;
        if (get_tool_status("sms_set")) {
            checksendcode($phone, C('sms_set.limit'));
            $xigu = new Xigu(C('sms_set.smtp'));
            $result = json_decode($xigu->sendSM(C('sms_set.smtp_account'), $phone, C('sms_set.smtp_port'), $param), true);
            if ($result['send_status'] != '000000') {
                echo json_encode(array('status' => 0, 'msg' => '发送失败，请重新获取'));
                exit;
            }
        } elseif (get_tool_status("alidayu")) {
            checksendcode($phone, C('alidayu.limit'));
            $xigu = new Xigu('alidayu');
            $result = $xigu->alidayu_send($phone, $rand, $delay);
            $result['send_time'] = time();
            if ($result == false) {
                echo json_encode(array('status' => 0, 'msg' => '发送失败，请重新获取'));
                exit;
            }
        } elseif (get_tool_status('jiguang')) {
            checksendcode($phone, C('jiguang.limit'));
            $xigu = new Xigu('jiguang');
            $result = $xigu->jiguang($phone, $rand, $delay);
            $result['send_time'] = time();
            if ($result == false) {
                echo json_encode(array('status' => 0, 'msg' => '发送失败，请重新获取'));
                exit;
            }
        } elseif (get_tool_status('alidayunew')) {
            checksendcode($phone, C('alidayunew.limit'));
            $xigu = new Xigu('alidayunew');
            $result = $xigu->alidayunew_send($phone, $rand, $delay);
            $result['send_time'] = time();
            if ($result == false) {
                echo json_encode(array('status' => 0, 'msg' => '发送失败，请重新获取'));
                exit;
            }
        } elseif (get_tool_status('alidayumsg')) {
            checksendcode($phone, C('alidayumsg.limit'));
            $xigu = new Xigu('alidayumsg');
            $result = $xigu->alidayumsg_send($phone, $rand, $delay);
            $result['send_time'] = time();
            if ($result == false) {
                echo json_encode(array('status' => 0, 'msg' => '发送失败，请重新获取'));
                exit;
            }
        } else {
            echo json_encode(array('status' => 0, 'msg' => '没有配置短信发送'));
            exit;
        }
        // 存储短信发送记录信息
        $result['send_status'] = '000000';
        $result['phone'] = $phone;
        $result['create_time'] = time();
        $result['pid'] = 0;
        $result['create_ip'] = get_client_ip();
        $r = M('Short_message')->add($result);
        $telsvcode['code'] = $rand;
        $telsvcode['phone'] = $phone;
        $telsvcode['time'] = time();
        $telsvcode['delay'] = $delay;
        session('admin_telsvcode', $telsvcode);
        if ($way == 0) {
            echo json_encode(array(
                    'status' => 1,
                    'msg' => "注册成功！请在" . $delay . "分钟内完成<br/>验证码已经发送到 $phone",
                    "type" => $type,
                    'data' => $telsvcode)
            );
        } else if ($way == 1) {
            echo json_encode(array('status' => 1, 'msg' => '验证码已发送', "type" => $type, 'data' => $telsvcode));
        } else if ($way == 2) {
            echo json_encode(array('status' => 1, 'msg' => "请输入验证码，验证码已经发送到 $phone", "type" => $type, 'data' => $telsvcode));
        }
    }
    

    /**
     *短信验证
     */

    public function check_tel_code($account = '', $verify = '', $way = 1)
    {
        $telcode = session('admin_telsvcode');
        if (!$telcode) {
            return -1;
        }
        if ($account != $telcode['phone']) {
            return -1;
        }
        $time = (time() - $telcode['time']) / 60;
        if ($time > $telcode['delay']) {
            session('admin_telsvcode', null);
            unset($telcode);
            return -2;
        }
        if ($telcode['code'] == $verify) {
            //unsetcode 注销
            session('admin_telsvcode', null);//使用后销毁
            switch ($way) {
                case 1:
                    return 1;
                    break;
            }
        } else {
            return -3;
        }
    }
}