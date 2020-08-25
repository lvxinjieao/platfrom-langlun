<?php
namespace Org\GoldPig;

use Think\Exception;

class GoldPig
{
    /*
    金猪支付
    提交参数：UserName=玩家帐号&Price=订单金额&shouji=玩家腾讯QQ号&PayID=通道代码&userid=金猪商户ID号&wooolID=金猪平台分区ID号&jinzhua=客户预留1&jinzhub=客户预留2&jinzhuc=客户预留3
     */
     public function GoldPig($UserName,$Price,$PayID,$pay_order_number="",$ka_type='',$mobile="",$password=''){
        $urlparams['UserName'] = $UserName;
        $urlparams['Price'] = $Price;
        $urlparams['shouji'] = C('PC_SET_SERVER_QQ');
        $urlparams['PayID'] =  $PayID;
        $urlparams['userid'] = C('goldpig.partner');
        $urlparams['wooolID'] = C('goldpig.appid');
        $urlparams['ka_type'] = $ka_type;
        $urlparams['mobile'] = $mobile;
        $urlparams['password'] = $password;
        $urlparams['jinzhua'] = $pay_order_number;
        $urlparams['jinzhub'] = '';
        $urlparams['jinzhuc'] = '平台币充值';
        $urlparams['jinzhue'] = $pay_order_number;
        $urlparams['uip'] = $this->getClientIP();
        $url=$this->goldpig_post($urlparams);
        return $url;

    }

    /**
     * 金猪支付post请求
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function goldpig_post($param){
        $url='http://api.357p.com/';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);//设置等待时间
        $url_str = curl_exec($ch);
        curl_close($ch);
        if(strpos($url_str,'http:')){
            $url_str=str_replace('&amp;','&',$url_str);
            $url_str=strstr($url_str,"http");
            $url_arr=explode('"',$url_str);
            $url=$url_arr[0];
            return ['msg'=>$url,'status'=>1];
        }else{
            return ['msg'=>'','status'=>0];
            
        } 
    }

    public function getClientIP(){  
        global $ip;  
        if(getenv("HTTP_CLIENT_IP")){
            $ip = getenv("HTTP_CLIENT_IP");  
        }else if(getenv("HTTP_X_FORWARDED_FOR")){
            $ip = getenv("HTTP_X_FORWARDED_FOR");  
        }else if(getenv("REMOTE_ADDR")){
            $ip = getenv("REMOTE_ADDR");  
        }else{
            $ip = "NULL";  
        }
        return $ip;  
    }



}



