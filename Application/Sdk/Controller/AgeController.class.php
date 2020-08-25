<?php
namespace Sdk\Controller;
use Think\Controller;
use Common\Api\GameApi;
use Org\WeixinSDK\Weixin;
use Org\HeepaySDK\Heepay;
use Org\UcenterSDK\Ucservice;

class AgeController extends BaseController{

    public function age($cardno,$name){

        $host = "http://idcard.market.alicloudapi.com";
        $path = "/lianzhuo/idcard";
        $method = "GET";
        $appcode = C('age')['appcode'];
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "cardno=".$cardno."&name=".$name;
        $bodys = "";
        $url = $host . $path . "?" . $querys;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
    }
}
