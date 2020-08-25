<?php
namespace App\Controller;
use Org\WeiXinSDK\Weixin;
use Org\JtpaySDK\Jtpay;

class PayController extends BaseController{

    const ALI_PAY = 1;          //支付宝支付
    const WEIXIN_PAY =2;        //微信支付
    const PLATFORM_COIN = 1;        //平台币
    const BIND_PLATFORM_COIN = 2;   //绑定平台币

    public function payWay(){
        $paytype = M('tool', 'tab_')->field('status,name')->where(['name'=>['in','wei_xin_app,alipay,goldpig']])->select();
        foreach ($paytype as $key => $value) {
            $pay[$value['name']] = $value['status'];
        }
        if($pay['wei_xin_app']==1){
            $pay['weixin'] = 1;
        }
        unset($pay['wei_xin_app']);
        $this->set_message(200,'success',$pay);
    }

    /**
     * 支付宝移动支付
     * @param  string user_id 用户ID
     * @param  string account 充值账号
     * @param  int price 充值金额
     * @return base64加密的json格式
     * @author yyh
     */
    public function alipay_pay($token,$sdk_version=1){
        switch ($sdk_version) {
            case 1:
            case 2:
                break;

            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        $this->auth($token);
        $request = I("post.");
        if (pay_set_status('alipay') == 0) {
            $this->set_message(1115,'支付宝支付未开启');
        }
        if($request['price']<0){
            $this->set_message(1011,"充值金额有误");
        }
        $request['user_id'] = USER_ID;
        $request['apitype'] = "alipay";
        $request['config']  = "alipay";
        $request['signtype']= "MD5";
        $request['server']  = "mobile.securitypay.pay";
        $request['payway']  = 1;
        $request['paysource']  = 2;
        $request['method']  = 'mobile';
        $request['pay_amount'] = $request['price'];
        $request['cost'] = $request['price'];
        switch ($request['code']) {
            case '1':
                $this->set_message(1090,"不支持该方式");
                break;
            case '2':
                $request['title'] = "充值".$request['price']."平台币";
                $request['body'] = "充值平台币";
                break;
            case '3':
                $game_id = $request['game_id'];
                $game = M("Game","tab_")->field('id,bind_recharge_discount,game_name,game_appid')->find($game_id);
                if(empty($game)){
                    $this->set_message(1057,"游戏不存在");
                }
                $discount = empty($game['bind_recharge_discount']) ? 10 : $game['bind_recharge_discount'];
                $real_pay_amount = round($request['price'] * $discount / 10,2);
                //构建商品信息
                $request['title'] = "绑定平台币";
                $request['body'] = "绑定平台币充值";
                $request['game_id'] = $game_id;
                $request['game_name'] = $game['game_name'];
                $request['game_appid'] = $game['game_appid'];
                $request['price'] = $real_pay_amount;
                $request['discount'] = $discount;
                break;
            default:
                exit('error');
                break;
        }
        $data = $this->pay($request);
        $md5_sign = $this->encrypt_md5($data['arg'],'mengchuang');
        $data = array('status'=>1,"orderInfo"=>$data['arg'],"out_trade_no"=>$data['out_trade_no'],"order_sign"=>$data['sign'],"md5_sign"=>$md5_sign);

        $this->set_message(200,'success',$data);
    }

    /**
     * [wx_pay 微信app支付充值平台币]
     * @param  [type] $token       [description]
     * @param  [type] $sdk_version [description]
     * @return [type]              [description]
     * @author [yyh] <[<email address>]>
     */
    public function wx_pay($token,$sdk_version=1){
        switch ($sdk_version) {
            case 1:
            case 2:
                break;

            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        $this->auth($token);
        $request = !empty(I("post."))?I('post.'):I('get.');
        $request['user_id'] = USER_ID;
        C(api('Config/lists'));
        $request['pay_amount'] = $request['price'];
        $request['cost'] = $request['price'];
        if($request['price']<0){
            $this->set_message(1011,"充值金额有误");
        }
        if (get_wx_type(1) == 1) {//app支付
            $request['pay_amount'] = $request['price'];
            switch ($request['code']) {
                case '1':
                    $this->set_message(1090,"不支持该方式");
                    break;
                case '2':
                    $prefix = "PF_";
                    $request['title'] = "充值".$request['price']."平台币";
                    break;
                case '3':
                    $prefix = "BR_";
                    $request['title'] = "充值".$request['price']."绑定平台币";
                    $game_id = $request['game_id'];
                    $game = M("Game","tab_")->field('id,bind_recharge_discount,game_name,game_appid')->find($game_id);
                    if(empty($game)){
                        $this->set_message(1057,"游戏不存在");
                    }
                    $discount = empty($game['bind_recharge_discount']) ? 10 : $game['bind_recharge_discount'];
                    $real_pay_amount = round($request['price'] * $discount / 10,2);
                    //构建商品信息
                    $request['game_id'] = $game_id;
                    $request['game_name'] = $game['game_name'];
                    $request['game_appid'] = $game['game_appid'];
                    $request['price'] = $real_pay_amount;
                    $request['discount'] = $discount;
                    break;
                default:
                    exit('error');
                    break;
            }
            $request['pay_order_number'] = $prefix . date('Ymd') . date('His') . sp_random_string(4);
            $request['pay_way'] = 3;
            $request['pay_status'] = 0;
            $request['spend_ip'] = get_client_ip();
            $weixn = new Weixin();
            $is_pay = json_decode($weixn->weixin_pay($request['title'], $request['pay_order_number'], $request['price'], 'APP', 2), true);
            if ($is_pay['status'] === 1) {
                if($request['code']==2){
                    $this->add_deposit($request);
                }else{
                    $this->add_bind_recharge($request);
                }
                $json_data['appid'] = $is_pay['appid'];
                $json_data['partnerid'] = $is_pay['mch_id'];
                $json_data['out_trade_no'] = $is_pay['prepay_id'];
                $json_data['noncestr'] = $is_pay['noncestr'];
                $json_data['timestamp'] = $is_pay['time'];
                $json_data['package'] = "Sign=WXPay";
                $json_data['sign'] = $is_pay['sign'];
                $json_data['status'] = 1;
                $json_data['return_msg'] = "下单成功";
                $json_data['wxtype'] = "wx";
                $this->set_message(200,'success',$json_data);
            }else{
                $this->set_message(1028,$is_pay['return_msg']);
            }
        }else{
            $this->set_message(1115,"支付未开启");
        }

    }

    public function goldpig_pay($token)
    {
        $this->auth($token);
        $request = I('post.');

        if (pay_set_status('goldpig') == 0) {
            $this->set_message(1115,'金猪支付未开启');
        }
        #判断账号是否存在
        $user = get_user_entity(USER_ID);

        $data2["user_id"] = $user["id"];
        $data2["user_account"] = $user['account'];
        $data2["user_nickname"] = $user['nickname'];

        if ($request['price'] < 1) {
            $this->set_message(1101,"金猪充值金额不能小于1元");
        }
        switch ($request['code']) {
            case '1':
                $this->set_message(1090,"不支持该方式");
                break;
            case '2':
                #支付配置
                $data['order_no'] = 'PF_' . date('Ymd') . date('His') . sp_random_string(4);
                $data['fee']      = $request['price'];//$_POST['amount'];
                #平台币记录数据
                $data['order_number'] = "";
                $data['pay_order_number'] = $data['order_no'];
                $data['user_id'] = $data2['user_id'];
                $data['user_account'] = $data2['user_account'];
                $data['user_nickname'] = $data2['user_nickname'];
                $data['promote_id'] = $user['promote_id'];
                $data['promote_account'] = $user['promote_account'];
                $data['pay_amount'] =  $request['price'];
                $data['pay_status'] = 0;
                $data['pay_way'] = 7;//金猪
                $data['pay_source'] = 1;
                $data['spend_ip'] = get_client_ip();
                $this->add_deposit($data);
                break;
            case '3':
                $data['order_no'] = 'BR_' . date('Ymd') . date('His') . sp_random_string(4);
                $game_id = $request['game_id'];
                $game = M("Game","tab_")->field('id,bind_recharge_discount,game_name,game_appid,sdk_version')->find($game_id);
                if(empty($game)){
                    $this->set_message(1057,"游戏不存在");
                }
                $discount = empty($game['bind_recharge_discount']) ? 10 : $game['bind_recharge_discount'];
                $real_pay_amount = round($request['price'] * $discount / 10,2);
                //构建商品信息
                $data['order_number'] = "";
                $data['pay_order_number'] = $data['order_no'];
                $data['user_id'] = $data2['user_id'];
                $data['user_account'] = $data2['user_account'];
                $data['user_nickname'] = $data2['user_nickname'];
                $data['promote_id'] = $user['promote_id'];
                $data['promote_account'] = $user['promote_account'];
                $data['game_id'] = $game['id'];
                $data['game_appid'] = $game['game_appid'];
                $data['game_name'] = $game['game_name'];
                $data['price'] =  $real_pay_amount;
                $data['pay_amount'] =  $request['price'];
                $data['pay_way'] = 7;//金猪
                $data['pay_source'] = 1;
                $data['spend_ip'] = get_client_ip();
                $data['discount'] = $discount;
                $data['sdk_version'] = $game['sdk_version'];
                $data['fee'] = $real_pay_amount;
                $this->add_bind_recharge($data);
                break;
            default:
                exit('error');
                break;
        }


        $urlparams['UserName'] = $data['user_account'];
        $urlparams['fee'] = $data["fee"];
        $urlparams['jinzhua'] = $data["pay_order_number"];
        $urlparams['jinzhub'] = signsortData($urlparams,C('goldpig.key'));
        $urlparams['gamename'] = $request['code'] == 2 ? '平台币充值' : '绑币平台币充值';
        $urlparams['UserId'] = $data['user_id'];

        $url = U('Subscriber/user_recharge_pig',sortData($urlparams),false);
        $http = is_https()?'https':'http';
        $result = $http.'://'.$_SERVER["SERVER_NAME"].$url;
        $result = str_replace("app.php","mobile.php",$result);
        $this->set_message(200,'success',$result);

    }
    /**
     * 支付设置
     * @param  array $param 支付参数
     * @return 支付回调信息
     * @author lyf
     */
    private function pay($param=array()){
        $table  = $param['code'] == 1 ? "spend" : ($param['code']==2?"deposit":'bind_recharge');
        $prefix = $param['code'] == 1 ? "SP_" : ($param['code']==2?"PF_":'BR_');
        $out_trade_no = $prefix.date('Ymd').date('His').sp_random_string(4);
        $user = get_user_entity($param['user_id']);
        if(empty($user)){$this->set_message(0,"fail","用户不存在");}
        $pay  = new \Think\Pay($param['apitype'],C($param['config']));
        $vo   = new \Think\Pay\PayVo();
        $vo->setFee($param['price'])//实际支付金额
            ->setMoney($param['pay_amount'])//原价
            ->setTitle($param['title'])
            ->setBody($param['body'])
            ->setOrderNo($out_trade_no)
            ->setService($param['server'])
            ->setSignType($param['signtype'])
            ->setPayMethod($param['method'])
            ->setTable($table)
            ->setPayWay($param['payway'])
            ->setPaySource($param['paysource'])
            ->setSdkVersion($param['sdk_version'])
            ->setGameId($param['game_id'])
            ->setGameName($param['game_name'])
            ->setGameAppid($param['game_appid'])
            ->setServerId(0)
            ->setServerName("")
            ->setUserId($param['user_id'])
            ->setAccount($user['account'])
            ->setUserNickName($user['nickname'])
            ->setPromoteId($user['promote_id'])
            ->setPromoteName($user['promote_account'])
            ->setOpenid($param['openid'])
            ->setExtend($param['extend'])
            ->setDiscount($param['discount'])
            ->setCallback($param['callbackurl']);
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
    public function recharge($token,$pay_amount,$good_info,$pay_way,$promote_id){
        $this->auth($token);
        $good_info = json_decode($good_info,true);
        switch ($good_info['type']){
            case self::PLATFORM_COIN:
                $table = "deposit";
                $prefix = "PF_";
                $good['real_pay_amount'] = $pay_amount;
                $good['title'] = "平台币";
                $good['body'] = "平台币充值";
                break;
            case self::BIND_PLATFORM_COIN:
                $table = "bind_recharge";
                $prefix = "BR_";
                $game_id = $good_info['game_id'];
                $game = M("Game","tab_")->find($game_id);
                if(empty($game)){
                    $this->set_old_message(1057,"游戏不存在");
                }
                $discount = empty($game['bind_recharge_discount']) ? 10 : $game['bind_recharge_discount'];
                $real_pay_amount = round($pay_amount * $discount / 10,2);
                //构建商品信息
                $good['title'] = "绑定平台币";
                $good['body'] = "绑定平台币充值";
                $good['game_id'] = $game_id;
                $good['game_name'] = $game['game_name'];
                $good['game_appid'] = $game['game_appid'];
                $good['real_pay_amount'] = $real_pay_amount;
                $good['discount'] = $discount;
                break;
            default:
                $this->set_old_message(1055,"商品信息错误");
        }
        $good['pay_amount'] = $pay_amount;
        $good['cost'] = $pay_amount;
        $good['promote_id'] = $promote_id;
        switch ($pay_way){
            case self::ALI_PAY :
                $this->alipay_pay(USER_ACCOUNT,$good,$table,$prefix);
                break;
            case self::WEIXIN_PAY:
                $this->weixin_pay(USER_ACCOUNT,$good,$table,$prefix);
                break;
            default:$this->set_old_message(1056,"暂无该支付选项");
        }
    }

    /**
     *支付验证
     */
    public function pay_validation(){
        $request = I("post.");
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
                $this->set_message(1090,'参数错误',"");
                break;
        }
        if($result){
            $this->set_message(200,'success','');
        }else{
            $this->set_message(1078,'支付失败','');
        }
    }
}