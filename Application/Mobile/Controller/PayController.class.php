<?php
namespace Mobile\Controller;
use Think\Controller;
use User\Api\MemberApi;
use Common\Api\GameApi;
use Common\Api\PayApi;
use Com\Wechat;
use Com\WechatAuth;
use Org\JtpaySDK\Jtpay;
use Org\SwiftpassSDK\Swiftpass;
use Org\WeiXinSDK\WeiXinOauth;
use Org\WeiXinSDK\Weixin;
use Org\GoldPig\GoldPig; 
class PayController extends SubscriberController{

	const ALI_PAY = 1;          //支付宝支付
    const WEIXIN_PAY =2;        //微信支付
	const GOLDPIG_PAY =8;        //金猪支付

	const PLATFORM_COIN = 1;        //平台币
	const BIND_PLATFORM_COIN = 2;   //绑定平台币


    /**
     * 微信官方、威富通官方wap支付
     */
    public function weixin_wap_pay()
    {
        $user = get_user_entity(is_login());
        if (empty($user)) {
            $this->error("用户不存在");
        }
        if (pay_set_status('wei_xin') == 0 && pay_set_status('weixin') == 0) {
            $this->error("网站未启用微信充值", '', 1);
            exit();
        }
        if ($_POST['amount'] <= 0) {
            $this->error('充值金额有误');
            exit();
        }
        $data['out_trade_no'] = 'PF_' . date('Ymd') . date('His') . sp_random_string(4);
        $data['pay_way'] = 2;
        $data['amount'] = $_POST['amount'];
        $param['service'] = "pay.weixin.wappay";
        $param['ip'] = get_client_ip();
        $param['game_name'] = 'vlcms';
        $param['pay_amount'] = $_POST['amount'];
        $param['out_trade_no'] = $data['out_trade_no'];
        $param['body'] = "平台币充值";
        //0 官方 1威富通
        if (get_wx_type() == 0) {
            $weixn = new Weixin();
            $is_pay = json_decode($weixn->weixin_pay("平台币充值", $param['out_trade_no'], $data['amount'], 'MWEB'), true);
            if($is_pay['status']==1){
                $this->add_deposit($data, $user);
                $json_data = array('status' => 1, 'pay_info' => $is_pay['mweb_url']);
            }else{
                $json_data = array('status' => -1,'info'=>$is_pay['return_msg']);
            }
        } else {
            $Swiftpass = new Swiftpass(C('weixin.partner'), C('weixin.key'));
            $url = $Swiftpass->submitOrderInfo($param);
            if ($url['status'] == 000) {
                $this->add_deposit($data, $user);
                $json_data = array('status' => 1, 'pay_info' => $url['pay_info']);
            }else{
                $json_data = array('status' => -1, 'info' => $url['msg']);
            }
        }

        $this->ajaxReturn($json_data);

    }

    /**

     *微信支付

     */

