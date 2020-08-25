<?php
namespace App\Controller;

use Common\Model\GameModel;
use Common\Model\DocumentModel;
use Common\Model\GiftbagModel;
use Com\WechatAuth;
class ActionController extends BaseController{

    /**
     * 收藏与取消收藏 (单体)
     * @author [yyh] <[<email address>]>
     */
    public function change_collect_status($token,$game_id,$status){
        $this->auth($token,"");
        $gdata = M('Game','tab_')->field('id')->find($game_id);
        if(empty($gdata)){
            $this->set_message(1041,'没有该游戏',"");
        }
        $map['user_id'] = USER_ID;
        $map['game_id'] = $game_id;
        $map['status'] = ['in','-1,1'];
        $data = M('user_behavior as ub','tab_')
            ->where($map)
            ->find();
        if($data){
            $save['status'] = $status==1?1:-1;
            $save['id'] = $data['id'];
            $save['update_time'] = time();
            $res = M('user_behavior','tab_')->save($save);
            if($res){
                $this->set_message(200,'success',"");
            }else{
                $this->set_message(1106,'收藏失败',"");
            }
        }else{
            $save['user_id'] = USER_ID;
            $save['game_id'] = $game_id;
            $save['status'] = $status==1?1:-1;
            $save['update_time'] = time();
            $save['create_time'] = $save['update_time'];
            $res = M('user_behavior','tab_')->add($save);
            if($res){
                $this->set_message(200,'success',"");
            }else{
                $this->set_message(1107,'取消收藏失败',"");
            }
        }
    }


    /**
     * 取消收藏 （群体）
     * @author [yyh] <[<email address>]>
     */
    public function cancel_collect($token,$game_id){
        $this->auth($token,"");

        $game_id = explode(',',$game_id);

        $where['user_id'] = USER_ID;
        $where['status'] = 1;
        $where['game_id'] = array('in',$game_id);
        $save['update_time'] = time();
        $save['status'] = -1;

        $res = M('user_behavior','tab_')
            ->where($where)
            ->save($save);
        if($res!==false){
            $this->set_message(200,'success',"");
        }else{
            $this->set_message(1107,'取消收藏失败',"");
        }
    }


