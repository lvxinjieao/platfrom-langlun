<?php
namespace Mobile\Event;
use Org\WeiXinSDK\WeiXinOauth;
use Org\WeiXinSDK\Weixin;
use Think\Controller;
use Common\Api\PayApi;

class GameEvent extends Controller{
	public function get_wx_code($_GET,$_POST){
		file_put_contents(dirname(__FILE__).'/2323.txt',11);
		$jieminame=get_uname();

        if($_GET['state']!=''&&isset($_GET['code'])){

        	$stateparam=explode(',',$_GET['state']);//微信支付参数

        	$jieminame=simple_decode($stateparam[2]);

        }

		$user = get_user_entity($jieminame,true);


		if (empty($user)) {

			$this->ajaxReturn(array('msg'=>'用户不存在！','status'=>0));exit();

		}else{

			$data["user_id"]       = $user["id"];

			$data["user_account"]  = $user['account'];

			$data["user_nickname"] = $user['nickname'];

		}


		$game_data = get_game_entity($_POST['game_appid']);

		if(empty($game_data)){$this->ajaxReturn(array('msg'=>'游戏不存在！','status'=>0));exit();}

		Vendor("WxPayPubHelper.WxPayPubHelper");

		// 使用jsapi接口

        $jsApi = new \JsApi_pub(C('wei_xin.appsecret'),C('wei_xin.email'),C('wei_xin.key'));

        //=========步骤1：网页授权获取用户openid============

        //通过code获得openid

        if (!isset($_GET['code']))

        {

        	if($_POST['sign']!=session('game_pay_sign')){

        		$this->ajaxReturn(array('msg'=>'数据异常！','status'=>0));

        	}

        	#支付配置

			$data['order_no'] = 'SP_'.date('Ymd').date ( 'His' ).sp_random_string(4);

			$data["game_id"]	   = $game_data['id'];

			$data["game_appid"]	   = $_POST["game_appid"];

			$data["game_name"]     = $game_data["game_name"];

			$data["server_id"]	   = 0;

			$data["server_name"]   = "";

			$data["promote_id"]    = $user['promote_id'];

			$data["promote_account"] = $user['promote_account'];

			$data["pay_order_number"]= $data['order_no'];

			$data["title"] = $_POST['props_name'];

			// $data["pay_amount"]   =  $_POST['amount']/100;

			$data["pay_amount"]   =  0.01;

			$data["pay_way"] = 2;

			$data['extend']  = $_POST['trade_no'];

			$pay = new PayApi();

			$pay->add_spend($data);

            //触发微信返回code码

            if(session('for_third')==C(PC_SET_DOMAIM)){

            	$code_url="http://".session('for_third').'/mobile.php/Subscriber/get_wx_code/';

            }else{

            	$code_url="http://".$_SERVER['HTTP_HOST'].'/mobile.php/Subscriber/get_wx_code/';

            }

            // $state=$data["pay_amount"].','.$data['order_no'].','.simple_encode(get_uname()).','.$data["game_id"].','.simple_encode($_SERVER['HTTP_HOST']);//指针0金额，1订单，2用户名，3游戏id,4发起支付的域名

            $state=0.01.','.$data['order_no'].','.simple_encode(get_uname()).','.$data["game_id"].','.simple_encode($_SERVER['HTTP_HOST']);//指针0金额，1订单，2用户名，3游戏id,4发起支付的域名

            // wite_text(json_encode($state),dirname(__FILE__).'/aa.txt');

            $url = $jsApi->createOauthUrlForCode($code_url,$state);
           
            return $url;
            //$this->ajaxReturn(array('url'=>$url,'status'=>1));

        }else

        {

        	//这个接口 由于微信后台支付授权地址有限，不使用

            //获取code码，以获取openid

            $code = $_GET['code'];

            $jsApi->setCode($code);

            $openid = $jsApi->getOpenId();

			$weixn=new Weixin();

			$state1=explode(',',$_REQUEST['state']);

			$amount=$state1[0];

			$out_trade_no=$state1[1];

			$game_id=$state1[2];

			$spendinfo=M('Spend','tab_')->where(array('pay_order_number'=>$out_trade_no))->find();

			if($spendinfo['pay_amount']!=$amount||$spendinfo['game_id']!=$game_id){

				$this->error('参数错误');exit;

			}

			$is_pay=$weixn->weixin_jsapi("游戏充值",$out_trade_no,$amount,$jsApi,$openid);

			$this->assign('jsApiParameters',$is_pay);

			$this->assign('game_url',U('Game/open_game',array('game_id'=>$game_id)));

			$this->display();

        }
    }

}

