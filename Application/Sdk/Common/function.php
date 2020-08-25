<?php
// +----------------------------------------------------------------------
// | 徐州梦创信息科技有限公司—专业的游戏运营，推广解决方案.
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.vlcms.com  All rights reserved.
// +----------------------------------------------------------------------
// | Author: kefu@vlcms.com QQ：97471547
// +----------------------------------------------------------------------




/*
*获取游戏设置信息
*/
function get_game_set_info($game_id = 0){
	$game = M('GameSet','tab_');
	$map['game_id'] = $game_id;
	$data = $game->where($map)->find();
	return $data;
}

/**  
 * 对数据进行编码转换  
 * @param array/string $data       数组  
 * @param string $output    转换后的编码  
 */  
function array_iconv($data,  $output = 'utf-8') {  
    $encode_arr = array('UTF-8','ASCII','GBK','GB2312','BIG5','JIS','eucjp-win','sjis-win','EUC-JP');  
    $encoded = mb_detect_encoding($data, $encode_arr);  
  
    if (!is_array($data)) {  
        return mb_convert_encoding($data, $output, $encoded);  
    }  
    else {  
        foreach ($data as $key=>$val) {  
            $key = array_iconv($key, $output);  
            if(is_array($val)) {  
                $data[$key] = array_iconv($val, $output);  
            } else {  
            $data[$key] = mb_convert_encoding($data, $output, $encoded);  
            }  
        }  
    return $data;  
    }  
}

/**
 * 获取游戏appstor上线状态
 * @param $game_id 游戏id
 * @return mixed appstatus 上线状态
 * @author zhaochao
 */
function get_game_appstatus($game_id){
    $map['id']=$game_id;
    $game=M('game','tab_')->where($map)->find();
    if($game['sdk_version']==2&&$game['appstatus']==1){
        return true;
    }elseif($game['sdk_version']==2&&$game['appstatus']==0){
        return false;
    }elseif($game['sdk_version']==1){
        return true;
    }

}
/**
 * 邮件发送函数
 */
function sendMail($to,$rand) {
    Vendor('PHPMailer.PHPMailerAutoload');
    $mail = new \PHPMailer(); //实例化
    $mail->IsSMTP(); // 启用SMTP
    $mail->Host=C('email_set.smtp'); //smtp服务器的名称（这里以126邮箱为例：smtp.126.com）
    $mail->SMTPAuth = TRUE;//C('MAIL_SMTPAUTH'); //启用smtp认证
    $mail->SMTPSecure = 'ssl'; //设置使用ssl加密方式登录鉴权
    $mail->Port = C('email_set.smtp_port'); //设置ssl连接smtp服务器的远程服务器端口号
    $mail->CharSet = 'UTF-8'; 
    $mail->Username = C('email_set.smtp_account'); //你的邮箱名
    $mail->Password = C('email_set.smtp_password') ; //邮箱密码
    $mail->From = C('email_set.smtp_account'); //发件人地址（也就是你的邮箱地址）
    $mail->FromName = C('email_set.smtp_name'); //发件人姓名
    $mail->AddAddress($to,"尊敬的客户");
    $mail->WordWrap = 50; //设置每行字符长度
    $mail->IsHTML(TRUE); // 是否HTML格式邮件
    $mail->CharSet='utf-8'; //设置邮件编码
    $mail->Subject =C('email_set.title'); //邮件主题
    $content=M("tool",'tab_')->where(array('name'=>'email_set'))->getField('template');
    $reg="/#code#/";
    $content=preg_replace($reg,$rand,$content);
    $mail->Body = $content; //邮件内容
    $c = strip_tags($content);
    $mail->AltBody = $c; //邮件正文不支持HTML的备用显示
    return($mail->Send());
}

/**
 * 获取微信app登录参数
 * @return [type] [description]
 */
function get_game_param($game_id,$type=1){
    $map['game_id']=0;
    $map['type'] = $type;
    $find=M('param','tab_')->where($map)->find();
    if(null==$find){
        $map['game_id']=$game_id;
        $find=M('param','tab_')->where($map)->find();
    }
    return $find;
}

/*
获取sdk微信参数
@author  cb
 */
function get_game_wxlogin_param($game_id){

    $map['game_id']=['in' ,[$game_id,0]];
    $map['type']=2;
    $map['status']=1;
    $res=M('param','tab_')->field('wx_appid,appsecret')->where($map)->find();
    if(empty($res)){
        return '';
    }else{
        return $res;
    }
}

/**
 * [获取游戏的app状态]
 * @param $game_id
 * @return bool
 */
function get_game_appstatus2($game_id){
    $map['id']=$game_id;
    $game=M('game','tab_')->field('appstatus')->where($map)->find();
    if($game['appstatus']==1){
        return true;
    }else{
        return false;
    }

}