    public function game_wx_pay(){
        #判断账号是否存在
        if($_REQUEST['sign']!=session('game_pay_sign')){
            $this->ajaxReturn(array("status"=>0,"msg"=>'数据异常！'));exit;
        }
        $user = get_user_entity(is_login());

        if (empty($user)) {
            $this->error("用户不存在");exit();
        }else{
            $data["user_id"]       = $user["id"];
            $data["user_account"]  = $user['account'];
            $data["user_nickname"] = $user['nickname'];
        }
        if ($_POST['amount'] <= 0) {
            $this->error('充值金额有误');
            exit();
        }
        $game_data = get_game_entity($_POST["game_appid"]);
        if(empty($game_data)){$this->error("游戏不存在");exit();}
        #支付配置
        $data['options']  = 'spend';
        $data['order_no'] = 'SP_'.date('Ymd').date ( 'His' ).sp_random_string(4);
        $data['fee']      = $_POST['amount']/100;
        $data['pay_type'] = 'swiftpass';
        $data['server'] = "pay.weixin.native";
        #平台币记录数据
        $data["game_id"]       = $game_data['id'];
        $data["game_appid"]    = $_POST["game_appid"];
        $data["game_name"]     = $game_data["game_name"];
        $data["server_id"]     = $_POST["server_id"];
        $data["server_name"]   = $_POST["server_name"];
        $data["game_player_id"]     = $_POST["role_id"];
        $data["game_player_name"]   = $_POST["role_name"];
        $data["promote_id"]    = $user['promote_id'];
        $data["promote_account"] = $user['promote_account'];
        $data["pay_order_number"]= $data['order_no'];
        $data["title"] = $_POST['props_name'];
        $data["pay_amount"]   = $_POST['amount']/100;
        $data["pay_way"] = 2;
        $data['extend']  = $_POST['trade_no'];
        $data['code']  = 1;
        $pay = new PayApi();
        if(get_wx_type()==0){//0官方
            $weixn=new Weixin();
            $is_pay=json_decode($weixn->weixin_pay($data["title"],$data['order_no'],$data['fee'],'NATIVE'),true);
            if($is_pay['status']===1){
                $html ='<div class="d_body" style="height:px;">
                    <div class="d_content">
                        <div class="text_center">
                            <table class="list" width="100%">
                                <tbody>
                                <tr>
                                    <td class="text_right">订单号</td>
                                    <td class="text_left">'.$data['order_no'].'</td>
                                </tr>
                                <tr>
                                    <td class="text_right">充值金额</td>
                                    <td class="text_left">本次充值'.$data['fee'].'元，实际付款'.$data['fee'].'元</td>
                                </tr>
                                </tbody>
                            </table>
                            <img src="'.U('Subscriber/qrcode',array('level'=>3,'size'=>4,'url'=>base64_encode(base64_encode($is_pay['url'])))).'" height="301" width="301">
                            <br/>&nbsp;&nbsp;&nbsp;&nbsp;
                            <img src="/Public/Media/images/wx_pay_tips.png">
                        </div>              </div>
                </div>';
                $pay->add_spend($data);
                $this->ajaxReturn(array("status"=>1,"out_trade_no"=>$data['order_no'],"html"=>$html,"game_id"=>$data["game_id"]));
            }else{
                $this->ajaxReturn(array("status"=>0,"msg"=>'支付数据错误！'));
            }
        }else{
            $data['pay_way']=4;
            $arr = $pay->other_pay($data,C('weixin'));
            if($arr['status1'] === 500){
                \Think\Log::record($arr['msg']);
                $html ='<div class="d_body" style="height:px;">
                        <div class="d_content">
                            <div class="text_center">'.$arr["msg"].'</div>
                        </div>
                        </div>';
                $json_data = array("status"=>1,"html"=>$html);
            }else{
                $html ='<div class="d_body" style="height:px;">
                        <div class="d_content">
                            <div class="text_center">
                                <table class="list" width="100%">
                                    <tbody>
                                    <tr>
                                        <td class="text_right">订单号</td>
                                        <td class="text_left">'.$arr["out_trade_no"].'</td>
                                    </tr>
                                    <tr>
                                        <td class="text_right">充值金额</td>
                                        <td class="text_left">本次充值'.$data["pay_amount"].'元，实际付款'.$data["pay_amount"].'元</td>
                                    </tr>
                                    </tbody>
                                </table>
                                <img src="'.$arr["code_img_url"].'" height="301" width="301">
                                <br/>&nbsp;&nbsp;&nbsp;&nbsp;
                                <img src="/Public/Media/images/wx_pay_tips.png">
                            </div>
                        </div>
                    </div>';
                $json_data = array("status"=>1,"out_trade_no"=>$arr["out_trade_no"],"html"=>$html,"game_id"=>$data["game_id"]);
            }
            $this->ajaxReturn($json_data);
        }
    }

    /**
     *微信pc支付
     */

    public function wx_pay()
    {
        #判断账号是否存在
        $user = get_user_entity(is_login());
        if (empty($user)) {
            $this->error("用户不存在");
            exit();
        }
        if (pay_set_status('wei_xin') == 0 && pay_set_status('weixin') == 0) {
            $this->error("网站未启用微信充值", '', 1);
            exit();
        }
        if (get_wx_type() == 0) {//0官方
            $weixn = new Weixin();
            $datt['out_trade_no'] = "PF_" . date('Ymd') . date('His') . sp_random_string(4);
            $datt['amount'] = $_POST['amount'];
            // $datt['amount']  = 0.01;
            $datt['pay_status'] = 0;
            $datt['pay_way'] = 2;
            $datt['pay_source'] = 1;
            $is_pay = json_decode($weixn->weixin_pay("平台币充值", $datt['out_trade_no'], $datt['amount'], 'NATIVE'), true);
            if ($is_pay['status'] === 1) {
                $html = '<div class="d_body" style="height:px;">
                    <div class="d_content">
                        <div class="text_center">
                            <table class="list" width="100%">
                                <tbody>
                                <tr>
                                    <td class="text_right">订单号</td>
                                    <td class="text_left">' . $datt["out_trade_no"] . '</td>
                                </tr>
                                <tr>
                                    <td class="text_right">充值金额</td>
                                    <td class="text_left">本次充值' . $datt["amount"] . '元，实际付款' . $datt["amount"] . '元</td>
                                </tr>
                                </tbody>
                            </table>
                            <img src="' . U('qrcode', array('level' => 3, 'size' => 4, 'url' => base64_encode(base64_encode($is_pay['url'])))) . '" height="301" width="301">
                            <br/>&nbsp;&nbsp;&nbsp;&nbsp;
                            <img src="/Public/Media/images/wx_pay_tips.png">
                        </div>
                    </div>
                </div>';
                $this->add_deposit($datt, $user);
                $this->ajaxReturn(array("status" => 1, "out_trade_no" => $datt["out_trade_no"], "html" => $html));
            }
        } else {
            $pay = new PayApi();
            $datt['pay_way']=4;
            $arr = $pay->other_pay($datt,C('weixin'));
            if($arr['status1'] === 500){
                \Think\Log::record($arr['msg']);
                $html ='<div class="d_body" style="height:px;">
                        <div class="d_content">
                            <div class="text_center">'.$arr["msg"].'</div>
                        </div>
                        </div>';
                $json_data = array("status"=>1,"html"=>$html);
            }else{
                $html ='<div class="d_body" style="height:px;">
                        <div class="d_content">
                            <div class="text_center">
                                <table class="list" width="100%">
                                    <tbody>
                                    <tr>
                                        <td class="text_right">订单号</td>
                                        <td class="text_left">'.$arr["out_trade_no"].'</td>
                                    </tr>
                                    <tr>
                                        <td class="text_right">充值金额</td>
                                        <td class="text_left">本次充值'.$datt["amount"].'元，实际付款'.$data["amount"].'元</td>
                                    </tr>
                                    </tbody>
                                </table>
                                <img src="'.$arr["code_img_url"].'" height="301" width="301">
                                <br/>&nbsp;&nbsp;&nbsp;&nbsp;
                                <img src="/Public/Media/images/wx_pay_tips.png">
                            </div>
                        </div>
                    </div>';
                $json_data = array("status"=>1,"out_trade_no"=>$arr["out_trade_no"],"html"=>$html,"game_id"=>$data["game_id"]);
            }
            $this->ajaxReturn($json_data);
        }
    }


