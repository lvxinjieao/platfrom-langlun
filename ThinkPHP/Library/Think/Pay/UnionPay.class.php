<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: huajie <banhuajie@163.com>
// +----------------------------------------------------------------------

namespace Think\Pay;

/**
 * 注意事项
 * 生产参数如下
      消息来源(msgSrc)：WWW.YZHXGXX.COM
      来源编号（msgSrcId）：6975
      通讯密钥:AnCbxw3Rh8reC8GWk85yCcHyTpRKzFzQjPzBGKMNp6AnReei
      机构商户号(instMid)：(见接口文档)
      烦请按附件中操作手册 做好代理设置。
      另外：1.提醒商户技术：将测试参数替换成生产参数（务必在投产时将测试参数替换成对应生产参数！）。需要替换的参数有：mid与tid（需求方录  
       入给商户）， msgSrc、来源编号（msgSrcId）与  通讯秘钥（见本邮件），url（见接口文档中生产环境接口地址） 。
          2.提醒商户检查下必传字段（mid  tid  msgType  msgSrc instMid）
          3：生产商户号限定为限制为15位数字< 机构码+地区码+MCC+随机数字 >，终端号限定为8位数字或字母。
 */
class UnionPay {
    //请求地址
    public $requestUrl = 'https://qr-test2.chinaums.com/netpay-route-server/api/';
    //秘钥
    public $key = '';
    public $msgSrcId = '6975';
    //支付参数,如有需要自行添加
    public $params = [
        'mid' => '',    //商户号
        'tid' => '',    //终端号
        'instMid' => 'QRPAYDEFAULT',    //业务类型
        'msgId' => '',    //消息id
        'msgSrc' => 'WWW.YZHXGXX.COM',    //消息来源
        'msgType' => '',    //消息类型
        'requestTimestamp' => '',    //报文请求时间:yyyy-MM-dd HH:mm:ss
        'billNo' => '',    //账单号
        'billDate' => '',    //账单日期：yyyy-MM-dd
        'billDesc' => 'dcmuyi',    //账单描述
        'totalAmount' => '1',    //支付总金额
        'expireTime' => '',    //过期时间
        'notifyUrl' => '',    //支付结果通知地址
        'returnUrl' => '',    //网页跳转地址
        'qrCodeId' => '',    //二维码ID
        'systemId' => '',    //系统ID
        'secureTransaction' => '',    //担保交易标识
        'walletOption' => '',    //钱包选项
        'name' => '',    //实名认证姓名
        'mobile' => '',    //实名认证手机号
        'certType' => '',    //实名认证证件类型
        'certNo' => '',    //实名认证证件号
        'fixBuyer' => '',    //是否需要实名认证
        'limitCreditCard' => '',    //是否需要限制信用卡支付
        'signType' => 'md5',    //签名方式
        'sign' => '',    //签名
    ];

    /**
     * 请求
     * @return mixed
     * @throws Exception
     */
    public function request()
    {
        $params = $this->params;
        $sign = $this->generateSign($params, $params['signType']);
        $this->setParams('sign', $sign);

        //模拟请求
        $resp = $this->curl($this->requestUrl, $this->params);

        //准备验签
        $respList = json_decode($resp, true);
        if (!$this->verify($respList)) {
            return ['code'=>0,'msg'=>'返回签名验证失败'];
        }

        if ($respList['errCode']  != 'SUCCESS') {
            return ['code'=>0,'msg'=>$respList['errMsg']];
        }

        return $respList;
    }

    /**
     * 模拟POST请求
     * @param $url
     * @param null $postFields
     * @return mixed
     * @throws Exception
     */
    protected function curl($url, $postFields = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        //有post参数-设置
        if (is_array($postFields) && 0 < count($postFields)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
        }

        $header[] = "Content-type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $reponse = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch), 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode) {
                die($reponse.$httpStatusCode);
            }
        }
        curl_close($ch);

        return $reponse;
    }

    /**
     * 验证签名是否正确
     * @param $data
     * @return bool
     */
    function verify($data) {
        //返回参数生成sign
        $signType = empty($data['signType']) ? 'md5' : $data['signType'];
        $sign = $this->generateSign($data, $signType);

        //返回的sign
        $returnSign = $data['sign'];

        if ($returnSign != $sign) {
            return false;
        }

        return true;
    }

    /**
     * 设置参数
     * @param $key
     * @param $valve
     */
    public function setParams($key, $valve) {
        $this->params[$key] = $valve;
    }

    /**
     * 根绝类型生成sign
     * @param $params
     * @param string $signType
     * @return string
     */
    public function generateSign($params, $signType = 'md5') {
        return $this->sign($this->getSignContent($params), $signType);
    }

    /**
     * 生成signString
     * @param $params
     * @return string
     */
    public function getSignContent($params) {
        //sign不参与计算
        $params['sign'] = '';

        //去除空值
        $params = array_filter($params);

        //排序
        ksort($params);

        $paramsToBeSigned = [];
        foreach ($params as $k=>$v) {
            $paramsToBeSigned[] = $k.'='.$v;
        }
        unset ($k, $v);

        //签名字符串
        $stringToBeSigned = implode('&', $paramsToBeSigned);
        $stringToBeSigned .= $this->key;

        return $stringToBeSigned;
    }

    /**
     * 生成签名
     * @param $data
     * @param string $signType
     * @return string
     */
    protected function sign($data, $signType = "md5") {
        $sign = hash($signType, $data);

        return strtoupper($sign);
    }
}