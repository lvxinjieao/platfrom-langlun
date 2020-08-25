<?php
namespace Sdk\Controller;
use Think\Controller;
use Common\Api\GameApi;
use Org\WeixinSDK\Weixin;
use Org\HeepaySDK\Heepay;
use Org\UcenterSDK\Ucservice;

class PayController extends BaseController{

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
        if (C('UC_SET') == 1&&$param['is_uc']==1) {
            $uc = new Ucservice();
            $uc_user=$uc->get_user_from_uid($param['user_id']);
            if(empty($uc_user)){
                $this->set_message(1075,"fail","UC用户数据异常");
            }else{
                if($param['code'] == 1){
                    $game = M('GameSet',"tab_");
                    $map['game_id'] = $param['game_id'];
                    $game_data = $game->where($map)->find();
                    $uc_id = $uc->uc_recharge($param['user_id'],$uc_user['username'],$uc_user['username'],$param['game_id'],$param['game_appid'],get_game_name($param['game_id']),0,'',$uc_user['promote_id'],$uc_user['promote_account'],"",$out_trade_no,$param['price'],time(),$param['extend'],$param['payway'],get_client_ip(),$param['sdk_version'],1,$uc_user['platform'],$game_data['pay_notify_url'],$game_data['game_key']);
                }else{
                    $uc_id = $uc->uc_deposit($param['user_id'],$uc_user['username'],$uc_user['username'],$param['game_id'],$param['game_appid'],get_game_name($param['game_id']),0,'',$uc_user['promote_id'],$uc_user['promote_account'],"",$out_trade_no,$param['price'],time(),$param['extend'],$param['payway'],get_client_ip(),$param['sdk_version'],1,$uc_user['platform'],$game_data['pay_notify_url'],$game_data['game_key']);
                }
            }
        }