    private function pay($table,$prefix,$param){
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
        $vo   = new \Think\Pay\PayVo();
        $vo ->setFee($param['real_pay_amount'])//支付金额
	        ->setMoney($param['pay_amount'])
            ->setTitle($param['title'])
            ->setBody($param['body'])
            ->setOrderNo($out_trade_no)
            ->setService($param['server'])
            ->setSignType($param['signtype'])
            ->setPayMethod("wap")
            ->setTable($table)
            ->setPayWay($param['payway'])
            ->setGameId($param['game_id'])
            ->setGameName($param['game_name'])
            ->setGameAppid($param['game_appid'])
            ->setServerId(0)
            ->setServerName("")
            ->setUserId($param['user_id'])
            ->setAccount($user['account'])
            ->setUserNickName($user['nickname'])
            ->setPromoteId($param['promote_id'])
            ->setPromoteName(get_promote_name($param['promote_id']))
            ->setExtend($param['extend'])
	        ->setDiscount($param['discount'])
            ->setSdkVersion($param['sdk_version']);
        return $pay->buildRequestForm($vo);
    }

	/**
	 * APP充值
	 * @param $token
	 * @param $pay_amount   金额
	 * @param $good_info    商品信息(json数组：type 1平台币 2绑币 game_id 游戏ID)
	 * @param $pay_way      1 支付宝 2微信
	 * author: xmy 280564871@qq.com
	 */
    public function recharge($pay_amount,$good_info,$pay_way,$promote_id){
        $pay_amount = 0.01;
        $good_info = json_decode($good_info,true);
	    $good['user_id'] = $good_info['user_id'];
    	switch ($good_info['type']){
		    case self::PLATFORM_COIN:
		    	$table = "deposit";
		    	$prefix = "PF_";
			    $good['real_pay_amount'] = $pay_amount;
			    $good['amount'] = $pay_amount;
			    $good['title'] = "平台币";
			    $good['body'] = "平台币充值";
		    	break;
		    case self::BIND_PLATFORM_COIN:
		    	$table = "bind_recharge";
		    	$prefix = "BR_";
		    	$game_id = $good_info['game_id'];
			    $game = M("Game","tab_")->find($game_id);
			    if(empty($game)){
			    	$this->error("游戏不存在");
			    }
			    $discount = empty($game['bind_recharge_discount']) ? 10 : $game['bind_recharge_discount'];
			    $real_pay_amount = round($pay_amount * $discount / 10,2);
			    //构建商品信息
			    $good['title'] = "绑定平台币";
			    $good['body']  = "绑定平台币充值";
			    $good['game_id']    = $game_id;
			    $good['game_name']  = $game['game_name'];
			    $good['game_appid'] = $game['game_appid'];
			    $good['real_pay_amount'] = $real_pay_amount;
			    $good['discount'] = $discount;
			    break;
		    default:
		    	$this->error("商品信息错误");
	    }
	    $good['pay_amount'] = $pay_amount;
    	$good['promote_id'] = $promote_id;
    	switch ($pay_way){
		    case self::ALI_PAY :
			    $result = $this->alipay_pay($good,$table,$prefix);
			    break;
		    case self::WEIXIN_PAY:
		    	$result = $this->weixin_pay($good,$table,$prefix);
		    	break;
            
		    default:$this->error("暂无该支付选项");
	    }
	    return $result;
    }
		
		/**
		 * 购买商品并支付
		 * @param $token
		 * @param $pay_amount   金额
		 * @param $good_info    商品信息
		 * @param $pay_way      1 支付宝 2微信
		 * @author 鹿文学
		 */
		public function buy($pay_amount,$good_info,$pay_way,$promote_id) {
			$good = json_decode($good_info,true);
			switch($good['type']) {
				case 'small':{
					$table = 'order';
					//$good['real_pay_amount'] = 0.01;
					$good['real_pay_amount'] = $pay_amount;
					$good['title'] = "购买商品";
					$good['body'] = "购买商品支付";
					$prefix = 'SI_';
				};break;
				default:
		    	$this->error("商品信息错误");
			}
			$good['pay_amount'] = $pay_amount;
			switch ($pay_way){
		    case self::ALI_PAY :
			    $result = $this->alipay_pay($good,$table,$prefix,'buy_pay');
			    break;
		    case self::WEIXIN_PAY:
		    	$result = $this->weixin_pay($good,$table,$prefix,'buy_pay');
		    	break;
            
		    default:$this->error("暂无该支付选项");
	    }
			
			return $result;
		}

