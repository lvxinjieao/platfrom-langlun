<?php
namespace Mobile\Controller;
use Think\Controller;
use Common\Api\GameApi;
use Common\Model\GameModel;
use Common\Model\UserPlayModel;
use Common\Model\UserModel;
use Common\Model\DocumentModel;
use Common\Model\AdvModel;
use Common\Model\ServerModel;
use Com\WechatAuth;

/**
* 首页
*/
class IndexController extends BaseController {

    public function index(){
        $Advmodel = new AdvModel();
        $Docmodel = new DocumentModel();
        $Advdata = $Advmodel->getAdv("slider_wap",5);//轮播图
        $this->assign('sliderWap',$Advdata);
        $usermodel = new UserModel();
        $user_id = session('user_auth.user_id');
        $hdmark = 0;
        $cooktime= (strtotime(date('Y-m-d',time()))+24*3600-1)-time();
        if(!$_COOKIE['mobile_collect']){
            $mobile_collect = 1;
            cookie('collect',1,array('expire'=>$cooktime,'prefix'=>'mobile_'));
        }else{
            $mobile_collect = 0;
        }
        $this->assign('mobile_collect',$mobile_collect);
        if($user_id){
            $hdmark = 1;
            $this->userLatelyPlay();
            $oneparam = $usermodel->getUserOneParam($user_id,'hidden_option');
            $article = $Docmodel->getArticleListsByCategory('',array('mobile_huodong','mobile_gg','mobile_zx','mobile_rule'),1,1000);
            $oneparamarr = json_decode($oneparam['hidden_option'],true);
            $hdmarkarr = $oneparamarr['hidden_hd'];
            $hdmarkstatus = $hdmarkarr['status'];//1隐藏红点  0显示
            $hdmarktime = $hdmarkarr['time']==NULL?0:$hdmarkarr['time'];
            foreach ($article as $key => $value) {
                if($value['line_time']>$hdmarktime){
                    $hdmark = 0;//今日新增或更新  且满足显示条件
                    break;
                }
            }
        }else{
            $hdmark = 1;
            $article = $Docmodel->getArticleListsByCategory('',array('mobile_huodong','mobile_gg','mobile_zx','mobile_rule'),1,1000);
            foreach ($article as $key => $value) {
                if($value['line_time']>mktime(0,0,0,date('m'),date('d'),date('Y'))){
                    $hdmark = 0;//今日新增或更新  且满足显示条件
                    break;
                }
            }
        }
        $this->assign('hdmark',$hdmark);

        //用户头像
        if(is_login()){
            //$user_data = M("user", "tab_")->field('id,account,nickname,real_name,register_way,phone,idcard,balance,shop_point,shop_address,head_icon,is_update_account')->find(is_login());
            $user_data = M("user", "tab_")->field('id,account,nickname,real_name,register_way,phone,idcard,balance,shop_point,shop_address,head_icon')->find(is_login());
            $this->assign('userinfo',$user_data);
        }
        $this->display();
    }

    //用户最近在玩
    Public function userLatelyPlay(){
        $model = new UserPlayModel();
        $map['user_id'] = session('user_auth.user_id');
        $data = $model->getUserPlay($map,"u.play_time desc",1,20);
        $this->assign('userPlay',$data);
    }
    //首页游戏
    public function more_game($rec_status='',$type='sy',$p=1,$limit=10){
        if($_REQUEST['game_id']>0){
            $map['g.id'] = array('neq',$_REQUEST['game_id']);
        }
        $map['recommend_status'] = array('like','%'.$rec_status.'%');
        if($type == 'sy'){
            if( get_device_type() == 'ios'){
                $map['g.sdk_version'] = 2;
            }else{
                $map['g.sdk_version'] = 1;
            }
        }else{
            $map['g.sdk_version'] = 3;
        }
        $model = new GameModel();
        if(is_cache()&&S('game_data'.$rec_status.$type.$p)){
            $data=S('game_data'.$rec_status.$type.$p);
        }else{
            $limit=is_cache()?999:$limit;
            $data = $model->getGameLists($map,'g.sort desc, g.id desc',$p,$limit);
            if(is_cache()){
                S('game_data'.$rec_status.$type.$p,$data);
            }
        }
        if(empty($data)){
            $res['status'] = 0;
        }else{
            $res['status'] = 1;
            $res['data'] = $data;
        }
        if(IS_AJAX){
            $this->ajaxReturn($res,'json');
        }else{
            return $res;
        }
    }

