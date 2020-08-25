<?php
namespace App\Controller;
use Common\Model\GameModel;
use Common\Model\GiftbagModel;
use Common\Model\UserPlayModel;
use Common\Model\DocumentModel;
use Common\Model\ServerModel;
use Think\Controller;
use Common\Api\GameApi;
use Org\UcenterSDK\Ucservice;
class GameController extends BaseController{
    /**
     * [gameRecList 首页游戏列表]
     * @param  string  $token       [description]
     * @param  string  $sdk_version [description]
     * @param  [type]  $rec_status  [description]
     * @param  integer $p           [description]
     * @param  integer $limit       [description]
     * @param  string  $order       [description]
     * @return [type]               [description]
     * @author [yyh] <[<email address>]>
     */
    public function gameRecList($token="",$sdk_version=1,$rec_status,$p=1,$limit=10,$order="g.sort desc,g.id desc"){
        if($token){
            $this->auth($token);
            $user_id = USER_ID;
        }else{
            $user_id = 0;
        }
        switch ($sdk_version) {
            case 1:
            case 2:
            case 3:
                $map['g.sdk_version'] = $sdk_version;
                break;
            
            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        $map['recommend_status'] = array('like','%'.$rec_status.'%');
        $model = new GameModel();
        $data = $model->getGameLists($map,$order="g.sort desc,g.id desc",$p,$limit,$user_id);
        if (empty($data)){
            $data = [];
        }
        $this->set_message(200,'success',$data);
    }
    /**
     * [gameGroup 游戏分类]
     * @return [type] [description]
     * @author [yyh] <[<email address>]>
     */
    public function gameGroup($sdk_version){
        switch ($sdk_version) {
            case 1:
            case 2:
            case 3:
                $map['g.sdk_version'] = ['eq',$sdk_version];
                break;
            
            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        $model = new GameModel();
        $data = $model->getGroupLists($map);
        if (empty($data)){
            $data = [];
        }
        $this->set_message(200,'success',$data);
    }
    /**
     * [gameGroupList 分类游戏列表]
     * @param  string  $type_id [description]
     * @param  integer $p       [description]
     * @param  integer $limit   [description]
     * @return [type]           [description]
     * @author [yyh] <[<email address>]>
     */
    public function gameGroupList($sdk_version=1,$type_id='',$p=1,$limit=10){
        switch ($sdk_version) {
            case 1:
            case 2:
            case 3:
                $map['g.sdk_version'] = ['eq',$sdk_version];
                break;
            
            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        if (empty($type_id)){
            $this->set_message(200,'success',[]);
        }
        if($type_id!=-1){
            $map['game_type_id'] = $type_id;
        }
        $model = new GameModel();
        $data = $model->getGameLists($map,'g.play_count desc,g.sort desc,g.id desc',$p,$limit);
        if (empty($data)){
            $data = [];
        }

        $this->set_message(200,'success',$data);
    }
    /**
     * 排行榜
     * @param $recommend    推荐状态    1 推荐 2 热门 3 最新 4 下载量/在玩榜
     * @param $version      游戏版本 1 android 2 ios 3H5
     * author: yyh
     */
    public function get_game_rank_lists($p=1,$recommend,$sdk_version,$token=''){
        switch ($sdk_version) {
            case 1:
            case 2:
            case 3:
                $map['g.sdk_version'] = $sdk_version;
                break;
            
            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        if($token){
            $this->auth($token);
            $user_id = USER_ID;
        }else{
            $user_id = 0;
        }
        if(4 == $recommend){
            if($sdk_version==3)
                $order = "play_count desc,id desc";
            else
                $order = "dow_num desc,id desc";
        }else{
            $order = "sort desc,id desc";
            $map['recommend_status'] = array('like','%'.$recommend.'%');
        }
        $model = new GameModel();
        $data = $model->getGameLists($map,$order,$p,10,$user_id);
        if(empty($data)){
            $this->set_message(1033,"暂无数据");
        }else{
            $this->set_message(200,"成功",$data);
        }
    }
    /**
     * 获取绑币充值折扣
     * @param $game_id
     * author: yyh
     */
    public function get_game_recharge_discount($game_id){
        $discount = GameModel::getBindRechargeDiscount($game_id);
        $this->set_message(200,"成功",$discount);
    }
    /**
     * [userLatelyPlay 用户最近在玩]
     * @param  [type] $token [description]
     * @return [type]        [description]
     * @author [yyh] <[<email address>]>
     */
    public function userLatelyPlay($token,$sdk_version=1){
        $this->auth($token);
        $model = new UserPlayModel();
        $map['user_id'] = USER_ID;
        $map['g.sdk_version'] = array('in',array($sdk_version,3));
        $data = $model->getUserPlay($map,"u.play_time desc",1,10);
        if (empty($data)){
            $data = [];
        }
        $this->set_message(200,'success',$data);
    }
    /**
     * [server 开服列表]
     * @param  string  $token       [description]
     * @param  string  $sdk_version [description]
     * @param  integer $type        [description]
     * @param  integer $p           [description]
     * @param  integer $row         [description]
     * @return [type]               [description]
     * @author [yyh] <[<email address>]>
     */
    public function server($token='',$sdk_version='',$type=0,$p=1,$row=10){
        $model = new ServerModel();
        if(!empty($token)){
            $this->auth($token);
            $user_id = USER_ID;
        }else{
            $user_id = 0;
        }
        switch ($sdk_version) {
            case 1:
            case 2:
                $sdk_version = $sdk_version;
                break;
            
            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        $data = $model->server($sdk_version,$type,$p,$row,$user_id);
        if (empty($data)){
            $data = [];
        }
        if($data['code']==-1){
            $this->set_message(1090,$data['msg']);
        }else{
            $this->set_message(200,'success',$data);
        }
        
    }
    /**
     * [setServerNotice 设置开服通知]
     * @param [type] $token     [description]
     * @param [type] $server_id [description]
     * @param [type] $type      [description]
     * @author [yyh] <[<email address>]>
     */
    public function setServerNotice($token,$server_id,$type){
        $this->auth($token,'');
        $model = new ServerModel();
        if(empty($server_id)||empty($type)){$this->set_message(1089, "必要参数不能为空",[],1);}
        $result = $model->set_server_notice(USER_ID,$server_id,$type);
        if($result!==false){
            $this->set_message(200,'操作成功',[],1);
        }else{
            $this->set_message(1043,'操作失败',[],1);
        }
    }
    /**
     * [gameDetail 游戏详情 不包含礼包、活动、都在玩]
     * @param  string $game_id [description]
     * @param  string $token   [description]
     * @return [type]          [description]
     * @author [yyh] <[<email address>]>
     */
    public function gameDetail($game_id='',$token=''){
        empty($game_id)&&$this->set_message(-1,'缺少game_id');
        if(!empty($token)){
            $this->auth($token);
            $user_id = USER_ID;
        }else{
            $user_id = 0;
        }
        $model = new GameModel();
        $data = $model->gameDetail($game_id,$user_id);
        if($data===false){
            $this->set_message(1057,'游戏不存在');
        }else{
            $data['introduction'] = str_replace('~~',"\n",$data['introduction']);
            $this->set_message(200,'success',$data);
        }
    }
    /**
     * [gameGift 游戏详情礼包]
     * @param  [type] $game_id [description]
     * @param  string $token   [description]
     * @return [type]          [description]
     * @author [yyh] <[<email address>]>
     */
    public function gameGift($sdk_version=1,$game_id,$token=''){
        $model = new GiftbagModel();
        $data = $model->getGiftLists($sdk_version,$game_id,1,50);
        if($data===false){
            $this->set_message(1015,'暂无礼包');
        }else{
            if($token){
                $this->auth($token);
                foreach ($data as $key => $value) {
                    $map = array();
                    $map['game_id'] = $value['game_id'];
                    $map['gift_id'] = $value['gift_id'];
                    $map['user_id'] = USER_ID;
                    $isget = M('gift_record','tab_')->where($map)->getField('id');
                    if(!empty($isget)){
                        $data[$key]['received'] = 1;
                    }else{
                        $data[$key]['received'] = 0;
                    }
                }
            }
            $data = array_values($data);
            $this->set_message(200,'success',$data);
        }
    }
    /**
     * [gameActiveDoc 游戏文章]
     * @param  [type] $game_id [description]
     * @return [type]          [description]
     * @author [yyh] <[<email address>]>
     */
    public function gameActiveDoc($game_id,$is_gl=0){
        $model = new DocumentModel();
        if($is_gl){
            $data = $model->getArticleListsByCategory($game_id,array('app_glue'),1,30,2);
        }else{
            $data = $model->getArticleListsByCategory($game_id,array('app_huodong','app_gg'),1,30,2);
        }
        if($data===false){
            $this->set_message(1033,'暂无数据');
        }else{
            $this->set_message(200,'success',$data);
        }
    }
    /**
     * 猜你喜欢和大家都在玩（随机取出四条数据）
     * @param  无
     * @return 四条游戏数据
     * @author lyf
     */
    public function gsULike($sdk_version=1,$getnum=4){
        $map['sdk_version'] = array('in',array($sdk_version,3));
        $game_list=get_game_list($map);
        $count = count($game_list);
        if($count>=$getnum){
            $getnum = $getnum;
        }else{
            $getnum = $count;
        }

        $game_keys=array_rand($game_list,$getnum);
        foreach ($game_keys as $val) {
            $game_like['game_id']=$game_list[$val]['id'];
            $game_like['icon']=icon_url($game_list[$val]['icon']);
            $game_like['game_name']=$game_list[$val]['relation_game_name'];
            $game_like['game_type_id']=$game_list[$val]['game_type_id'];
            $game_like['sdk_version']=$game_list[$val]['sdk_version'];
            $game_like['xia_status']=check_game_sorue($game_list[$val]['id']);
            $res[]=$game_like;
        }
        $this->set_message(1,'success',$res);
    }

    /**
     * [gameGiftLists 游戏礼包列表]
     * @param  [type]  $sdk_version [description]
     * @param  boolean $rec_status  [description]
     * @param  string  $token       [description]
     * @param  integer $p           [description]
     * @return [type]               [description]
     * @author [yyh] <[<email address>]>
     */
    public function gameGiftLists($sdk_version,$rec_status=false,$token='',$p=1){
        switch ($sdk_version) {
            case 1:
            case 2:
            case 3:
                $sdk_version = $sdk_version;
                // $sdk_version = '%'.$sdk_version.'%';
                break;
            
            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        $model = new GiftbagModel();
        $data = $model->getGameGiftLists($sdk_version,$rec_status,0,[],$p);
        if($data===false){
            $this->set_message(1015,'暂无礼包');
        }else{
            if($token){
                $this->auth($token);
                foreach ($data as $key => $value) {
                    foreach ($value['gblist'] as $k => $v) {
                        $map = array();
                        $map['game_id'] = $v['game_id'];
                        $map['gift_id'] = $v['gift_id'];
                        $map['user_id'] = USER_ID;
                        $isget = M('gift_record','tab_')->where($map)->getField('id');
                        if(!empty($isget)){
                            $data[$key]['gblist'][$k]['received'] = 1;
                        }else{
                            $data[$key]['gblist'][$k]['received'] = 0;
                        }
                    }
                }
            }
            $this->set_message(200,'success',array_values($data));
        }
    }
    /**
     * [giftDetail 礼包详情]
     * @param  [type] $gift_id [description]
     * @param  string $token   [description]
     * @return [type]          [description]
     * @author [yyh] <[<email address>]>
     */
    public function giftDetail($gift_id,$token=''){
        $model = new GiftbagModel();
        $data = $model->getDetail($gift_id);
        if($data===false){
            $this->set_message(1015,'暂无礼包');
        }else{
            $data['game_name'] = str_replace('(苹果版)','',$data['game_name']);
            $data['game_name'] = str_replace('(安卓版)','',$data['game_name']);
            $data['desribe'] = $this->ttt($data['desribe']);
            if($token){
                $this->auth($token);
                $map = array();
                $map['game_id'] = $data['game_id'];
                $map['gift_id'] = $data['gift_id'];
                $map['user_id'] = USER_ID;
                $isget = M('gift_record','tab_')->where($map)->getField('id');
                if(!empty($isget)){
                    $data['received'] = 1;
                }else{
                    $data['received'] = 0;
                }
            }
            if(!check_gift_server($data['server_id'])){
                $data['server_name'] = '适用区服已关闭';
            }
            $this->set_message(1,'success',$data);
        }
    }

    /**
     * 去除换行符
     */
    function ttt($str){
        $order = array("\r\n", "\n", "\r");
        $replace = '';
        $str = str_replace($order, $replace, $str);
        return $str;
    }
    /**
     * 领取激活码
     * @param $token
     * @param $gift_id
     * author: yyh 280564871@qq.com
     */
    public function get_novice($token,$gift_id){
        $this->auth($token,"");
        if(!$gift_id){
            $this->set_message(1001,'礼包id数据不能为空',[]);
        }
        $model = new GiftbagModel();
        $exist = $model->checkAccountGiftExist(USER_ID,$gift_id);
        if($exist){
            $this->set_message(1014,"您已经领取过该礼包","","");
        }
        $novice = $model->getNovice(USER_ID,$gift_id);
        if(empty($novice)){
            $this->set_message(1116,"来晚一步，激活码被领光了~","");
        }else if(!empty($novice['qrCodeUrl'])){
            $this->set_message(1117,"您还未关注公众号,请关注公众号后领取~",$novice['qrCodeUrl']);
        }
        $this->set_message(200,'success',$novice);
    }



    /**
     * [hot_game 热门游戏 搜索页面使用]
     * @param  [type]  $sdk_version [description]
     * @param  string  $token       [description]
     * @param  integer $p           [description]
     * @param  string  $type        [description]
     * @return [type]               [description]
     * @author [yyh] <[<email address>]>
     */
    public function hot_game($sdk_version,$token='',$p=0,$type='0'){
        switch ($sdk_version) {
            case 1:
            case 2:
                $sdk_version = $sdk_version.',3';
                break;
            
            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        if (!empty($token)){
            $this->auth($token);
            $user_id = USER_ID;
        }
        $shunxu = $p;
        if(!$type){
            $shuzu = M('Game','tab_')->where(array('game_status'=>1,'recommend_status'=>['like','%2%'],'sdk_version'=>['in',$sdk_version]))->field('id')->group('id')->select();
        }else{
            $shuzu = M('Game','tab_')->where(array('game_status'=>1,'sdk_version'=>['in',$sdk_version]))->field('id')->select();
        }
        $count = count($shuzu);

        $shunxu1 = $shunxu*4;
        $shunxu2 = $shunxu*4+4;

        if (floor($shunxu1/$count) == floor($shunxu2/$count) && floor($shunxu1/$count) == 0){
            $shunxu_x = $shunxu1.",".($shunxu2-$shunxu1);
        }elseif(floor($shunxu1/$count) == floor($shunxu2/$count) && floor($shunxu1/$count) != 0){
            $floor = floor($shunxu1/$count);
            $shunxu_x = ($shunxu1-$floor*$count).",".(($shunxu2-$floor*$count)-($shunxu1-$floor*$count));
        }elseif (floor($shunxu1/$count) == 0 && floor($shunxu2/$count)!=0){
            $floor = floor($shunxu2/$count);
            $shunxu_x1 = $shunxu1.",".($count-$shunxu1);
            $shunxu_x2 = '0'.",".($shunxu2-$floor*$count);
        }elseif(floor($shunxu1/$count) != 0 && floor($shunxu2/$count)!=0 && floor($shunxu1/$count) != floor($shunxu2/$count)){
            $floor1 = floor($shunxu1/$count);
            $floor2 = floor($shunxu2/$count);

            $shunxu_x1 = ($shunxu1-$floor1*$count).",".($count-($shunxu1-$floor1*$count));
            $shunxu_x2 = '0'.",".($shunxu2-$floor2*$count);
        }

        $model = new GameModel();
        if(!$type){
            $map['recommend_status'] = ['like','%2%'];
        }
        $map['sdk_version'] = ['in',$sdk_version];
        if ($shunxu_x){
            $reco = $model->getHotGame($map,'g.sort desc,g.id desc',$shunxu_x,$user_id);
        }else{
            $reco1 = $model->getHotGame($map,'g.sort desc,g.id desc',$shunxu_x1,$user_id);

            if ($shunxu_x2 != '0,0'){
                $reco2 = $model->getHotGame($map,'g.sort desc,g.id desc',$shunxu_x2,$user_id);
                $reco = array_merge($reco1,$reco2);
            }else{
                $reco = $reco1;
            }
        }

        $this->set_message(200,'success',$reco);
    }

    /**
     * [game_recommend_hot 游戏列表 我的足迹使用]
     * @param  string  $token       [description]
     * @param  integer $limit       [description]
     * @param  integer $rec_status  [description]
     * @param  [type]  $sdk_version [description]
     * @return [type]               [description]
     */
    public function game_recommend_hot($token='',$limit=3,$rec_status=1,$sdk_version=1){
        switch ($sdk_version) {
            case 1:
            case 2:
                $sdk_version = array($sdk_version,3);
                break;
            
            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        if (!empty($token)){
            $this->auth($token);
            $user_id = USER_ID;
        }
        $map['recommend_status'] = array('like','%'.$rec_status.'%');
        $map['g.sdk_version'] = array('in',$sdk_version);
        $model = new GameModel();
        $data = $model->getGameLists($map,'g.sort desc,g.id desc',$p=1,$limit,$user_id);
        if (empty($data)){
            $this->set_message(1082,'暂无数据',[]);
        }else{
            $this->set_message(200,'success',$data);
        }
    }
    /**
     * 获取折扣游戏列表
     * @param string $name
     * @param int $p
     * @param $version
     * author: yyh 
     */
    public function get_discount_game_lists($token='',$name="",$p=1,$sdk_version=1){
        empty($name) || $map['g.game_name'] = ['like','%'.$name.'%'];
        $map['_string'] = " (bind_recharge_discount > 0 && bind_recharge_discount<10) OR (p.promote_status =1 and p.first_discount < 10) OR (p.recharge_status=1 and p.continue_discount < 10)";
        //$map['bind_recharge_discount'] = [['gt',0],['elt',10]];
        if (!empty($token)){
            $this->auth($token);
            $user_id = USER_ID;
        }
        $map['g.sdk_version'] = array('in',array($sdk_version,3));
        $model = new GameModel();
        $data = $model->getGameLists1($map,'sort desc,id desc',$p,10,$user_id);
        if(empty($data)){
            $this->set_message(1041,"没有游戏");
        }else{
            $this->set_message(200,"成功",array_values($data));
        }
    }

    /**
     * 绑币充值游戏列表
     * @param $token
     * author: xmy 280564871@qq.com
     */
    public function get_user_recharge_game($token,$sdk_version=1){
        $this->auth($token);
        switch ($sdk_version) {
            case 1:
                break;
            case 2:
                break;
            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        $model = new GameModel();
        $user_id = USER_ID;
        $data = $model->getUserRechargeGame($user_id,$sdk_version);
        if (empty($data)){
            $this->set_message(1034,"游戏不存在");
        }
        $this->set_message(200,"成功",$data);
    }

    public function downRecord($token='',$game_id=""){
        if(empty($token)||empty($game_id)){
            $this->set_message(200,"数据不完整",[]);
        }
        if (!empty($token)){
            $this->auth($token);
            $user_id = USER_ID;
            $user_account = USER_ACCOUNT;
        }
        $model = new GameModel();
        $res = $model->downRecord($user_id,$user_account,$game_id);
        $this->set_message(200,"成功",['record_id'=>$res]);
    }
    public function downRecordLists($token='',$sdk_version,$p=1){
        if (!empty($token)){
            $this->auth($token);
            $user_id = USER_ID;
        }
        switch ($sdk_version) {
            case 1:
            case 2:
                $sdk_version = $sdk_version;
                break;
            
            default:
                $this->set_message(1089, "sdk_version参数填写错误",[],1);
                break;
        }
        $model = new GameModel();
        $data = $model->downRecordLists($sdk_version,$user_id,$p);
        foreach ($data as $key=>$vo){
            if($sdk_version != 3) $data[$key]['xia_status']=check_game_sorue($vo['game_id']);
        }
        $this->set_message(200,"成功",$data);
    }
    public function downRecordDel($token='',$record_id=''){
        if (!empty($token)){
            $this->auth($token);
            $user_id = USER_ID;
        }
        if(empty($record_id)){
            $this->set_message(1089,"删除失败",[]);
        }
        $model = new GameModel();
        $data = $model->downRecordDel($user_id,$record_id);
        if($data){
            $this->set_message(200,"成功",[]);
        }else{
            $this->set_message(1089,"删除失败",[]);
        }

    }
}