    /*支付宝支付*/
    public function alipay($amount = 0.01, $game_id = '')
    {
        #判断账号是否存在
        $user = get_user_entity(is_login());
        if ($user<=0) {
            if(IS_AJAX){
                $this->ajaxReturn(array('status'=>-1,'msg'=>'未登录','url'=>U('Subscriber/user')));
            }
            redirect(U('Subscriber/user'));exit;
        }
        if (ceil($amount) < 1) {
            $this->ajaxReturn(array('status'=>-1,'msg'=>'充值金额有误'));
        }
        #支付配置
        $data['code'] = 2;
        $data['fee'] = $amount;
        $data['title'] = '平台币购买';
        $data['body'] = '购买'.intval($amount).'平台币';
        $data['pay_type'] = 'alipay';
        $data['signtype'] = 'MD5';
        if (is_mobile_request()) {
            $data['server'] = 'alipay.wap.create.direct.pay.by.user';
            $data['method'] = 'wap';
        } else {
            $data['server'] = 'create_direct_pay_by_user';
            $data['method'] = 'direct';
        }
        if($param['code'] == 1){
            $prefix = 'SP_';
        }else{
            $prefix = 'PF_';
        }
        $out_trade_no = $prefix . date('Ymd') . date('His') . sp_random_string(4);
        #平台币记录数据
        $data['order_no'] = $out_trade_no;
        $data['user_id'] = $user['id'];
        $data['user_account'] = $user['account'];
        $data['user_nickname'] = $user['nickname'];
        $data['promote_id'] = $user['promote_id'];
        $data['promote_account'] = $user['promote_account'];
        $data['pay_amount'] = $amount;
        $data['pay_status'] = 0;
        $data['pay_way'] = 1;
        $data['pay_source'] = 1;
        if ($game_id) {
            $data['game_id'] = $game_id;
        }
        if (is_mobile_request()) {
            $api = new PayApi();
            $url = $api->other_pay($data,C('alipay'),$game_id);
            if(IS_AJAX){
                $this->ajaxReturn(array('status'=>1,'url'=>$url));
            }else{
                redirect($url);
            }
        } else {
            echo $url;
        }
    }

    // public function jftpay($money)
    // {
    //     if (pay_set_status('jft') == 0) {
    //         $this->error("网站未启用竣付通充值", '', 1);
    //         exit();
    //     }
    //     #判断账号是否存在
    //     $user = get_user_entity(is_login());
    //     if (empty($user)) {
    //         $this->error("用户未登录");
    //         exit();
    //     } else {
    //         $data2["user_id"] = $user["id"];
    //         $data2["user_account"] = $user['account'];
    //         $data2["user_nickname"] = $user['nickname'];
    //     }
    //     if ($_POST['money'] <= 0) {
    //         $this->error('充值金额有误');
    //     }
    //     #支付配置
    //     $data['order_no'] = 'PF_' . date('Ymd') . date('His') . sp_random_string(4);
    //     $data['fee']      = $_POST['amount'];//$_POST['amount'];
    //     // $data['fee'] = 0.01;
    //     #平台币记录数据
    //     $data['order_number'] = "";
    //     $data['pay_order_number'] = $data['order_no'];
    //     $data['user_id'] = $data2['user_id'];
    //     $data['user_account'] = $data2['user_account'];
    //     $data['user_nickname'] = $data2['user_nickname'];
    //     $data['promote_id'] = $user['promote_id'];
    //     $data['promote_account'] = $user['promote_account'];
    //     $data['pay_amount'] =  $money;
    //     $data['pay_status'] = 0;
    //     $data['pay_way'] = 6;//竣付通
    //     $data['pay_source'] = 1;
    //     $sign = think_encrypt(md5( $money . $data['order_no']));
    //     $jtpay = new Jtpay();
    //     // echo $jtpay->jt_pay($data['pay_order_number'],$data['pay_amount'],$data2['user_account'],get_client_ip(),'','3','',4,2);
    //     // jt_pay($order_no,$amount=0.02,$account="测试",$ip,$paychannelnum="ICBC",$p9_paymethod=1,$returnurl="",$p26_iswappay=1,$p25_terminal=1)
    //     $url = U('Subscriber/pay_way', array('type' => 'Alipay', 'account' => $data2['user_account'], 'pay_amount' =>  $money, 'sign' => $sign, 'pay_order_number' => $data['order_no']));
    //     $this->error('',$url);
    //     // jt_pay($order_no,$amount=0.02,$account="测试",$ip,$p9_paymethod=1,$p26_iswappay=1,$p25_terminal=1){
    // }


		
		private function buy_pay($table,$prefix,$param){
            $da = M('Order','tab_')->where(['merchandise_id'=>$param['id'],'buyer_id'=>$param['user_id']])->find();
            if(is_array($da)) {
                M('Order','tab_')->where(['merchandise_id'=>$param['id'],'buyer_id'=>$param['user_id']])->save(['order_time'=>time()]);
                $out_trade_no = $da['order_number'];
            } else {
                $out_trade_no = $prefix.date('Ymd').date('His').sp_random_string(4);
            }
            $user = get_user_entity($param['user_id']);
            switch ($param['apitype']) {
                case 'swiftpass':
                    $pay  = new \Think\Pay($param['apitype'],$param['config']);
                    break;

                default:
                    $pay  = new \Think\Pay($param['apitype'],C($param['config']));
                    break;
            }
            $vo   = new \Think\Pay\PayVo();
            $vo ->setFee($param['real_pay_amount'])//支付金额
						->setMoney($param['real'])
            ->setTitle($param['title'])
            ->setBody($param['body'])
            ->setOrderNo($out_trade_no)
            ->setService($param['server'])
            ->setSignType($param['signtype'])
            ->setPayMethod("trade")
            ->setTable($table)
            ->setPayWay($param['payway'])
            ->setBuyerId($param['user_id'])
            ->setBuyerAccount($user['account'])
						->setSellerId($param['seller_id'])
						->setSellerAccount($param['seller_account'])
						->setSmallId($param['small_id'])
						->setSmallAccount($param['small_account'])
						->setParam($param['id'])
						->setGameId($param['game_id'])
						->setPoundage($param['poundage']);
						
        return $pay->buildRequestForm($vo);
    }


