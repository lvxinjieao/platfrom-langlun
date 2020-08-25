<?php
namespace Sdk\Controller;
use Think\Controller\RestController;

class UnionpayController extends BaseController
{
    public function pay()
    {
        $data = json_decode(base64_decode(file_get_contents("php://input")), true);
//        error_log(print_r([$data,date('Y-m-d H:i:s')],true),3,'1111');
        //二维码请求demo
        $unionClient = new \Think\Pay\UnionPay();
        $unionClient->requestUrl = 'https://qr.chinaums.com/netpay-portal/webpay/pay.do';
        $unionClient->key = 'AnCbxw3Rh8reC8GWk85yCcHyTpRKzFzQjPzBGKMNp6AnReei';

        $prefix = $data['code'] == 1 ? "SP_" : "PF_";
        $data['order_number'] = $prefix.date('Ymd').date('His').sp_random_string(4);
        //订单号
        $billNo = $unionClient->msgSrcId . date('YmdHis') . rand(1000000, 9999999);
        $data['body'] = isset($data['body']) ? $data['body'] : '充值';
        //参数自行添加或修改
        $unionClient->setParams('mid', '898130448161557');    //商户号
        $unionClient->setParams('tid', '04939556 ');    //终端号
        $unionClient->setParams('msgType', 'bills.getQRCode');    //消息类型
        $unionClient->setParams('msgSrc', 'WWW.YZHXGXX.COM');    //消息来源
        $unionClient->setParams('instMid', 'QRPAYDEFAULT');    //业务类型
        $unionClient->setParams('billNo', $data['order_number']);    //账单号
        $unionClient->setParams('totalAmount', 1);    //支付总金额(单位：分)
        $unionClient->setParams('billDate', date('Y-m-d', time()));    //账单日期：yyyy-MM-dd
        $unionClient->setParams('billDesc', sprintf($data['body'], $billNo));    //账单描述
        $unionClient->setParams('notifyUrl', 'CallbackDemo.php');    //支付结果通知地址
        //同步通知地址
        $sUrl = 'https://' . $_SERVER ['HTTP_HOST'] . "/sdk.php/unionpay/pay_success?order_number={$data['order_number']}";
        $unionClient->setParams('returnUrl', $sUrl);    //网页跳转地址
        $unionClient->setParams('requestTimestamp', date('Y-m-d H:i:s', time()));    //报文请求时间:yyyy-MM-dd HH:mm:ss
        $res = $unionClient->request();
        if(array_key_exists('code') && $res['code'] == 0) {
            $this->set_message(1000, "fail", $res['msg']);
        }
        $bool = $this->add_spend($data);
        if(!$bool) {
            $this->set_message(999, "fail", '订单生成失败');
        }
        echo base64_encode(json_encode(array('status' => 200, 'url' => $res['billQRCode'], 'return_msg' => 'success')));
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

 /**
 * 服务端支付接口查看
 */
  public function pay_success(){
        $orderno = $_GET['order_number']=="" ? $_GET['order_number']: $_GET['order_number'];
        $pay_where = substr($orderno, 0, 2);
        $map['pay_order_number'] = $orderno;
        switch ($pay_where) {
            case 'SP':
                $result = M('Spend','tab_')->field("pay_status")->where($map)->find();
                break;
            case 'PF':
                $result = M('deposit','tab_')->field('pay_status')->where($map)->find();
                break;
            case 'BR':
                $result = M('bing_recharge','tab_')->field('pay_status')->where($map)->find();
                break;
        }
        $this->assign('paystatus',$result['pay_status']);
        $html = $result['pay_status'] == 1 ? 'pay_success.html' : 'pay_error.html';
        $this->display($html);

    }
}