    /**
     * 搜索
     */
    public function search($keyword,$sdk_version=1,$token=""){
        $sdk_version1 = $sdk_version;
        $sdk_version = array($sdk_version,3);
        if ($token) {
            $this->auth($token);
            $user_id = USER_ID;
        }else{
            $user_id = 0;
        }
        $gamemod = new GameModel();
        $gamemap['g.game_name'] = array('like','%'.$keyword.'%');
        $gamemap['g.sdk_version'] = array('in',$sdk_version);
        $game=$gamemod->searchgame($gamemap,$user_id);//搜索游戏
        $samllgame = $this->getSmalllist($keyword);//搜索小游戏
        $samllgame = $samllgame ? $samllgame : [];
        if(!empty($game)) {
            $docmodel = new DocumentModel();
            $docmap['d.belong_game'] = array('in', array_column($game, 'id'));
            $article = $docmodel->searchArticle(array('in', array('app_huodong', 'app_gg')), $docmap, 100, $model = 'app');
            $article = $article ? $article : [];

            $giftmodel = new GiftbagModel();
            $giftgame = array('in', implode(',', array_column($game, 'relation_game_id')));
            $data = $giftmodel->getGiftLists($sdk_version1,$giftgame, 1, 100);

            
            foreach ($data as $key => $value) {
                $map['game_id'] = $data[$key]['game_id'];
                $map['gift_id'] = $data[$key]['gift_id'];
                $map['user_id'] = $user_id;
                $isget = M('gift_record', 'tab_')->where($map)->getField('id');
                if (!empty($isget)) {
                    $data[$key]['received'] = 1;
                } else {
                    $data[$key]['received'] = 0;
                }
                $data[$key]['game_name'] = str_replace('(安卓版)','',$value['game_name']);
                $data[$key]['game_name'] = str_replace('(苹果版)','',$data[$key]['game_name']);
            }
            $gift = $data?$data:[];
            $return['game'] = $game;
            $return['samllgame'] = $samllgame;
            $return['article'] = $article;
            $return['gift'] = array_values($gift);
            $this->set_message(200,'success',$return);
        }else{
            $data['game'] = [];
            $data['article'] = [];
            $data['gift'] = [];
            $data['samllgame'] = $samllgame;
            $this->set_message(200,'success',$data);
        }
    }
    public function search_hot($sdk_version,$rec_status,$limit=9,$p=1){
        switch ($sdk_version) {
            case 1:
            case 2:
                $map['g.sdk_version'] = ['in',$sdk_version.',3'];
                break;
            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        $map['recommend_status'] = array('like','%'.$rec_status.'%');
        $model = new GameModel();
        $data = $model->getGameLists($map,$order="g.sort desc,g.id desc",$p,$limit);
        if (empty($data)){
            $data = [];
        }
        $this->set_message(200,'success',$data);
    }
    /*
     * 小程序结果列表
     */
    public function getSmalllist($keyword){
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
        $map['status'] = 1;
        $map['game_name'] = array('like','%'.$keyword.'%');
        $order = "sort desc,id desc";
        $data = $model
            ->field("id,game_name,scan_num,icon,qrcodeurl")
            ->where($map)
            ->order($order)
            ->group("id")
            ->select();
        foreach ($data as $key=>$vo){
            $image = get_cover($vo['icon'],'path');
            if(strpos($image,'http') === false){
                $data[$key]['icon'] = "http://".$_SERVER['HTTP_HOST'].$image;
            }else{
                $data[$key]['icon'] = $image;
            }
        }
        return $data;
    }

    

    /**
     * 签到、文章分享
     * @ram int $group 状态分组
     * @param int $type  状态文字 1签到  2文章
     * @param int $article_id  type=2时  文章id
     * @author yyh
     */
    public function share($type=1,$article_id=''){
        switch ($type) {
            case 1:
                $data['title'] = "每日签到，不限量积分等你来领";
                $data['cover'] = "http://".$_SERVER['HTTP_HOST']."/Public/Mobile/images/invitate_btn_logo.png";
                $data['introduction'] = "每日签到，不限量积分等你来领！     积分当钱花，更有机会兑换神秘礼品！";
                $data['url'] = "http://".$_SERVER['HTTP_HOST']."/mobile.php/PointShop/mall_sign";
                break;
            case 2:
                if(!$article_id){
                    $this->set_message(1090,'参数错误',array());
                }
                $model = new DocumentModel();
                $article_data = $model->articleDetail($article_id);
                if($article_data===false){
                    $this->set_message(1046,'暂无文章',array());
                }
                $data['title'] = $article_data['title'];
                if($article_data['cover_id']){
                    $article_data['cover_id'] = get_cover($article_data['cover_id'],'path');
                    if(strpos($article_data['cover_id'],'http') === false){
                        $article_data['cover_id'] = 'http://'.$_SERVER['HTTP_HOST'].$article_data['cover_id'];
                    }
                }else{
                    $article_data['cover_id'] = 'http://'.$_SERVER['HTTP_HOST'].'/Public/static/images/pic_zhanwei.png';
                }
                $data['cover'] = $article_data['cover_id'];
                $data['introduction'] = $article_data['description']==''?'号外！号外！大新闻':$article_data['description'];
                $data['url'] = "http://".$_SERVER['HTTP_HOST']."/mobile.php/Article/detail/id/".$article_id;
                break;
        }

        $this->set_message(200,'success',$data);
    }


    /**
     * 删除我的足迹
     * @author [yyh] <[<email address>]>
     */
    public function delete_foot($token,$ids){
        $this->auth($token,'');
        $ids = explode(',',$ids);
        $ids = array_unique($ids);
        $ids = implode(',',$ids);
        $model = new GameModel();
        $user = USER_ID;
        $map['id'] = array('in',$ids);
        $data = $model->optionBehavior($user,2,$map);
        if($data!==false){
            $this->set_message(200,'success','');
        }else{
            $this->set_message(1051,'删除失败','');
        }
    }
}