    /**
    *支付宝移动支付
    */
    private function alipay_pay($param,$table,$prefix,$pway=''){
        $param['apitype'] = "alipay";
	    $param['config']  = "alipay";
	    $param['signtype']= "MD5";
	    $param['server']  = "alipay.wap.create.direct.pay.by.user";//mobile.securitypay.pay
	    $param['payway']  = 1;
	    $param['user_id'] = $param['user_id'];
        //if($pway=='buy_pay') {
		//	$data = $this->buy_pay($table,$prefix,$param);
       // } else {
        $data = $this->pay($table,$prefix,$param);
       //}
	    $resultData = array('pay_way'=>'alipay',"status"=>1,"url"=>$data);
	    return $resultData;
    }

    /**
    *微信支付
    */
	private function weixin_pay($param, $table, $prefix,$pway='')
	{

//		if (get_wx_type() == 0) {//官方
			$param['pay_order_number'] = $prefix . date('Ymd') . date('His') . sp_random_string(4);
            $param['out_trade_no'] = $param['pay_order_number'];
			$param['pay_way'] = 3;
			$param['pay_status'] = 0;
			$param['spend_ip'] = get_client_ip();
			$weixn = new Weixin();
			$is_pay = json_decode($weixn->weixin_pay($param['title'], $param['pay_order_number'], $param['real_pay_amount'], 'MWEB'), true);
			$user = get_user_entity($param['user_id']);
            $user['user_id'] = $param['user_id'];
			if ($is_pay['status'] === 1) {
				switch ($table){
					case 'deposit':
						$this->add_deposit($param,$user);
						break;
					case "bind_recharge":
						$this->add_bind_recharge($param,$user);
						break;
				}
                //$is_pay['mweb_url'] = $is_pay['mweb_url'].'&redirect_url='.(is_ssl()?'https%3A%2F%2F':'http%3A%2F%2F'). $_SERVER ['HTTP_HOST']."%2Fmobile.php%2FTrade%2Fsuccess%2Fout_trade_no%2F".$param['pay_order_number']; 
				$json_data = array('pay_way'=>'weixin',"status"=>1,"url"=>$is_pay['mweb_url']);
			}else{
				$json_data = array('pay_way'=>'weixin',"status"=>0,"info"=>'失败');
			}
			return $json_data;
//		} else {
//            $Swiftpass=new Swiftpass(C('weixin_gf.partner'),C('weixin_gf.key'));
//            $param['service']="pay.weixin.wappay";
//            $param['ip']=  get_client_ip();
//            $param['pay_amount']=$param['real_pay_amount'];//;
//            $param['out_trade_no']= $prefix . date('Ymd') . date('His') . sp_random_string(4);
//				//file_put_contents(dirname(__FILE__). '/wxswiftpass.txt',json_encode($param));
//			if('buy_pay'==$pway) {
//                $param['body']='购买商品支付';
//                $param['callback_url']='http://' . $_SERVER ['HTTP_HOST'] . "/mobile.php/Trade/success/out_trade_no/".$param['out_trade_no'];
//                $url=$Swiftpass->submitOrderInfo($param);
//                if($url['status']==0){
//                  $request['pay_way'] = 4;
//                    $this->add_order($param);
//                    $json_data = array('pay_way'=>'weixin',"status"=>1,"url"=>$url['pay_info']);
//                }else{
//                    $json_data = array('pay_way'=>'weixin',"status"=>0,"info"=>$url['msg']);
//                }
//                return $json_data;
//
//			} else {
//                $param['body']="游戏充值";
//                $param['callback_url']='http://' . $_SERVER ['HTTP_HOST'] . "/mobile.php/User/recharge";
//                $url=$Swiftpass->submitOrderInfo($param);
//                if($url['status']==0){
//                  $request['pay_way'] = 4;
//                    if($request['code']==1){
//                        $this->add_spend($param);
//                    }else{
//                        $this->add_deposit($param);
//                    }
//                    $json_data = array('pay_way'=>'weixin',"status"=>1,"url"=>$url['pay_info']);
//                }else{
//                    $json_data = array('pay_way'=>'weixin',"status"=>0,"info"=>$url['msg']);
//                }
//                return $json_data;
//			}
//		}
	}

