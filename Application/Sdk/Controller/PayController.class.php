<?php
namespace Sdk\Controller;

use Think\Controller;
use Common\Api\GameApi;
use Org\WeiXinSDK\Weixin;
use Org\HeepaySDK\Heepay;
use Org\UcenterSDK\Ucservice;
use Common\Api\PayApi;

class PayController extends BaseController
{

    private function pay($param = array())
    {
        $table = $param['code'] == 1 ? "spend" : "deposit";
        $prefix = $param['code'] == 1 ? "SP_" : "PF_";
        $out_trade_no = $prefix . date('Ymd') . date('His') . sp_random_string(4);
        $user = get_user_entity($param['user_id']);
        $pay = new \Think\Pay($param['apitype'], C($param['config']));
        $discount = $this->get_discount($param['game_id'], $user['promote_id'], $param['user_id']);
        $discount = $discount['discount'];
         if(!is_check_apply_promote($param['game_id'],$user['promote_id'])){
            $user['promote_id']=0;
            $user['promote_account']="自然注册";
        }
        $vo = new \Think\Pay\PayVo();
        $vo->setBody("充值记录描述")
            ->setFee($param['price'])//支付金额 
            ->setTitle($param['title'])
            ->setBody($param['body'])
            ->setOrderNo($out_trade_no)
            ->setRatio(get_game_selle_ratio($param["game_id"]))
            ->setService($param['server'])
            ->setSignType($param['signtype'])
            ->setPayMethod('mobile')
            ->setTable($table)
            ->setPayWay($param['payway'])
            ->setGameId($param['game_id'])
            ->setGameName(get_game_name($param['game_id']))
            ->setGameAppid($param['game_appid'])
            ->setServerId(0)
            ->setRoleName($param['game_player_name'])
            ->setServerName($param['server_name'])
            ->setUserId($param['user_id'])
            ->setAccount($user['account'])
            ->setUserNickName($user['nickname'])
            ->setPromoteId($user['promote_id'])
            ->setPromoteName($user['promote_account'])
            ->setExtend($param['extend'])
            ->setSdkVersion($param['sdk_version'])
            ->setDiscount($discount);
            return $pay->buildRequestForm($vo);
        

    }


    /**
     *支付宝移动支付
     */
    public function alipay_pay()
    {
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $request = json_decode(base64_decode(file_get_contents("php://input")), true);
        
        C(api('Config/lists'));
        if ($request['price'] < 0) {
            $this->set_message(1011, "fail", "充值金额有误");
        }
        if(empty(C("alipay.partner")) || empty(C("alipay.email")) || empty(C("alipay.key")) || empty(C("alipay.appid"))){
            $this->set_message(1079, "faill", "未设置支付参数");
        }
        $game_set_data = get_game_set_info($request['game_id']);
        $request['apitype'] = "alipay";
        $request['config'] = "alipay";
        $request['signtype'] = "RSA2";
        $request['server'] = "mobile.securitypay.pay";
        $request['payway'] = 1;
        $data = $this->pay($request);
        $md5_sign = $this->encrypt_md5(base64_encode($data['arg']), $game_set_data["access_key"]);
        $data = array('status' => 200, "orderInfo" => base64_encode($data['arg']), "out_trade_no" => $data['out_trade_no'], "order_sign" => $data['sign'], "md5_sign" => $md5_sign);
        echo base64_encode(json_encode($data));
    }


    /**
     *微信
     */
    public function outher_pay()
    {
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组 
        $request = json_decode(base64_decode(file_get_contents("php://input")), true);
        
        C(api('Config/lists'));
        if ($request['price'] < 0) {
            $this->set_message(1011, "fail", "充值金额有误");
        }      
        $game_set_data = get_game_set_info($request['game_id']);
        $table = $request['code'] == 1 ? "spend" : "deposit";
        $prefix = $request['code'] == 1 ? "SP_" : "PF_";
        $request['pay_order_number'] = $prefix . date('Ymd') . date('His') . sp_random_string(4);
        $request['pay_way'] = 3;
        $request['pay_status'] = 0;
        $request['spend_ip'] = get_client_ip();
        $weixn = new Weixin();
        //折扣
        $user = get_user_entity($request['user_id']);
        $discount = $this->get_discount($request['game_id'], $user['promote_id'], $request['user_id']);
        $discount = $discount['discount'];
        if($prefix=='PF_'){
            $pay_amount = $request['price']; 
        }else{
            $pay_amount = $discount * $request['price'] / 10; 
        }
        $is_pay = json_decode($weixn->weixin_pay($request['title'], $request['pay_order_number'], $pay_amount, 'APP', 2,$request['game_id']), true);
        if ($is_pay['status'] === 1) {
            if ($request['code'] == 1) {
                $this->add_spend($request);
            } else {
                $this->add_deposit($request);
            }
            $json_data['appid'] = $is_pay['appid'];
            $json_data['partnerid'] = $is_pay['mch_id'];
            $json_data['prepayid'] = $is_pay['prepay_id'];
            $json_data['noncestr'] = $is_pay['noncestr'];
            $json_data['timestamp'] = $is_pay['time'];
            $json_data['package'] = "Sign=WXPay";
            $json_data['sign'] = $is_pay['sign'];
            $json_data['game_pay_appid'] = $game_set_data['game_pay_appid'];
            $json_data['status'] = 200;
            $json_data['return_msg'] = "下单成功";
            $json_data['wxtype'] = "wx";
            echo base64_encode(json_encode($json_data));
        }

    }

    