    //小程序游戏
    public function samll_game($p=1,$limit=10){
        $model = M("small_game","tab_");
        $page = intval($p);
        $page = $page ? $page : 1;
        $map['status'] = 1;
        $order = "sort desc,id desc";
        $data = $model
            ->field("id,game_name,scan_num,icon,qrcodeurl,type,qrcode")
            ->where($map)
            ->page($page, $limit)
            ->order($order)
            ->group("id")
            ->select();
        foreach ($data as $key=>$v){
            $data[$key]['icon'] = get_cover($v['icon'],'path');
            if($v['qrcode']){
                $data[$key]['qrcode'] = get_cover($v['qrcode'],'path');
            }
        }
        if(empty($data)){
            $res['status'] = 0;
        }else{
            $res['status'] = 1;
            $res['data'] = $data;
        }
        if(IS_AJAX){
            $this->ajaxReturn($res,'json');
        }else{
            return $res;
        }
    }
    //获取活动
    public function get_article_lists($p=1,$category=6,$limit=10){
        switch ($category) {
            case 4://资讯
                $category_name = "mobile_zx";
                $row = $limit;
                break;
            case 5://公告
                $category_name = "mobile_gg";
                $row = $limit;
                break;
            case 6://活动
                $category_name = "mobile_huodong";
                $row = $limit;
                break;
            case 7://攻略
                $category_name = "mobile_rule";
                $row = $limit;
                break;
        }
        $model = new DocumentModel();
        if(is_login()){
            $hdmark = $model->hdmarkrec(is_login());
        }else{
            $hdmark = false;
        }
        if(get_devices_type() == 2){
            $map['g.sdk_version'] = array('in',array(2,3));
        }else{
            $map['g.sdk_version'] = array('in',array(1,3));
        }
        if($category == 6){
            $data = $model->getArticleListsByCategory2('',$category_name,$p,$row,1,$map);
        }else{
            $data = $model->getArticleListsByCategory('',$category_name,$p,$row,1,$map);
        }

        if($data===false){
            $res['status'] = 0;
            $res['hdmark'] = 1;
        }else{
            $res['status'] = 1;
            $res['data'] = $data;
            $res['hdmark'] = $hdmark===false?0:1;
        }
        $this->ajaxReturn($res,'json');
    }
    //开服
    public function server($type=0,$p=1,$row=10){
        $sdk_version = get_devices_type();
        $model = new ServerModel();
        $user = is_login();
        $data = $model->mobile_server($sdk_version,$type,$p,$row,$user);
        if(empty($data)){
            $res['status'] = 0;
        }else{
            $res['status'] = 1;
            $res['data'] = $data;
        }
        $this->ajaxReturn($res,'json');
    }

    public function setServerNotice($server,$type){
        $user = is_login();
        $model = new ServerModel();
        $result = $model->set_server_notice($user,$server,$type);
        if($result!==false){
            $res['code'] = 1;
        }else{
            $res['code'] = 0;
        }
        $this->ajaxReturn($res,'json');
    }
    
    public function iosdownload(){
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            $system = 1;
            $type = 0;
        }else{
            $system = 2;
            $type = 1;
        }
        if($_SERVER['HTTP_HOST'] == C('WEB_SITE')){
            $map['name'] = array('like','%联运平台%');
            $map['app_version'] = $type;
            $data = M('app', 'tab_')->where($map)->find();
            $data['file_url'] = "http://".$_SERVER['HTTP_HOST'].substr($data['file_url'],1);
            $data['plist_url'] = "https://".$_SERVER['HTTP_HOST'].substr($data['plist_url'],1);
        }else{
            $map['promote_id'] = session('union_host.union_id');
            $map['app_version'] = $type;
            $data = M('app_apply', 'tab_')->where($map)->find();
            $data['file_url'] = "http://".$_SERVER['HTTP_HOST'].substr($data['dow_url'],1);
            $data['plist_url'] = "https://".$_SERVER['HTTP_HOST'].substr($data['plist_url'],1);
            $map1['name'] = array('like','%联运平台%');
            $map1['app_version'] = $type;
            $data1 = M('app', 'tab_')->where($map)->find();
            $data['file_size'] = $data1['file_size'];
        }
        $this->assign('data',$data);
        $this->assign('system',$system);
        $this->assign('app_name',"H5联运APP");
        $this->assign('logo_name',get_cover(C('APP_DOWN_LOGO'),'path'));
        $this->display('download');
    }

    public function down_file($game_id=0){
        if(empty($game_id))$this->error('参数错误');
        $sdk_version = get_devices_type();
        $map['sdk_version'] = $sdk_version;
        $map['relation_game_id'] = $game_id;
        $game = M('game','tab_')->field('id')->where($map)->find();
        if(!$game){
            $map['sdk_version'] = $sdk_version == 1 ? 2 : 1;
            $game = M('game','tab_')->field('id')->where($map)->find();
        }
        $model = new GameModel();
        $user_id = session('user_auth.user_id')>0?session('user_auth.user_id'):0;
        $data = $model->gameDetail($game['id'],$user_id);
        $this->assign('data',$data);
        $this->assign('sdk_version',$sdk_version);
        $this->display();
    }
}  