    /**
     * 金猪支付
     * @return [type] [description]
     * @author cb <[email address]>
     */
    public function goldPig()
    {
        $user = get_user_entity(session('user_auth.user_id'));
        $user['user_id'] = session('user_auth.user_id');
        if (!$user) {
            $this->error("用户不存在");
        }
        if (pay_set_status('goldpig') == 0 ) {
            $this->error("网站未启用金猪充值", '', 1);
            exit();
        }
        if($_REQUEST['spendType'] == 2){
            $userMap['user_id'] = $user['user_id'];
            $userMap['game_id'] = $_REQUEST['game_id'];
            $empty = M('UserPlay','tab_')->where($userMap)->find();
            if(!$empty){
                $this->error('该用户未玩过此游戏哦~');
            }
        }
        switch ($_POST['spendType']){
            case '1':
                $data['real_pay_amount'] = $_POST['pay_amount'];
                $data['out_trade_no'] = 'PF_' . date('Ymd') . date('His') . sp_random_string(4);
                $data['pay_way'] = 7;
                $data['amount'] = $_POST['pay_amount'];
                $res = $this->add_deposit($data, $user);
                if(!$res){
                    $this->error("订单创建失败，请重新创建");
                }
                break;
            case '2':
                $data['pay_order_number'] = 'BR_' . date('Ymd') . date('His') . sp_random_string(4);
                $data['game_id'] = $_POST['game_id'];
                $game = M("Game","tab_")->field('id,game_name,game_appid,bind_recharge_discount')->find($data['game_id']);
                if(empty($game)){
                    $this->error("游戏不存在");
                }
                $discount = empty($game['bind_recharge_discount']) ? 10 : $game['bind_recharge_discount'];
                $real_pay_amount = round($_POST['pay_amount'] * $discount / 10,2);
                //构建商品信息
                $data['game_name']  = $game['game_name'];
                $data['game_appid'] = $game['game_appid'];
                $data['real_pay_amount'] = $real_pay_amount;
                $data['pay_amount'] = $_POST['pay_amount'];
                $data['pay_way'] = 7;
                $data['discount'] = $discount;
                $data['user_id'] = $user['user_id'];
                $data['spend_ip'] = get_client_ip();
                $this->add_bind_recharge($data,$user);
                break;
            default:
                $this->error("信息错误");
        }
        if ($data['real_pay_amount'] < 1) {
            $this->error('充值金额不能小于1');
            exit();
        }

        $urlparams['UserName'] = $user['account'];
        $urlparams['fee'] = $data["real_pay_amount"];
        $urlparams['jinzhua'] = $data["out_trade_no"] ? $data["out_trade_no"] : $data['pay_order_number'];
        $urlparams['jinzhub'] = signsortData($urlparams,C('goldpig.key'));
        $urlparams['gamename'] = $_POST['spendType'] == 1 ? '平台币充值' : "折扣充值";
        $url = U('Subscriber/user_recharge_pig',sortData($urlparams));
        if(IS_AJAX){
            $this->ajaxReturn(array('status'=>1,'url'=>$url));
        }else{
            redirect($url);
        }

    }

    public function recharge_pig() {

        $user = get_user_entity(is_login());
        if (empty($user)) {
            $this->error("用户不存在");
        }
        if (pay_set_status('goldpig') == 0 ) {
            $this->error("网站未启用金猪充值", '', 1);
            exit();
        }
        if (ceil($_POST['money']) < 1) {
            $this->error('充值金额不能小于1');
            exit();
        }
        $data['out_trade_no'] = 'PF_' . date('Ymd') . date('His') . sp_random_string(4);
        $data['pay_way'] = 7;
        $data['amount'] = $_POST['money'];
        $res = $this->add_deposit($data, $user);
        if(!$res){
            $this->error("订单创建失败，请重新创建");
        }
        $urlparams['UserId'] = $user['id'];
        $urlparams['UserName'] = $user['account'];
        $urlparams['fee'] = $data["amount"];
        $urlparams['jinzhua'] = $data["out_trade_no"];
        $urlparams['jinzhub'] = signsortData($urlparams,C('goldpig.key'));
        $urlparams['gamename'] = '平台币充值';

        $url = U('Subscriber/user_recharge_pig',sortData($urlparams));

        if(IS_AJAX){
            $this->ajaxReturn(array('status'=>1,'url'=>$url));
        }else{
            redirect($url);
        }
    }