    /**
     *平台币支付
     */
    public function platform_coin_pay()
    {
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $request = json_decode(base64_decode(file_get_contents("php://input")), true);
       
        #记录信息
        if ($request['price'] < 0) {
            $this->set_message(1011, "fail", "充值金额有误");
        }
        $out_trade_no = "SP_" . date('Ymd') . date('His') . sp_random_string(4);
        $request['order_number'] = $out_trade_no;
        $request['pay_order_number'] = $out_trade_no;
        $request['out_trade_no'] = $out_trade_no;
        $request['title'] = $request['title'];
        $request['pay_status'] = 1;
        $request['pay_way'] = 0;
        $request['spend_ip'] = get_client_ip();
        $user_entity = get_user_entity($request['user_id']);
        $discount_arr = $this->get_discount($request['game_id'], $user_entity['promote_id'], $request['user_id']);
        $discount = $discount_arr['discount'];
        $result = false;
        switch ($request['code']) {
            case 1:#非绑定平台币
                $user = M("user", "tab_");
                $real_price = $request['price'] * $discount / 10;
                if ($user_entity['balance'] < $real_price) {

                    $data = array('discount'=>$discount,"real_price"=>$real_price,'balance'=>$user_entity['balance']);

                    echo base64_encode(json_encode(array("status" => 1076,"return_code" => "fail", "return_msg" => "余额不足")));
                    exit();
                }
                #扣除平台币
                $user->where("id=" . $request["user_id"])->setDec("balance", $real_price);
                #TODO 添加绑定平台币消费记录
                $result = $this->add_spend($request);
                #检查返利设置
                $this->set_ratio($request['pay_order_number']);
                $PayApi = new PayApi();
                $spend_data = M('spend','tab_')->where(['id'=>$result])->find();
                $PayApi->buyshoppoint($spend_data);
                break;
            case 2:#绑定平台币
                $request['pay_way'] = -1;
                $user_play = M("UserPlay", "tab_");
                $user_play_map['user_id'] = $request['user_id'];
                $user_play_map['game_id'] = $request['game_id'];
                $user_play_data = $user_play->where($user_play_map)->find();
                
                if ($user_play_data['bind_balance'] < $request['price']) {
                    echo base64_encode(json_encode(array("status" =>1076, "return_code" => "fail", "return_msg" => "余额不足")));
                    exit();
                }
                #扣除平台币
                $user_play->where($user_play_map)->setDec("bind_balance", $request['price']);
                #TODO 添加绑定平台币消费记录
                $result = $this->add_spend($request);
                #检查返利设置
                $this->set_ratio($request['pay_order_number'],2);
                break;
            default:
                echo base64_encode(json_encode(array("status" => 1081, "return_code" => "fail", "return_msg" => "支付方式不明确")));
                exit();
                break;
        }
        $game = new GameApi();
        $game->game_pay_notify($request, $request['code']);
        if ($result) {
            echo base64_encode(json_encode(array("status" => 200, "return_code" => "success", "return_msg" => "支付成功", "out_trade_no" => $out_trade_no)));
        } else {
            echo base64_encode(json_encode(array("status" => 1078, "return_code" => "fail", "return_msg" => "支付失败")));
        }
    }

    /**
     *支付验证
     */
    public function pay_validation()
    {
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $request = json_decode(base64_decode(file_get_contents("php://input")), true);
        $out_trade_no = $request['out_trade_no'];
        $pay_where = substr($out_trade_no, 0, 2);
        $result = 0;
        $map['pay_order_number'] = $out_trade_no;
        switch ($pay_where) {
            case 'SP':
                $data = M('spend', 'tab_')->field('pay_status')->where($map)->find();
                $result = $data['pay_status'];
                break;
            case 'PF':
                $data = M('deposit', 'tab_')->field('pay_status')->where($map)->find();
                $result = $data['pay_status'];
                break;
            case 'AG':
                $data = M('agent', 'tab_')->field('pay_status')->where($map)->find();
                $result = $data['pay_status'];
                break;
            default:
                exit('accident order data');
                break;
        }
        if ($result) {
            echo base64_encode(json_encode(array("status" => 200, "return_code" => "success", "return_msg" => "支付成功")));
            exit();
        } else {
            echo base64_encode(json_encode(array("status" => 1078, "return_code" => "fail", "return_msg" => "支付失败")));
            exit();
        }
    }

    /**
     *sdk客户端显示支付
     */
    public function payShow()
    {
        $map['type'] = 1;
        $map['status'] = 1;
        $data = M("tool", "tab_")->where($map)->select();
        if (empty($data)) {
            echo base64_encode(json_encode(array("status" => 1082, "return_code" => "fail", "return_msg" => "暂无数据")));
            exit();
        }
        foreach ($data as $key => $value) {
            $pay_show_data[$key]['mark'] = $value['name'];
            $pay_show_data[$key]['title'] = $value['title'];
        }
        echo base64_encode(json_encode(array("status" => 200, "return_code" => "fail", "return_msg" => "成功", "pay_show_data" => $pay_show_data)));
        exit();
    }



}
