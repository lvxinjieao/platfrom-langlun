<?php 

namespace Sdk\Controller; 

use Think\Controller; 

use Common\Api\GameApi; 

use Org\SwiftpassSDK\Swiftpass; 

use Org\WeixinSDK\WeiXin; 

use Org\JtpaySDK\Jtpay; 

use Org\GoldPig\GoldPig; 





class ExchangeController extends BaseController{    /** 

    *ios移动支付 

    */ 
   
    public function exchange(){ 

        C(api('Config/lists')); 

        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组 

       $request = json_decode(base64_decode(file_get_contents("php://input")),true); 

         if(C('UC_SET')==1){ 

           if(!is_array(find_uc_account($request['account']))){ 

              $this->set_message(0,"fail","Uc用户暂不支持"); 

           } 

        } 
         $extend_data = M('spend','tab_')->where(array('extend'=>$request['extend'],'game_id'=>$request['game_id']))->find();
        if($extend_data){
            $this->set_message(1089,"fail","订单号重复，请关闭支付页面重新支付");
        }
        file_put_contents("./Application/Sdk/Scheme/".$request['game_id'].".txt",$request['scheme']); 

         if(pay_set_status('alipay')==1 || (pay_set_status('goldpig') == 1 && C('ZFB_TYPE') == 1)){

                $prefix = $request['code'] == 1 ? "SP_" : "PF_"; 

                $out_trade_no = $prefix.date('Ymd').date('His').sp_random_string(4); 

                $request['pay_order_number'] = $out_trade_no; 

                $request['pay_status'] = 0; 

                $request['pay_way']    = 7; 

                $request['spend_ip']   = get_client_ip(); 

             if(get_game_appstatus2($request['game_id'])){ 

                 file_put_contents("./Application/Sdk/OrderNo/".$request['user_id']."-".$request['game_id'].".txt",think_encrypt(json_encode($request))); 

                 echo base64_encode(json_encode(array('status'=>200,'out_trade_no'=>$out_trade_no,'img'=>"http://".$_SERVER['HTTP_HOST'].'/sdk.php/Spend/pay_way/user_id/'.$request['user_id'].'/game_id/'.$request['game_id'].'/type/1')));exit; 

             }else{ 

                 #获取订单信息 

                 if($request['code'] == 1 ){ 

                     #TODO添加消费记录 

                     $this->add_spend($request); 

                 }else{ 

                     #TODO添加平台币充值记录 

                     $this->add_deposit($request); 

                 } 

                $data = array("status"=>200,"out_trade_no"=>$out_trade_no,'img'=>''); 

                echo base64_encode(json_encode($data)); 

         } 

       }else{ 

        #获取订单信息 

        $prefix = $request['code'] == 1 ? "SP_" : "PF_"; 

        $out_trade_no = $prefix.date('Ymd').date('His').sp_random_string(4); 

        $data = array("status"=>200,"out_trade_no"=>$out_trade_no); 

        $request['pay_order_number'] = $out_trade_no; 

        $request['pay_status'] = 0; 

        $request['pay_way']    = 7; 

        $request['title'] = $request['productId']; 

        $request['spend_ip']   = get_client_ip(); 

        if($request['code'] == 1 ){ 

            #TODO添加消费记录 

            $this->add_spend($request); 

        }else{ 

            #TODO添加平台币充值记录 

            $this->add_deposit($request); 

        } 

        echo base64_encode(json_encode($data)); 

    } 

} 

 /** 

    *支付宝移动支付 

    */ 

    public function apple_alipay_pay($user_id,$game_id){ 

        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组 

        $file=file_get_contents("./Application/Sdk/OrderNo/".$user_id."-".$game_id.".txt"); 

         $request = json_decode(think_decrypt($file),true); 

        C(api('Config/lists')); 

        if (empty($request)) { 

            $this->set_message(0, "fail", "登录数据不能为空"); 

        } 

        if($request['price']<0){ 

            $this->set_message(0,"fail","充值金额有误"); 

        } 


        $extend_data = M('spend','tab_')->where(array('extend'=>$request['extend'],'game_id'=>$request['game_id']))->find();
        if($extend_data){
            $this->set_message(1089,"fail","订单号重复，请关闭支付页面重新支付");
        }
        if(get_zfb_type()==0){

            $game_set_data = get_game_set_info($request['game_id']); 

            $request['apitype'] = "alipay"; 

            $request['config']  = "alipay"; 

            $request['signtype']= "MD5"; 

            $request['server']  = "alipay.wap.create.direct.pay.by.user"; 

            $request['payway']  = 1; 

            $request['title']=$request['price']; 

            $request['body']=$request['price']; 

            $pay_url=$this->pay($request); 

            redirect($pay_url['url']); 

            

        }else{

            if( empty(C('goldpig.partner'))||empty(C('goldpig.wooolid'))){ 

                $this->set_message(1009, "fail", "支付参数未配置"); 

            } 

            $table = $request['code'] == 1 ? "spend" : "deposit"; 

            $prefix = $request['code'] == 1 ? "SP_" : "PF_"; 

            // $request['pay_order_number'] =$// $prefix . date('Ymd') . date('His') . sp_random_string(4); 

            $request['pay_way'] = 8; 

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



            $goldpig=new GoldPig();

            $pay_url = $goldpig->GoldPig($user['account'],$pay_amount,26,$request['pay_order_number']);

            if($pay_url['status']==0){

                $url='http://'.$_SERVER['HTTP_HOST'];

                redirect($url); 

            }else{

                redirect($pay_url['msg']); 

            }

        }            

    } 

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

