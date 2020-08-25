<?php
namespace Sdk\Controller;
use Think\Controller\RestController;

class UnionController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Methods:POST,OPTIONS');
        header('Access-Control-Allow-Headers:x-requested-with,content-type,usertoken,pid');
    }
    public function pay(){
        //充值元
        $param = json_decode(base64_decode(file_get_contents("php://input")), true);
        $prefix = $param['code'] == 1 ? "SP_" : "PF_";
        $param['order_number'] = $prefix.date('Ymd').date('His').sp_random_string(4);
        $money = isset($param['price'])  ? ($param['price'] * 100) : 100; //支付金额

        $order_number = $this->add_spend($param);
        if(!$order_number) {
            $this->set_message(999, "fail", '订单生成失败');
        }

        $pay_order_number="1017".date('YmdHis').$order_number;
        $time = time(); //当前时间
        $APPID = "10037e6f66f2d0f901672aa27d690006"; //APPID
        $APPKEY = "47ace12ae3b348fe93ab46cee97c6fde"; //APPKEY
        $authType = "OPEN-FORM-PARAM";  //认证方式
        $dataTime = date("YmdHis",$time); //时间戳
        $randRum = md5(uniqid(microtime(true),true)); //随机数
        $sellerID = "898310148160568";  //H5商户号
        $devID ="88880001";  //终端号
        $sellType = "H5DEFAULT"; //业务类型

        $notifyUrl = "https://".$_SERVER ['HTTP_HOST']."/callback.php/union/notify/apitype/union/methodtype/notify/order_id/".$pay_order_number; //支付成功回调地址
//        $url = "http://58.247.0.18:29015/v1/netpay/uac/order?"; //银联地址
        $url = 'http://58.247.0.18:29015/v1/netpay/trade/h5-pay?';//H5支付

        $arr = array();
        $arr['authorization'] = $authType;//写死的
        $arr['appId'] = $APPID;
        $arr['nonce'] = $randRum;
        $arr['timestamp'] = $dataTime;//yyyyMMddHHmmss
        $pData = array();

        $pData['requestTimestamp'] = date("Y-m-d H:i:s",$time);//报文请求时间 字符串是格式yyyy-MM-dd HH:mm:ss
        $pData['merOrderId'] = $pay_order_number;//商户订单号
        $pData['mid'] = $sellerID;//商户号
        $pData['tid'] = $devID;//终端号
        $pData['instMid'] = $sellType;//业务类型
        $pData['totalAmount'] = $money;//支付金额(分)
        $pData['notifyUrl'] = $notifyUrl;

        $arr['content'] = json_encode($pData);
        $jmContent = bin2hex(hash('sha256', $arr['content'], true));//内容加密
        $sign = base64_encode(hash_hmac('sha256',$APPID.$dataTime.$randRum.$jmContent, $APPKEY, true));
        $arr['signature'] = $sign;
        $params = http_build_query($arr);
        $sUrl = $url.$params;

        $date = date('Ymd');
        error_log(print_r([$arr,$sUrl,date('Y-m-d H:i:s')],true),3,"pay-{$date}");

        exit(base64_encode(json_encode(array('status' => 200, 'url' => $sUrl, 'return_msg' => 'success'))));
    }
    /**
     * 创建订单
     * @param $data
     * @return mixed
     */
    public function add_spend($data){
        $spend = M("spend","tab_");
        $spend_data['user_id']          = $data['user_id'];
        $spend_data['user_account']     = $data['account'];
        $spend_data['user_nickname']    = isset($data['nick']) ? $data['nick'] : '';
        $spend_data['game_id']          = isset($data['game_id']) ? $data['game_id'] : '';
        $spend_data['game_appid']       = isset($data['game_appid']) ? $data['game_appid'] : '';
        $spend_data['game_name']        = isset($data['game_name']) ? $data['game_name'] : '';
        $spend_data['server_id']        = isset($data['server_id']) ? $data['server_id'] : 0;
        $spend_data['server_name']      = isset($data['server_name']) ? $data['server_name'] : '';
        $spend_data['game_player_id']   = isset($data['game_player_id']) ? $data['game_player_id'] : 0;
        $spend_data['game_player_name'] = isset($data['game_player_name']) ? $data['game_player_name'] : '';

        //折扣
        $user = get_user_entity($data['user_id']);
        $discount = $this->get_discount($data['game_id'],$user['promote_id'],$data['user_id']);
        $discount = $discount['discount'];
        if($data['code'] == 1){
            $pay_amount = $data['price'];
        }else{
            $pay_amount = $discount * $data['price'] / 10;
        }
        $spend_data['pay_amount']       = $pay_amount;
        $spend_data['promote_id']       = isset($user['promote_id']) ? $user['promote_id'] : 0;
        $spend_data['promote_account']  = isset($data['promote_account']) ? $data['promote_account'] : '';
        $spend_data['order_number']     = $data['order_number'];
        $spend_data['pay_order_number'] = $data['order_number'];
        $spend_data['props_name']       = isset($data['body']) ? $data['body'] : '';
        $spend_data['cost']             = isset($data['cost']) ? $data['cost'] : 0;
        $spend_data['pay_way']          = 10;
        $spend_data['pay_time']         = NOW_TIME;
        $spend_data['pay_status']       = 0;
        $spend_data['pay_game_status']  = 0;
        $spend_data['extend']           = isset($data['extend']) ? $data['extend'] : '';
        $spend_data['spend_ip']         = get_client_ip();
        $spend_data['sdk_version']      = isset($data['sdk_version']) ? $data['sdk_version'] : '';
        $result = $spend->add($spend_data);
        return $result;
    }
}