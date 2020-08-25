<?php 
namespace Sdk\Controller; 
use Think\Controller; 
use Common\Api\GameApi; 
use Org\WeiXinSDK\Weixin; 
use Org\HeepaySDK\Heepay; 
use Org\UcenterSDK\Ucservice; 
use Org\SwiftpassSDK\Swiftpass; 
use Org\JtpaySDK\Jtpay; 
use Org\GoldPig\GoldPig; 
class WapPayController extends BaseController{ 
    private function pay($param=array()){ 
        $table  = $param['code'] == 1 ? "spend" : "deposit"; 
        $prefix = $param['code'] == 1 ? "SP_" : "PF_"; 
        $out_trade_no = $prefix.date('Ymd').date('His').sp_random_string(4); 
        $user = get_user_entity($param['user_id']); 
        switch ($param['apitype']) { 
            case 'swiftpass': 
                $pay  = new \Think\Pay($param['apitype'],$param['config']); 
                break; 
            default: 
                $pay  = new \Think\Pay($param['apitype'],C($param['config'])); 
                break; 
        } 
        $discount = $this->get_discount($param['game_id'],$user['promote_id'],$param['user_id']); 
        $discount = $discount['discount']; 
        $vo   = new \Think\Pay\PayVo(); 
        $vo->setBody("充值记录描述") 
            ->setFee($param['price'])//支付金额 
            ->setTitle($param['title']) 
            ->setBody($param['body']) 
            ->setOrderNo($out_trade_no) 
            ->setRatio(get_game_selle_ratio($param["game_id"])) 
            ->setService($param['server']) 
            ->setSignType($param['signtype']) 
            ->setPayMethod("wap") 
            ->setTable($table) 
            ->setPayWay($param['payway']) 
            ->setGameId($param['game_id']) 
            ->setGameName(get_game_name($param['game_id'])) 
            ->setGameAppid($param['game_appid']) 
            ->setServerId(0) 
            ->setGameplayerName($param['game_player_name']) 
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
    public function alipay_pay(){
        $request = json_decode(base64_decode(file_get_contents("php://input")),true);
        C(api('Config/lists'));
        if (empty($request)) {
            $this->set_message(1001, "fail", "登录数据不能为空");
        }
        if($request['code'] == 1){
            $extend_data = M('spend','tab_')->where(array('extend'=>$request['extend'],'game_id'=>$request['game_id']))->find();
            if($extend_data){
                $this->set_message(1089,"fail","订单号重复，请关闭支付页面重新支付");
            }
        }
        if($request['price']<0){
            $this->set_message(1011,"fail","充值金额有误");
        }

        $prefix = $request['code'] == 1 ? "SP_" : "PF_";
        $out_trade_no = $prefix.date('Ymd').date('His').sp_random_string(4);

        if(get_tool_status('wei_xin')==1){
            $game_set_data = get_game_set_info($request['game_id']);

            $request['apitype'] = "alipay";
            $request['config']  = "alipay";
            $request['signtype']= "MD5";
            $request['server']  = "alipay.wap.create.direct.pay.by.user";
            $request['payway']  = 1;
            $request['title']=$request['price'];
            $request['body']=$request['price'];
            $request['out_trade_no'] = $out_trade_no;
            $pay_url=$this->pay($request);


            $data = array(
                'status' => 200,
                "out_trade_no" => $out_trade_no,
                "url"          => $pay_url,
            );

        }elseif(get_tool_status('goldpig') == 1){
            if( !C('goldpig.partner')){
                $this->set_message(1009, "fail", "支付参数未配置");
            }

            $request['pay_way'] = 7;
            $request['pay_status'] = 0;
            $request['pay_order_number'] = $out_trade_no;
            $request['spend_ip'] = get_client_ip();
            //折扣
            $user = get_user_entity($request['user_id']);
            $discount = $this->get_discount($request['game_id'],$user['promote_id'],$request['user_id']);
            $discount = $discount['discount'];
            $pay_amount = $discount * $request['price'] / 10;

            if($request['code']==1){
                $this->add_spend($request);
            }else{
                $this->add_deposit($request);
            }


            $goldpig=new GoldPig();
            $pay_url = $goldpig->GoldPig($user['account'],$pay_amount,15,$request['pay_order_number']);
            if($pay_url['status']==0){
                $url='http://'.$_SERVER['HTTP_HOST'];
                $data = array(
                    'status' => 200,
                    "out_trade_no" => $out_trade_no,
                    "url"          => $url,
                );
            }else{
                $data = array(
                    'status' => 200,
                    "out_trade_no" => $out_trade_no,
                    "url"          => $pay_url['msg'],
                );
            }
        }else{
            $this->set_message(1009, "fail", "支付参数未配置");
        }
        echo base64_encode(json_encode($data));
    }
    /**
     *微信支付
     */
    public function weixin_pay()
    {

        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $request = json_decode(base64_decode(file_get_contents("php://input")), true);
        if (empty($request)) {
            $this->set_message(1001, "fail", "登录数据不能为空");
        }
        C(api('Config/lists'));
        if($request['price']<0){
            $this->set_message(1011,"fail","充值金额有误");
        }
        $extend_data = M('spend','tab_')->where(array('extend'=>$request['extend'],'game_id'=>$request['game_id']))->find();
        if($extend_data){
            $this->set_message(1089,"fail","订单号重复，请关闭支付页面重新支付");
        }
        $table  = $request['code'] == 1 ? "spend" : "deposit";
        $prefix = $request['code'] == 1 ? "SP_" : "PF_";
        $request['pay_order_number'] = $prefix . date('Ymd') . date('His') . sp_random_string(4);
        $request['pay_way'] = 3;
        $request['pay_status'] = 0;
        $request['spend_ip'] = get_client_ip();
        //折扣
        $user = get_user_entity($request['user_id']);
        $discount = $this->get_discount($request['game_id'],$user['promote_id'],$request['user_id']);
        $discount = $discount['discount'];
        if($prefix=='PF_'){
            $pay_amount = $request['price'];
        }else{
            $pay_amount = $discount * $request['price'] / 10;
        }
        $game_set_data = get_game_set_info($request['game_id']);
        //$request['game_name']
        //0 官方 1威富通
        if (get_tool_status('wei_xin')==1) {
            if(empty(C('wei_xin.email'))|| empty(C('wei_xin.partner'))||empty(C('wei_xin.key'))){
                $this->set_message(1009, "fail", "支付参数未配置");
            }
            $weixn = new Weixin();
            $is_pay = json_decode($weixn->weixin_pay("充值", $request['pay_order_number'], $pay_amount, 'MWEB'), true);
            if($is_pay['status']==1){
                if($request['code']==1){
                    $this->add_spend($request);
                }else{
                    $this->add_deposit($request);
                }
                $json_data['status'] = 200;
                if($request['sdk_version']==1){
                    $json_data['url'] = $is_pay['mweb_url'];
                    $json_data['orderno'] =$request['pay_order_number'];
                    $json_data['paytype'] ="wx";
                }else{
                    $json_data['url'] = $is_pay['mweb_url'].'&redirect_url='.urlencode((is_ssl()?'https://':'http://'). $_SERVER ['HTTP_HOST'] . "/sdk.php/Spend/pay_success/orderno/".$request['pay_order_number'] );
                    $json_data['paytype'] ="wx";
                }
            }else{
                $json_data['status'] = 500;
                $json_data['url']    = "http://" . $_SERVER['HTTP_HOST'];
            }
            $json_data['cal_url']    = "http://" . $_SERVER['HTTP_HOST'];

            echo base64_encode(json_encode($json_data));exit;
            // $this->redirect('WapPay/weixin_pay_view',['user_id'=>$request['user_id'],'game_id'=>$request['game_id']]);
        }elseif(get_tool_status('goldpig')==1){
            if(!C('goldpig.partner')){
                $this->set_message(1009, "fail", "支付参数未配置");
            }
            $request['pay_way'] = 7;


            if($request['code']==1){
                $this->add_spend($request);
            }else{
                $this->add_deposit($request);
            }

            $goldpig=new GoldPig();
            $res = $goldpig->GoldPig($user['account'],$pay_amount,35,$request['pay_order_number']);
            if($res['status']==1){
                $json_data['status'] = 200;
                $json_data['paytype']='wft';
                $json_data['url']=$res['msg'];

            }else{
                $json_data['status'] = 200;
                $json_data['url']='http://'.$_SERVER ['HTTP_HOST'];
                $json_data['cal_url']    = "http://" . $_SERVER['HTTP_HOST'];
            }

            echo base64_encode(json_encode($json_data)); exit;
        }else{
            if( empty(C('weixin_gf.partner'))||empty(C('weixin_gf.key'))){
                $this->set_message(1009, "fail", "支付参数未配置");
            }
            $Swiftpass=new Swiftpass(C('weixin_gf.partner'),C('weixin_gf.key'));
            $param['service']="pay.weixin.wappay";
            $param['ip']=  $request['spend_ip'];
            $param['pay_amount']=$pay_amount;//;
            $param['out_trade_no']= $request['pay_order_number'];
            $param['game_name']= get_game_name($request['game_id']);
            $param['body']="游戏充值";
            $param['callback_url']='http://' . $_SERVER ['HTTP_HOST'] . "/sdk.php/Spend/pay_success/orderno/".$request['pay_order_number'];
            $url=$Swiftpass->submitOrderInfo($param);
            if($url['status']==0){
                $request['pay_way'] = 4;
                if($request['code']==1){
                    $this->add_spend($request);
                }else{
                    $this->add_deposit($request);
                }
                $json_data['status'] = 200;
                $json_data['url']=$url['pay_info'];
            }else{
                $json_data['status'] = 0;
                $json_data['url']='http://'.$_SERVER ['HTTP_HOST'];
            }
            $json_data['paytype'] ="wft";
            echo base64_encode(json_encode($json_data));
        }
        // Header("Location: $ssd");
    }
    /** 
     * 查询订单状态 
     * @return [type] [description] 
     */ 
    public function get_orderno_restart(){ 
        $request = json_decode(base64_decode(file_get_contents("php://input")), true); 
        $pay_where = substr($request['orderno'], 0, 2); 
        $map['pay_order_number'] = $request['orderno']; 
        switch ($pay_where) { 
            case 'SP': 
                $result = M('Spend','tab_')->field("pay_status")->where($map)->find(); 
                break; 
            case 'PF': 
                $result = M('deposit','tab_')->field('pay_status')->where($map)->find(); 
                break; 
        } 
        if(empty($result['pay_status'])){ 
            $status=1086; 
        }else{ 
            $status=$result['pay_status']==0?1086:200; 
        } 
         echo base64_encode(json_encode(['status'=>$status])); 
    } 
    public function weixin_pay_view($user_id,$game_id){ 
         $file=file_get_contents("./Application/Sdk/OrderNo/".$user_id."-".$game_id.".txt"); 
         $request = json_decode(base64_decode($file),true); 
         $this->assign('url',$request['url'].'&redirect_url='.(is_ssl()?'https://':'http://'). $_SERVER ['HTTP_HOST'] . "/sdk.php/Spend/pay_success"); 
         // Header("Location: $ssd"); 
        $this->display(); 
    } 
    /** 
     * 竣付通支付 
     * @return [type] [description] 
     */ 
    public function jft_wap(){ 
         #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组 
         $request = json_decode(base64_decode(file_get_contents("php://input")), true); 
        if (empty($request)) { 
            $this->set_message(1001, "fail", "登录数据不能为空"); 
        } 
        C(api('Config/lists')); 
          if($request['price']<0){ 
            $this->set_message(1011,"fail","充值金额有误"); 
        } 
            $table = $request['code'] == 1 ? "spend" : "deposit"; 
            $prefix = $request['code'] == 1 ? "SP_" : "PF_"; 
            $request['pay_order_number'] = $prefix . date('Ymd') . date('His') . sp_random_string(4); 
            $request['pay_way'] = 3; 
            $request['pay_status'] = 0; 
            $request['spend_ip'] = get_client_ip(); 
            //折扣 
            $user = get_user_entity($request['user_id']); 
            $discount = $this->get_discount($request['game_id'],$user['promote_id'],$request['user_id']); 
            $discount = $discount['discount']; 
            $pay_amount = $discount * $request['price'] / 10; 
          
                if($request['code']==1){ 
                   $this->add_spend($request); 
                }else{ 
                  $this->add_deposit($request); 
                } 
                 file_put_contents("./Application/Sdk/OrderNo/".$request['user_id']."-".$request['game_id'].".txt",think_encrypt(json_encode($request))); 
               
                $sss="http://".$_SERVER['HTTP_HOST'].'/sdk.php/Spend/pay_way/user_id/'.$request['user_id'].'/game_id/'.$request['game_id'].'/type/3'; 
                redirect($sss); 
    } 
} 