        $vo->setBody("充值") 

            ->setFee($param['price'])//支付金额 

            ->setTitle($param['title']) 

            ->setOrderNo($out_trade_no) 

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

        if($param['is_uc']==1){ 

            return $pay->buildRequestForm($vo,1); 

        }else{ 

            $pay_['url']= $pay->buildRequestForm($vo); 

            $pay_['out_trade_no']= $out_trade_no; 

            return $pay_; 

        } 

    } 

    /** 

     *微信支付 

     */ 

    public function apple_weixin_pay($user_id,$game_id) 

    { 

        $file=file_get_contents("./Application/Sdk/OrderNo/".$user_id."-".$game_id.".txt"); 

        $request = json_decode(think_decrypt($file),true); 

        if (empty($request)) { 

            $this->set_message(0, "fail", "登录数据不能为空"); 

        } 

        C(api('Config/lists')); 

        if($request['price']<0){ 

            $this->set_message(0,"fail","充值金额有误"); 

        } 
         $extend_data = M('spend','tab_')->where(array('extend'=>$request['extend'],'game_id'=>$request['game_id']))->find();
        if($extend_data){
            $this->set_message(1089,"fail","订单号重复，请关闭支付页面重新支付");
        }

        $table = $request['code'] == 1 ? "spend" : "deposit"; 

        $prefix = $request['code'] == 1 ? "SP_" : "PF_"; 

        $request['pay_way'] = 3; 

        $request['pay_status'] = 0; 

        $request['spend_ip'] = get_client_ip(); 

        //折扣 

        $user = get_user_entity($request['user_id']); 

        $discount = $this->get_discount($request['game_id'],$user['promote_id'],$request['user_id']); 

        $discount = $discount['discount']; 

        $pay_amount = $discount * $request['price'] / 10; 

        //0 官方 1威富通  2俊付通

        if (get_wx_type() == 0) { 

            $weixn = new WeiXin(); 

            $is_pay = json_decode($weixn->weixin_pay("充值", $request['pay_order_number'], $pay_amount, 'MWEB'), true); 

            if($is_pay['status']==1){ 

                if($request['code']==1){ 

                    $this->add_spend($request); 

                }else{ 

                    $this->add_deposit($request); 

                } 



                $json_data['url'] = $is_pay['mweb_url'].'&redirect_url='.(is_ssl()?'https%3A%2F%2F':'http%3A%2F%2F'). $_SERVER ['HTTP_HOST'] . "%2Fsdk.php%2FSpend%2Fpay_success2%2Forderno%2F".$request['pay_order_number'].'%2Fgame_id%2F'.$request['game_id'] ; 

            }else{ 

                $json_data['url'] = "http://" . $_SERVER['HTTP_HOST']; 

            } 

        }elseif(get_wx_type() == 2){



            if($request['code']==1){

                   $this->add_spend($request);

                }else{

                  $this->add_deposit($request);

                }



            $jtpay=new Jtpay(); 

            

            $json_data['url']=$jtpay->jt_pay($request['pay_order_number'],$pay_amount,$user['account'],get_client_ip(),"sdk",3,'http://' . $_SERVER ['HTTP_HOST'] . "/sdk.php/Spend/pay_success2/orderno/".$request['pay_order_number'].'/game_id/'.$request['game_id'],3,2);//ios



        }elseif(get_wx_type() == 3){ 

            if( empty(C('goldpig.partner'))||empty(C('goldpig.wooolid'))){ 

                $this->set_message(1009, "fail", "支付参数未配置"); 

            } 

            $request['pay_way'] = 8; 





            if($request['code']==1){

               $this->add_spend($request);

            }else{

              $this->add_deposit($request);

            }



            $goldpig=new GoldPig();

            $res = $goldpig->GoldPig($user['account'],$pay_amount,29,$request['pay_order_number']);

            if($res['status']==1){

                $json_data['url']=$res['msg']; 

            }else{

                $json_data['url']='http://'.$_SERVER ['HTTP_HOST']; 

            }



            

            

        }else{ 

            $Swiftpass=new Swiftpass(C('weixin_gf.partner'),C('weixin_gf.key')); 

            $param['service']="pay.weixin.wappay"; 

            $param['ip']=  $request['spend_ip']; 

            $param['pay_amount']=$pay_amount;//; 

            $param['out_trade_no']= $request['pay_order_number']; 

            $param['game_name']= get_game_name($request['game_id']); 

            $param['body']="游戏充值"; 

            $param['callback_url']='http://' . $_SERVER ['HTTP_HOST'] . "/sdk.php/Spend/pay_success/orderno/".$request['pay_order_number'].'/game_id/'.$request['game_id']; 

            $url=$Swiftpass->submitOrderInfo($param); 

            if($url['status']==0){ 

              $request['pay_way'] = 4; 

                if($request['code']==1){ 

                    $this->add_spend($request); 

                }else{ 

                    $this->add_deposit($request); 

                } 

                $json_data['url']=$url['pay_info']; 

            }else{ 

                $json_data['url']='http://'.$_SERVER ['HTTP_HOST']; 

            } 

        } 

        redirect($json_data['url']); 

    } 

    /** 

    *苹果支付验证 

    */ 

    public function exchangeVerify(){ 

        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组 

        $request = json_decode(base64_decode(file_get_contents("php://input")),true); 

        //开始执行验证 

        try 

        { 

            $data = $this->getSignVeryfy($request, 1); 

            $info = json_decode($data,true); 

             if($info['status']==21007){ 

                $data = $this->getSignVeryfy($request, 2); 

                $info = json_decode($data,true); 

            } 

            if($info['status'] == 0){ 

                $paperVerify=M('spend','tab_')->field('id,order_number')->where(array('pay_way'=>7,'order_number'=>$info['receipt']['transaction_id']))->find(); 

                if($paperVerify){ 

                    echo base64_encode(json_encode(array("status"=>0,"return_code"=>"fail","return_msg"=>"凭证重复"))); 

                    exit(); 

                } 

                $out_trade_no = $request['out_trade_no']; 

                $pay_where = substr($out_trade_no,0,2); 

                $result = 0; 

                $map['pay_order_number'] = $out_trade_no; 

                $payamountVerify=M('spend','tab_')->field('id,pay_order_number,extend,pay_amount')->where($map)->find(); 

                if($payamountVerify['pay_amount']!=$request['price']){ 

                    $disdata=array(); 

                    $disdata['spend_id']=$payamountVerify['id']; 

                    $disdata['pay_order_number']=$payamountVerify['pay_order_number']; 

                    $disdata['extend']=$payamountVerify['extend']; 

                    $disdata['last_amount']=$request['price']; 

                    $disdata['currency']=$request['currency']; 

                    $disdata['create_time']=NOW_TIME; 

                    $pay_distinction=M('spend_distinction','tab_')->add($disdata); 

                    if(!$pay_distinction){ 

                        \Think\Log::record('数据插入失败 pay_order_number'.$payamountVerify['pay_order_number']); 

                    } 

                } 

                $field = array("pay_status"=>1,"pay_amount"=>$request['price'],"receipt"=>$data,"order_number"=>$info['receipt']['transaction_id']); 

                switch ($pay_where) { 

                    case 'SP': 

                        $result = M('spend','tab_')->where($map)->setField($field); 

                        $param['out_trade_no'] = $out_trade_no; 

                        $game = new GameApi(); 

                        $game->game_pay_notify($param); 

                        break; 

                    case 'PF': 

                        $result = M('deposit','tab_')->where($map)->setField($field); 

                        break; 

                    case 'AG': 

                        $result = M('agent','tab_')->where($map)->setField($field); 

                        break; 

                    default: 

                        exit('accident order data'); 

                        break; 

                } 

                if($result){ 

                    $this->set_ratio($out_trade_no); 

                    echo base64_encode(json_encode(array("status"=>200,"return_code"=>"success","return_msg"=>"支付成功"))); 

                    exit(); 

                }else{ 

                    echo base64_encode(json_encode(array("status"=>0,"return_code"=>"fail","return_msg"=>"支付状态修改失败"))); 

                    exit(); 

                } 

            }else{ 

                echo base64_encode(json_encode(array("status"=>0,"return_code"=>"fail","return_msg"=>"支付失败"))); 

                exit(); 

            } 

        } 

        //捕获异常 

        catch(Exception $e) 

        { 

            echo 'Message: ' .$e->getMessage(); 

        } 

    } 

    private function getSignVeryfy($receipt, $isSandbox = 1){ 

        if ($isSandbox==2) { 

            $endpoint = 'https://sandbox.itunes.apple.com/verifyReceipt'; 

        } 

        else { 

            $endpoint = 'https://buy.itunes.apple.com/verifyReceipt'; 

        } 

        $postData = json_encode( 

            array('receipt-data' => $receipt["paper"]) 

        ); 

        $ch = curl_init($endpoint); 

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 

        curl_setopt($ch, CURLOPT_POST, true); 

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); 

        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);  //这两行一定要加，不加会报SSL 错误 

        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0); 

        $response = curl_exec($ch); 

        $errno    = curl_errno($ch); 

        $errmsg   = curl_error($ch); 

        curl_close($ch); 

        //判断时候出错，抛出异常 

        if ($errno != 0) { 

            throw new \Think\Exception($errmsg, $errno); 

        } 

        return $response; 

    } 

} 