        $method=$param['sdk_version']==2?"wap":"mobile";
        $discount = $this->get_discount($param['game_id'],$user['promote_id'],$param['user_id']);
        $discount = $discount['discount'];
        $vo   = new \Think\Pay\PayVo();
        $vo->setBody("充值记录描述")
            ->setFee($param['price'])//支付金额
            ->setTitle($param['title'])
            ->setBody($param['body'])
            ->setOrderNo($out_trade_no)
            ->setService($param['server'])
            ->setSignType($param['signtype'])
            ->setPayMethod($method)
            ->setTable($table)
            ->setPayWay($param['payway'])
            ->setGameId($param['game_id'])
            ->setGameName(get_game_name($param['game_id']))
            ->setGameAppid($param['game_appid'])
            ->setServerId(0)
            ->setServerName("")
            ->setUserId($param['user_id'])
            ->setAccount($user['account'])
            ->setUserNickName($user['nickname'])
            ->setPromoteId($user['promote_id'])
            ->setPromoteName($user['promote_account'])
            ->setExtend($param['extend'])
            ->setSdkVersion($param['sdk_version'])
            ->setDiscount($discount);
        if($param['is_uc']==1){
            return $pay->buildRequestForm($vo,1);
        }else{
            return $pay->buildRequestForm($vo);
        }

    }

    public function asd(){

    }


    /**
    *支付宝移动支付
    */
    public function alipay_pay(){
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $request = json_decode(base64_decode(file_get_contents("php://input")),true);
        C(api('Config/lists'));
        if (empty($request)) {
            $this->set_message(1001, "fail", "登录数据不能为空");
        }
        if($request['price']<0){
            $this->set_message(1011,"fail","充值金额有误");
        }

        $game_set_data = get_game_set_info($request['game_id']);
        $request['apitype'] = "alipay";
        $request['config']  = "alipay";
        $request['signtype']= "MD5";
        $request['server']  = $request['sdk_version']==2?"alipay.wap.create.direct.pay.by.user":"mobile.securitypay.pay";
        $request['payway']  = 1;
        if($request['sdk_version']==2){
            $request['title']=$request['price'];
            $request['body']=$request['price'];
            $request['price']=0.01;
            $pay_url=$this->pay($request);
            file_put_contents("./Application/Sdk/OrderNo/".$request['user_id']."-".$request['game_id'].".txt",base64_encode(json_encode(['pay_url'=>$this->pay($request)])));
            
            echo base64_encode(json_encode(array('pay_url'=>"http://".$_SERVER['HTTP_HOST'].'/sdk.php/Spend/get_pay_url/user_id/'.$request['user_id'].'/game_id/'.$request['game_id'])));

        }else{
            $this->pay($request);
            $md5_sign = $this->encrypt_md5(base64_encode($data['arg']),$game_set_data["access_key"]);
            $data = array('status'=>200,"orderInfo"=>base64_encode($data['arg']),"out_trade_no"=>$data['out_trade_no'],"order_sign"=>$data['sign'],"md5_sign"=>$md5_sign);
            echo base64_encode(json_encode($data));

            
        }
        
    }


    /**
    *其他支付
    */
    public function outher_pay()
    {
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组 
        $request = json_decode(base64_decode(file_get_contents("php://input")), true);

        C(api('Config/lists'));
          if($request['price']<0){
            $this->set_message(0,"fail","充值金额有误");
        }
        if (get_wx_type() == 0) {//官方
            $table = $request['code'] == 1 ? "spend" : "deposit";
            $prefix = $request['code'] == 1 ? "SP_" : "PF_";
            $request['pay_order_number'] = $prefix . date('Ymd') . date('His') . sp_random_string(4);
            $request['pay_way'] = 3;
            $request['pay_status'] = 0;
            $request['spend_ip'] = get_client_ip();
            $weixn = new Weixin();
            //折扣
            $user = get_user_entity($request['user_id']);
            $discount = $this->get_discount($request['game_id'],$user['promote_id'],$request['user_id']);
            $discount = $discount['discount'];
            $pay_amount = $discount * $request['price'] / 10;
            $is_pay = json_decode($weixn->weixin_pay($request['title'], $request['pay_order_number'], $pay_amount, 'APP', 2), true);
            if ($is_pay['status'] === 1) {
                if (C('UC_SET') == 1&&$request['is_uc']==1) {
                    $uc = new Ucservice();
                    $uc_user=$uc->get_user_from_uid($request['user_id']);
                    if($uc_user){
                        if($request['code'] == 1){
                            $game = M('GameSet',"tab_");
                            $map['game_id'] = $request['game_id'];
                            $game_data = $game->where($map)->find();
                            $uc_id = $uc->uc_recharge($request['user_id'],$uc_user['username'],$uc_user['username'],$request['game_id'],$request['game_appid'],get_game_name($request['game_id']),0,'',$uc_user['promote_id'],$uc_user['promote_account'],"",$request['pay_order_number'],$request['price'],time(),$request['extend'],$request['pay_way'],get_client_ip(),$request['sdk_version'],1,$uc_user['platform'],$game_data['pay_notify_url'],$game_data['game_key']);
                        }else{
                            $uc_id = $uc->uc_deposit($request['user_id'],$uc_user['username'],$uc_user['username'],$request['game_id'],$request['game_appid'],get_game_name($request['game_id']),0,'',$uc_user['promote_id'],$uc_user['promote_account'],"",$out_trade_no,$request['price'],time(),$request['extend'],$request['pay_way'],get_client_ip(),$request['sdk_version'],1,$uc_user['platform'],$game_data['pay_notify_url'],$game_data['game_key']);
                        }
                    }
                }
                if(!$request['is_uc']||C('UC_SET')==0||find_uc_account($user['account'])){
                    if ($request['code'] == 1) {
                        $this->add_spend($request);
                    } else {
                        $this->add_deposit($request);
                    }
                }
                $json_data['appid'] = $is_pay['appid'];
                $json_data['partnerid'] = $is_pay['mch_id'];
                $json_data['prepayid'] = $is_pay['prepay_id'];
                $json_data['noncestr'] = $is_pay['noncestr'];
                $json_data['timestamp'] = $is_pay['time'];
                $json_data['package'] = "Sign=WXPay";
                $json_data['sign'] = $is_pay['sign'];
                $json_data['status'] = 200;
                $json_data['return_msg'] = "下单成功";
                $json_data['wxtype'] = "wx";
                echo base64_encode(json_encode($json_data));
            }
        } else {
            $game_set_data = get_game_set_info($request['game_id']);
            if(empty(C("weixin.partner"))||empty(C("weixin.key"))){
                  $this->set_message(1079, "faill", "未设置威富通账号");
            }
            $request['apitype'] = "swiftpass";
            $request['config']=array("partner"=>trim(C("weixin.partner")),"email"=>"","key"=>trim(C("weixin.key")));
            $request['signtype'] = "MD5";
            $request['server'] = "unified.trade.pay";
            $request['payway'] = 4;
            $result_data = $this->pay($request);
            $data['status'] = 200;
            $data['return_code'] = "success";
            $data['return_msg'] = "下单成功";
            $data['token_id'] = $result_data['token_id'];
            $data['out_trade_no'] = $result_data['out_trade_no'];
            $data['game_pay_appid'] = $game_set_data['game_pay_appid'];
            $data['wxtype'] = "wft";
            echo base64_encode(json_encode($data));
        }

    }

    //聚宝云支付
    public function jubaobar_pay(){
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $request = json_decode(base64_decode(file_get_contents("php://input")),true);
        wite_text(json_encode($request),dirname(__FILE__).'/a.txt');
        C(api('Config/lists'));
          if($request['price']<0){
            $this->set_message(1011,"fail","充值金额有误");
        }
        $prefix = $request['code'] == 1 ? "SP_" : "PF_";
        $out_trade_no = $prefix.date('Ymd').date('His').sp_random_string(4);
        $request['pay_order_number'] = $out_trade_no;
        $request['pay_status'] = 0;
        $request['pay_way']    = 5;
        $request['spend_ip']   = get_client_ip();
        if (C('UC_SET') == 1&&$request['is_uc']==1) {
            $uc = new Ucservice();
            $uc_user=$uc->get_user_from_uid($request['user_id']);
            if($uc_user){
                if($request['code'] == 1){
                    $game = M('GameSet',"tab_");
                    $map['game_id'] = $request['game_id'];
                    $game_data = $game->where($map)->find();
                    $uc_id = $uc->uc_recharge($request['user_id'],$uc_user['username'],$uc_user['username'],$request['game_id'],$request['game_appid'],get_game_name($request['game_id']),0,'',$uc_user['promote_id'],$uc_user['promote_account'],"",$request['pay_order_number'],$request['price'],time(),$request['extend'],$request['pay_way'],get_client_ip(),$request['sdk_version'],1,$uc_user['platform'],$game_data['pay_notify_url'],$game_data['game_key']);
                }else{
                    $uc_id = $uc->uc_deposit($request['user_id'],$uc_user['username'],$uc_user['username'],$request['game_id'],$request['game_appid'],get_game_name($request['game_id']),0,'',$uc_user['promote_id'],$uc_user['promote_account'],"",$out_trade_no,$request['price'],time(),$request['extend'],$request['pay_way'],get_client_ip(),$request['sdk_version'],1,$uc_user['platform'],$game_data['pay_notify_url'],$game_data['game_key']);
                }
            }
        }
        $user = get_user_entity($request['user_id']);
        if(!$request['is_uc']||C('UC_SET')==0||find_uc_account($user['account'])){
            if($request['code'] == 1 ){
            #TODO添加消费记录
                $this->add_spend($request);
            }else{
            #TODO添加平台币充值记录
                $this->add_deposit($request);
            }
        }
        
        $data['status'] = 200;
        $data['return_code'] = "success";
        $data['return_msg']  = "下单成功";
        $data['out_trade_no'] = $out_trade_no;
        $data['appid']  =   $request['sdk_version']==2?C("jubaobar.iosemail"):C("jubaobar.email");//1安卓 2苹果
        echo base64_encode(json_encode($data));
    }

    //汇付宝支付
    public function heepay_pay(){
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $request = json_decode(base64_decode(file_get_contents("php://input")),true);
        C(api('Config/lists'));
        if(C('UC_SET')==1){
            if(!is_array(find_uc_account($request['account']))){
               $this->set_message(1080,"fail","Uc用户暂不支持");
            }
        }
          if($request['price']<0){
            $this->set_message(1011,"fail","充值金额有误");
        }
        $prefix = $request['code'] == 1 ? "SP_" : "PF_";
        $out_trade_no = $prefix.date('Ymd').date('His').sp_random_string(4);
        $request['pay_order_number'] = $out_trade_no;
        $request['pay_status'] = 0;
        $request['pay_way']    = 6;
        $request['spend_ip']   = get_client_ip();
        if($request['code'] == 1 ){
            #TODO添加消费记录
            $this->add_spend($request);
        }else{
            #TODO添加平台币充值记录
            $this->add_deposit($request);
        }
        $pay['agent_id']="1664502";//商户号
        $pay['order_no']=$out_trade_no;
        $pay['time']=date('YmdHis', time());
        $pay['pay_type']=$request['pay_type'];
        $pay['amount']=$request['price'];
        $pay['user_ip']=get_client_ip();
        $pay['sign_key']="87FB9444028A4B14937A1905";//密钥
        $pay['payerName']="元宝";
        $pay['number']=1;
        $pay['goods_note']="支付";
        $heepay=new Heepay();
        $token_id=$heepay->heepay_pay($pay);
        $data['agent_id']="1664502";//商户号
        $data['status'] = 200;
        $data['return_code'] = "success";
        $data['return_msg']  = "下单成功";
        $data['token_id']=$token_id;
        
        $data['out_trade_no'] = $out_trade_no;
        echo base64_encode(json_encode($data));
    }


    /**
    *平台币支付
    */
    public function platform_coin_pay(){
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $request = json_decode(base64_decode(file_get_contents("php://input")),true);
        #记录信息
        if($request['price']<0){
            $this->set_message(1011,"fail","充值金额有误");
        }
        $out_trade_no = "PF_".date('Ymd').date('His').sp_random_string(4);
        $request['order_number']     = $out_trade_no;
        $request['pay_order_number'] = $out_trade_no;
        $request['out_trade_no']     = $out_trade_no;
        $request['title'] = $request['title'];
        $request['pay_status'] = 1;
        $request['pay_way'] = 0;
        $request['spend_ip']   = get_client_ip();
        if (C('UC_SET') == 1&&$request['is_uc']==1) {
            $uc = new Ucservice();
            $uc_user=$uc->get_user_from_uid($request['user_id']);
            if(empty($uc_user)){
                $this->set_message(1075,"fail","UC用户数据异常");
            }else{
                //UC存在则 查找其他数据库 配置文件里
                $sqltype = 2;
                $user_entity = M('User', 'tab_', C('DB_CONFIG2'))->where(array('account' => $request['account']))->find();
                if (empty($user_entity)) {
                    $sqltype = 3;
                    $user_entity = M('User', 'tab_', C('DB_CONFIG3'))->where(array('account' => $request['account']))->find();
                }
                if(empty($user_entity)){
                    $this->set_message(1066,"fail","用户数据异常");
                }
                $discount = 10;
            }
        }else{
            $user_entity = get_user_entity($request['user_id']);
            $discount_arr = $this->get_discount($request['game_id'],$user_entity['promote_id'],$request['user_id']);
            $discount = $discount_arr['discount'];
        }
        $result = false;
        switch ($request['code']) {
            case 1:#非绑定平台币
                $user = M("user","tab_");
                $real_price = $request['price'] * $discount / 10;
                if ($user_entity['balance'] < $real_price) {
                    echo base64_encode(json_encode(array("status"=>-2,"return_code"=>"fail","return_msg"=>"余额不足")));
                    exit();
                }
                //生成UC订单
                if($sqltype!=''){
                    $game = M('GameSet',"tab_");
                    $map['game_id'] = $request['game_id'];
                    $game_data = $game->where($map)->find();
                    $result = $uc->uc_recharge($request['user_id'],$uc_user['username'],$uc_user['username'],$request['game_id'],$request['game_appid'],get_game_name($request['game_id']),0,'',$uc_user['promote_id'],$uc_user['promote_account'],"",$out_trade_no,$request['price'],time(),$request['extend'],$request['pay_way'],get_client_ip(),$request['sdk_version'],1,$uc_user['platform'],$game_data['pay_notify_url'],$game_data['game_key']);
                }
                if($sqltype==2){
                    M('User','tab_',C('DB_CONFIG2'))->where(array('account'=>$user_entity['account']))->setDec("balance",$request['price']);
                    $uc->uc_rechange_notify($data_spned['pay_order_number'],1);
                    if($result){
                        echo base64_encode(json_encode(array("return_status"=>200,"return_code"=>"success","return_msg"=>"支付成功","out_trade_no"=>$out_trade_no)));exit;
                    }
                }elseif($sqltype==3){
                    M('User','tab_',C('DB_CONFIG3'))->where(array('account'=>$user_entity['account']))->setDec("balance",$request['price']);
                    $uc->uc_rechange_notify($data_spned['pay_order_number'],200);
                    if($result){
                        echo base64_encode(json_encode(array("return_status"=>200,"return_code"=>"success","return_msg"=>"支付成功","out_trade_no"=>$out_trade_no)));exit;
                    }
                }else{
                    #扣除平台币
                    $user->where("id=".$request["user_id"])->setDec("balance",$real_price);
                    #TODO 添加绑定平台币消费记录
                    $result = $this->add_spend($request);
                    #检查返利设置
                    $this->set_ratio($request['pay_order_number']);
                }
                
                break;
             case 2:#绑定平台币
                $user_play = M("UserPlay","tab_");
                $user_play_map['user_id'] = $request['user_id'];
                $user_play_map['game_id'] = $request['game_id'];
                $user_play_data = $user_play->where($user_play_map)->find();

                if($user_play_data['bind_balance'] < $request['price']){
                    echo base64_encode(json_encode(array("status"=>1076,"return_code"=>"fail","return_msg"=>"余额不足")));
                    exit();
                }
                #扣除平台币
                $user_play->where($user_play_map)->setDec("bind_balance",$request['price']);
                #TODO 添加绑定平台币消费记录
                $result = $this->add_bind_spned($request);
                break;
            default:
                echo base64_encode(json_encode(array("status"=>1081,"return_code"=>"fail","return_msg"=>"支付方式不明确")));
                exit();
            break;
        }
        $game = new GameApi();
        $game->game_pay_notify($request,$request['code']);
        if($result){
            echo base64_encode(json_encode(array("return_status"=>200,"return_code"=>"success","return_msg"=>"支付成功","out_trade_no"=>$out_trade_no)));
        }
        else{
            echo base64_encode(json_encode(array("status"=>1078,"return_code"=>"fail","return_msg"=>"支付失败")));
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
            echo base64_encode(json_encode(array("status"=>200,"return_code"=>"success","return_msg"=>"支付成功")));
            exit();
        }else{
            echo base64_encode(json_encode(array("status"=>1078,"return_code"=>"fail","return_msg"=>"支付失败")));
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
            echo base64_encode(json_encode(array("status"=>1082,"return_code"=>"fail","return_msg"=>"暂无数据")));
            exit();
        }
        foreach ($data as $key => $value) {
            $pay_show_data[$key]['mark']  = $value['name'];
            $pay_show_data[$key]['title'] = $value['title'];
        }
        echo base64_encode(json_encode(array("status"=>200,"return_code"=>"fail","return_msg"=>"成功","pay_show_data"=>$pay_show_data)));
        exit();
    }


   public function wite_text($txt,$name){
    $myfile = fopen($name, "w") or die("Unable to open file!");
    fwrite($myfile, $txt);
    fclose($myfile);
}

}
