<?php
namespace App\Controller;
use Think\Controller;
use Com\WechatAuth;

class SmallGameController extends BaseController {
    /**
     * []
     * @param int $p
     * @author 郭家屯[syt]
     */
	public function getLists($p=1,$limit = 10) {
        $model = M("small_game","tab_");
        //临时二维码过期更新判断
        $first = $model->field("qrcode_time")->order("id asc")->find();
        if(strtotime("-28 day") > $first['qrcode_time']){
            //获取access_token去生成临时二维码存入数据库
            $result = auto_get_access_token('access_token_validity.txt');
            $appid = C('wechat.appid');
            $appsecret = C('wechat.appsecret');
            //获取access_token
            if (!$result['is_validity']) {
                $auth = new WechatAuth($appid, $appsecret);
                $token = $auth->getAccessToken();
                $token['expires_in_validity'] = time() + $token['expires_in'];
                wite_text(json_encode($token), 'access_token_validity.txt');
                $result = auto_get_access_token('access_token_validity.txt');
            }
            $auth = new WechatAuth($appid, $appsecret,$result['access_token']);
            $data = $model->field("id")->order("id asc")->select();
            foreach ($data as $key=>$vo){
                $res  = $auth->qrcodeCreate("SM_".$vo['id'],2505600);
                $qrcodeurl = $auth->showqrcode($res['ticket']);
                $model->where(array('id'=>$vo['id']))->save(array('qrcodeurl'=>$qrcodeurl,'qrcode_time'=>time()));
            }
        }
        $page = intval($p);
        $page = $page ? $page : 1;
		$map['status'] = 1;
		$order = "sort desc,id desc";
		$data = $model
            ->field("id,game_name,scan_num,icon,qrcodeurl")
            ->where($map)
            ->page($page, $limit)
            ->order($order)
            ->group("id")
            ->select();
        if (empty($data)){
            $data = [];
        }else{
            foreach ($data as $key=>$vo){
                $image = get_cover($vo['icon'],'path');
                if(strpos($image,'http') === false){
                    $data[$key]['icon'] = "http://".$_SERVER['HTTP_HOST'].$image;
                }else{
                    $data[$key]['icon'] = $image;
                }
            }
        }
        $this->set_message(200,'success',$data);
	}
}