    public function gameGoldPig(){
        #判断账号是否存在
        $user = get_user_entity(is_login());
        if ($user<=0) {
            $this->ajaxReturn(array('status'=>-1,'msg'=>'未登录'));
        }else{
            $data["user_id"]       = $user["id"];
            $data["user_account"]  = $user['account'];
            $data["user_nickname"] = $user['nickname'];
        }
        if (pay_set_status('goldpig') == 0 ) {
            $this->error("网站未启用金猪充值", '', 1);
            exit();
        }
        $amount = $_POST['amount'];
        if ($amount/100 < 1) {
            $this->ajaxReturn(array('status'=>-1,'msg'=>'充值金额需要大于1'));
        }
        $game_data = get_game_entity($_POST["game_appid"]);
        if(empty($game_data)){$this->error("游戏不存在");exit();}
        #支付配置
        $data['options']  = 'spend';
        $data['order_no'] = 'SP_'.date('Ymd').date ( 'His' ).sp_random_string(4);
        $data['fee']      = $amount/100;
        $data['pay_type'] = 'goldpig';
        #平台币记录数据
        $data["game_id"]       = $game_data['id'];
        $data["game_appid"]    = $_POST["game_appid"];
        $data["game_name"]     = $game_data["game_name"];
        $data["server_id"]     = $_POST["server_id"];
        $data["server_name"]   = $_POST["server_name"];
        $data["game_player_id"]     = $_POST["role_id"];
        $data["game_player_name"]   = $_POST["role_name"];
        $data["promote_id"]    = $user['promote_id'];
        $data["promote_account"] = $user['promote_account'];
        $data["pay_order_number"]= $data['order_no'];
        $data["title"] = $_POST['props_name'];
        $data["pay_amount"]   = $amount/100;
        $data["pay_way"] = 7;
        $data['extend']  = $_POST['trade_no'];
        $data['code']  = 1;
        $pay = new PayApi();
        $res = $pay->add_spend($data);
        if(!$res){
            $this->error("订单创建失败，请重新创建");
        }
        $urlparams['UserName'] = $user['account'];
        $urlparams['fee'] = $data["pay_amount"];
        $urlparams['jinzhua'] = $data["order_no"];
        $urlparams['jinzhub'] = signsortData($urlparams,'mengchuang');
        $urlparams['gamename'] = $game_data["game_name"];
        $url = 'http://pay000.com/wap/?m=BTGG&'.sortData($urlparams);
        if(IS_AJAX){
            $this->ajaxReturn(array('status'=>1,'url'=>$url));
        }else{
            redirect($url);
        }

    }

    public function gameGoldPigRecharge(){
        #判断账号是否存在
        $user = get_user_entity(is_login());
        if ($user<=0) {
            $this->ajaxReturn(array('status'=>-1,'msg'=>'未登录'));
        }else{
            $data["user_id"]       = $user["id"];
            $data["user_account"]  = $user['account'];
            $data["user_nickname"] = $user['nickname'];
        }
        if (pay_set_status('goldpig') == 0 ) {
            $this->error("网站未启用金猪充值", '', 1);
            exit();
        }
        $amount = $_POST['amount'];
        if ($amount < 1) {
            $this->ajaxReturn(array('status'=>-1,'msg'=>'充值金额需要大于1'));
        }
        $game_data = get_game_entity($_POST["game_appid"]);
        if(empty($game_data)){$this->error("游戏不存在");exit();}
        #支付配置
        $data['options']  = 'spend';
        $data['order_no'] = 'SP_'.date('Ymd').date ( 'His' ).sp_random_string(4);
        $data['fee']      = $amount;
        $data['pay_type'] = 'goldpig';
        #平台币记录数据
        $data["game_id"]       = $game_data['id'];
        $data["game_appid"]    = $_POST["game_appid"];
        $data["game_name"]     = $game_data["game_name"];
        $data["server_id"]     = $_POST["server_id"];
        $data["server_name"]   = $_POST["server_name"];
        $data["game_player_id"]     = $_POST["role_id"];
        $data["game_player_name"]   = $_POST["role_name"];
        $data["promote_id"]    = $user['promote_id'];
        $data["promote_account"] = $user['promote_account'];
        $data["pay_order_number"]= $data['order_no'];
        $data["title"] = $_POST['props_name'];
        $data["pay_amount"]   = $amount;
        $data["pay_way"] = 7;
        $data['extend']  = $_POST['trade_no'];
        $data['cose'] = $amount;
        $data['code']  = 1;
        $pay = new PayApi();
        $res = $pay->add_spend($data);
        if(!$res){
            $this->error("订单创建失败，请重新创建");
        }
        $urlparams['UserName'] = $user['account'];
        $urlparams['fee'] = $data["pay_amount"];
        $urlparams['jinzhua'] = $data["order_no"];
        $urlparams['jinzhub'] = signsortData($urlparams,'mengchuang');
        $urlparams['gamename'] = $game_data["game_name"];
        $url = U('Subscriber/user_recharge_pig',sortData($urlparams));

        if(IS_AJAX){
            $this->ajaxReturn(array('status'=>1,'url'=>$url));
        }else{
            redirect($url);
        }

    }





