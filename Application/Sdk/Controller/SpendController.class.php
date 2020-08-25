<?php

namespace Sdk\Controller;

use Think\Controller;

use Org\JtpaySDK\Jtpay;

class SpendController  extends Controller {



    /**

     * 转发支付url

     */

    public function get_pay_url($user_id,$game_id){

        $file=file_get_contents("./Application/Sdk/OrderNo/".$user_id."-".$game_id.".txt");

        $info=json_decode(think_decrypt($file),true);

        redirect($info['pay_url']);

    }







    public function jft_pay($user_id,$game_id,$type)

    {

        $file=file_get_contents("./Application/Sdk/OrderNo/".$user_id."-".$game_id.".txt");

        $request=json_decode(think_decrypt($file),true);

        

        $jtpay=new Jtpay();

        if($request['sdk_version']==1){//1 安卓 2苹果

            $p25_terminal=3;

            $p26_iswappay=3;

        }else{

            $p25_terminal=2;

            $p26_iswappay=3;

        }



        if($type=="3"){// 3支付宝  4微信

             $url=$jtpay->jt_pay($request['pay_order_number'],$request['price'],$request['user_id'],get_client_ip(),"sdk",4,'http://' . $_SERVER ['HTTP_HOST'] . "/sdk.php/Spend/pay_success",$p26_iswappay,$p25_terminal);//安卓

        }else{

             $url=$jtpay->jt_pay($request['pay_order_number'],$request['price'],$request['user_id'],get_client_ip(),"sdk",3,'http://' . $_SERVER ['HTTP_HOST'] . "/sdk.php/Spend/pay_success",3,2);//安卓

        }   

        redirect($url);



        

    }

    /**

     * 支付页面

     * @return [type] [description]

     */

    public function  pay_way(){

        $this->display();

    }



    public function pay_success(){     

        $orderno=$_GET['orderno']==""?$_GET['out_trade_no']:$_GET['orderno'];
        if(!empty($_GET['jinzhue'])){
            $orderno = $_GET['jinzhue'];
        }
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

        $this->display();

    }







    public function pay_success2(){     

        $orderno=$_GET['orderno']==""?$_GET['out_trade_no']:$_GET['orderno'];

        $pay_where = substr($orderno, 0, 2);

        $Scheme=file_get_contents("./Application/Sdk/Scheme/".$_GET['game_id'].".txt");

        $map['pay_order_number'] = $orderno;

        switch ($pay_where) {

            case 'SP':

                $result = M('Spend','tab_')->field("pay_status")->where($map)->find();

                break;

            case 'PF':

                $result = M('deposit','tab_')->field('pay_status')->where($map)->find();

                break;

        }
        if($_GET['type'] == 1){
            $this->assign('type',$_GET['type']);
        }
        $this->assign('paystatus',$result['pay_status']);

        $this->assign('Scheme',$Scheme);

        $this->display();

    }





    public function pay_error(){

    	$this->display();

    }

}