    /**
    *支付验证
    */
    public function pay_validation(){
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $request = json_decode(base64_decode(file_get_contents("php://input")),true);
        $out_trade_no = $request['out_trade_no'];
        $pay_where = substr($out_trade_no,0,2);
        $result = 0;
        $map['pay_order_number'] = $out_trade_no;
        switch ($pay_where) {
            case 'SP':
                $data = M('spend','tab_')->field('pay_status')->where($map)->find();
                $result = $data['pay_status'];
                break;
            case 'PF':
                $data = M('deposit','tab_')->field('pay_status')->where($map)->find();
                $result = $data['pay_status'];
                break;
            case 'AG':
                $data = M('agent','tab_')->field('pay_status')->where($map)->find();
                $result = $data['pay_status'];
                break;
            default:
                exit('accident order data');
                break;
        }
        if($result){
            echo base64_encode(json_encode(array("status"=>1,"return_code"=>"success","return_msg"=>"支付成功")));
            exit();
        }else{
            echo base64_encode(json_encode(array("status"=>0,"return_code"=>"fail","return_msg"=>"支付失败")));
            exit();
        }
    }

    /**
    *sdk客户端显示支付
    */
    public function payShow(){
        $map['type'] = 1;
        $map['status'] = 1;
        $data = M("tool","tab_")->where($map)->select();
        if(empty($data)){
            echo base64_encode(json_encode(array("status"=>0,"return_code"=>"fail","return_msg"=>"暂无数据")));
            exit();
        }
        foreach ($data as $key => $value) {
            $pay_show_data[$key]['mark']  = $value['name'];
            $pay_show_data[$key]['title'] = $value['title'];
        }
        echo base64_encode(json_encode(array("status"=>0,"return_code"=>"fail","return_msg"=>"成功","pay_show_data"=>$pay_show_data)));
        exit();
    }
		
		
		
		public function look() {
			$user = D('User')->getLoginInfo();
			$id = $_POST['id'];
			if(is_array($user)) {
				if(is_numeric($id) && $id>0) {
					
					$order = M('Order','tab_')->field('order_number')->where(['merchandise_id'=>$id,'buyer_id'=>$user['user_id']])->find();
					file_put_contents(dirname(__FILE__) .'/order.txt',json_encode($order));
					$weixn = new Weixin();
				
					$result = $weixn->weixin_orderquery($order['order_number']);
					file_put_contents(dirname(__FILE__) .'/order2.txt',json_encode($result));
					if($result == $order['order_number']) {
						$this->ajaxReturn(['status'=>1,'info'=>'','url'=>U('Trade/success',array('out_trade_no'=>$order['order_number']))],'json');
					} else {
						$this->ajaxReturn(['status'=>0,'info'=>$result],'json');
					}
				} else {
					$this->ajaxReturn(['status'=>0,'info'=>'参数错误'],'json');
				}
			} else {
				$this->ajaxReturn(['status'=>0,'info'=>'未登录'],'json');
			}
			
		}

    /**
     *平台币充值记录
     */

    public function add_deposit($data, $user='')
    {
        $deposit = M("deposit", "tab_");
        $deposit_data['order_number'] = '';
        $deposit_data['pay_order_number'] = $data['out_trade_no'];
        $deposit_data['user_id'] = $user['id'];
        $deposit_data['user_account'] = $user['account'];
        $deposit_data['user_nickname'] = $user['nickname'];
        $deposit_data['promote_id'] = $user['promote_id'];
        $deposit_data['promote_account'] = $user['promote_account'];
        $deposit_data['pay_amount'] = $data['amount'];
        $deposit_data['pay_status'] = 0;
        $deposit_data['pay_way'] = $data['pay_way'];
        $deposit_data['pay_source'] = 0;
        $deposit_data['pay_ip'] = get_client_ip();
        $deposit_data['pay_source'] = 0;
        $deposit_data['create_time'] = NOW_TIME;
        $deposit_data['sdk_version'] = '';
        $result = $deposit->add($deposit_data);
        return $result;
    }

    public function add_bind_recharge($param = array(),$user_entity='')
    {
        $data_spned['user_id'] = $param["user_id"];
        $data_spned['user_account'] = $user_entity["account"];
        $data_spned['user_nickname'] = $user_entity["nickname"];
        $data_spned['game_id'] = $param["game_id"];
        $data_spned['game_appid'] = $param["game_appid"];
        $data_spned['game_name'] = $param["game_name"];
        $data_spned['promote_id'] = $user_entity["promote_id"];
        $data_spned['promote_account'] = $user_entity["promote_account"];
        $data_spned['order_number'] = $param["order_number"];
        $data_spned['pay_order_number'] = $param["pay_order_number"];
        $data_spned['amount'] = $param['pay_amount'];
        $data_spned['create_time'] = NOW_TIME;
        $data_spned['pay_status'] = $param["pay_status"];
        $data_spned['pay_way'] = $param["pay_way"];
        $data_spned['recharge_ip'] = $param["spend_ip"];
        $data_spned['real_amount'] = $param["real_pay_amount"];
        $data_spned['zhekou'] = $param['discount'];
        $spend = M("bind_recharge", "tab_")->add($data_spned);
        return $spend;
    }


}
