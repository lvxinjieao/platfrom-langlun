<?php
namespace Media\Controller;
use Think\Controller;
use Common\Api\GameApi;
use Org\WeiXinSDK\WeiXinOauth;
use Common\Api\PayApi;
use User\Api\MemberApi;
use Org\ThinkSDK\ThinkOauth;
use Org\WeiXinSDK\Weixin;
use Org\UcpaasSDK\Ucpaas;
use Org\XiguSDK\Xigu;
use Org\UcenterSDK\Ucservice;
use Com\Wechat;
use Com\WechatAuth;
use Org\JtpaySDK\Jtpay;
use Common\Model\GameModel;
use Common\Model\GiftbagModel;
use Common\Model\DocumentModel;
use Common\Model\ServerModel;
use Common\Model\AdvModel;
use Think\Model;
class GameController extends BaseController {
	public function index($p=1) {
        $this->assign('ish5',$_REQUEST['ish5']);
		$this->assign('gt',$_REQUEST['game_type']);
		$this->assign('t',$_REQUEST['theme']);
		$this->assign('gn',$_REQUEST['game_name']);
		$map['game_status'] = 1;
        empty($_REQUEST['ish5']) ? $sdk_version = '1,2':$sdk_version = '3';
        !in_array($_REQUEST['sdk_version'], [1,2]) ? $sdk_version = $sdk_version:$sdk_version = $_REQUEST['sdk_version'];
        $map['g.sdk_version'] = ['in',$sdk_version];
		empty($_REQUEST['game_type']) ?  "":$map['game_type_id'] = $_REQUEST['game_type'];
		empty($_REQUEST['theme']) ?  "":$map['short'] = array('like',$_REQUEST['theme'].'%');
		empty($_REQUEST['game_name'])? "":$map['g.game_name'] = array('like','%'.trim($_REQUEST['game_name']).'%');
		$model = new GameModel();
		$row = 10;
		if($_REQUEST['game_name']){
            $data = $model->getGameLists($map,'g.sort desc, g.id desc',$p,$row,'','g.relation_game_id');
            if(!$data){
                $sdk_version = '3';
                $map['g.sdk_version'] = ['in',$sdk_version];
                $data =  $data = $model->getGameLists($map,'g.sort desc, g.id desc',$p,$row,'','g.relation_game_id');
                if($data){
                    redirect(U('Game/index',array('game_name'=>$_REQUEST['game_name'],'ish5'=>1)));
                }
            }
		}else{
            $data = $model->getGameLists($map,'g.sort desc, g.id desc',$p,$row,'','g.relation_game_id');
        }
        $count = $model->getGameListsCounts($map);
        //分页
        $this->set_page($count,$row);
        $this->assign('list_data',$data);
        $this->display();
	}

    //游戏详情
    public function detail($game_id=0) { 
        empty($game_id)&&$this->error('缺少game_id');
        $model = new GameModel();
        $smodel = new ServerModel();
        $user_id = session('user_auth.user_id')>0?session('user_auth.user_id'):0;
        $data = $model->gameDetail($game_id,$user_id,1);
        if(empty($data)){
            $this->error('游戏不存在');
        }
        $this->gameGift($game_id,$data['relation_game_id']);
        $this->gameActiveDoc($game_id,$data['relation_game_id']);
        $this->game_type_like($data['relation_game_id'],$data['game_type_id']);
        $this->assign('data',$data);
        $smap = [];
        $server = $smodel->serverOrder($smap);
        $this->assign('server',$server);

        $map['recommend_status'] = array('like',"%".'2'."%");
        $hotgame = $model->getHotGame($map,'g.sort desc,g.id desc');
        $this->assign('hot',$hotgame);

        /*礼包  */
        $gmodel = new GiftbagModel();
        $gdata = $gmodel->getGiftLists(false,false,1,6,array('giftbag_type'=>2),'','gb.id desc');
        $this->assign('giftlist',$gdata);

        /*广告  */
        $Advmodel = new AdvModel();
        $Advdata = $Advmodel->getAdv("game_pc_wide",1)[0];
        $this->assign('adv',$Advdata);
        
        $ids = M('Game','tab_')->field('id,sdk_version,relation_game_id')->where(['relation_game_id'=>$game_id])->select();
        foreach ($ids as $key => $value) {
            $gameids[$value['sdk_version']] = $value['id'];
            $gameids['relation_game_id'] = $value['relation_game_id'];
        }
        $this->assign('gameids',$gameids);
        $this->display();
    }
    //打开游戏

    public function open_game()
    {
        $gmset = M('game_set','tab_')->field('support_sdk,login_notify_url,third_party_url')->where(array('id'=>$_GET['game_id']))->find();
        if(!empty($gmset['third_party_url'])){
            redirect($gmset['third_party_url']);
        }
        if(I('token')){
            $this->app_write_session(I('token'));//app使用aa
        }
        $http = 'http';
        if(is_https()){
            $http = 'https';
        }
        $full_url = $http.'://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
        // $_1 = strpos($full_url, 'mobile.php/');
        // $_2 = strpos($full_url, 'mobile.php?');
        // $_3 = strpos($full_url, 'mobile.php/?');//此格式用于微信公众号支付授权页面
        // if($_1&&!$_3){
        //     $url = str_replace('mobile.php/','mobile.php/?s=',$_SERVER['REQUEST_URI']);
        // }elseif($_2){
        //     $url = str_replace('mobile.php?s=','mobile.php/?s=',$_SERVER['REQUEST_URI']);
        //     if(!$url){
        //         $url = str_replace('mobile.php?','mobile.php/?s=',$_SERVER['REQUEST_URI']);
        //     }
        // }
        // if($url){
        //     $url = $http.'://'.$_SERVER["HTTP_HOST"].$url;
        //     header("Location: {$url}");
        // }
        if(!$_GET['game_id']){
            $this->ajaxReturn(array('code'=>0,'data'=>'缺少游戏参数'));
        }

        $type = M('game','tab_')->where(array('id'=>$_GET['game_id']))->find();

        $game_icon = icon_url($type['icon']);
        $this->assign('game_icon',$game_icon);
        $this->assign('game_name',$type['game_name']);
        $this->assign('game_id',$_GET['game_id']);
        //游戏报错页面
        if(empty($type)){
            redirect(U('Game/user_game_error',['ish5'=>1]));exit;
        }elseif($type['game_status'] != 1 && $type['test_status'] != 0){
            redirect(U('Game/user_game_error',['ish5'=>1]));exit;
        }

        $game_load_page =icon_url($type['game_load_page']);
        if(!getimagesize($game_load_page)){
            $game_load_page = false; 
        }else{
            $this->assign("game_load_page",$game_load_page);
        }
        
        if(is_weixin()){
            //微信分享参数
            $signPackage = $this->sharefun();
            if(empty(session('shareparams'))){
                $shareparams['title'] = $type['game_name']; 
                $shareparams['desc'] = '我正在玩“'.$type['game_name'].'”,快来和我一起玩吧！';  
                $shareparams['imgUrl'] = icon_url($type['icon']);
                $shareparams['link'] = $full_url;
            }else{
                $shareparams = session('shareparams');
            }
            $this->assign('signPackage',$signPackage);
            $this->assign('shareparams',$shareparams);
        }
        $pid =$_GET['pid']==""?0:$_GET['pid'];
        $GameApi = new GameApi();
        $uid = is_login();
        $yk = M('User','tab_')->field('lock_status,register_way,phone')->find($uid);
        $this->assign('promote_id',$pid);
        $game_id = $_GET['game_id'];

        if($uid>0&&!session('stop_play')&&$yk['lock_status']==1){
            $login_url = $GameApi->game_login($uid,$game_id,$pid);
            $this->assign("login_url",$login_url);
        }elseif($uid>0&&$yk['lock_status']!=1){
            redirect(U('Index/index'));//禁止锁定用户登录
        }else{
            session('pro_gid',$game_id);
            session('pro_pid',$pid);
            // redirect(U('Subscriber/login'));//禁止锁定用户登录
        }
        //添加游戏加载图片logo
        $game_logo = get_cover(C('WAP_SET_LOGO'),'path');
        $this->assign('game_logo',$game_logo);

        $this->assign('yk',$yk);
        $this->assign('prev_url',$_SERVER['HTTP_REFERER']);
        if ($type['screen_type'] == 1) {
            $this->display('open_game_landscape');  
        }else {
            $this->display('open_game');                    
        }
    }

    //游戏详情礼包
    private function gameGift($game_id='',$relation_game_id=''){
        empty($game_id)&&$this->ajaxReturn(array('code'=>0,'msg'=>'缺少game_id'));
        $model = new GiftbagModel();
        if(ACTION_NAME=='suspension_gift'){
            $data = $model->getGiftLists(false,$game_id,1,100);
        }else{
            $data = $model->getGiftLists(false,$game_id,1,100);
        }
        $map['relation_game_id'] = $relation_game_id;
        $map['id'] = array('neq',$game_id);
        $map['game_status'] = 1;
        $map['test_status'] = 1;
        $game = M('game','tab_')->where($map)->field('id')->find();
        if($game){
            $data1 = $model->getGiftLists(false,$game['id'],1,100);
            foreach ($data1 as $key=>$v){
                $data[] = $v;
            }
        }
        $user_id = session('user_auth.user_id');
        if($user_id){
            foreach ($data as $key => $value) {
                $map = array();
                $map['game_id'] = $value['game_id'];
                $map['gift_id'] = $value['gift_id'];
                $map['user_id'] = $user_id;
                $isget = M('gift_record','tab_')->where($map)->getField('id');
                if(!empty($isget)){
                    $data[$key]['received'] = 1;
                }else{
                    $data[$key]['received'] = 0;
                }
            }
        }
        if(ACTION_NAME=='suspension_gift'){
            return $data;
        }else{
            $this->assign('gamegift',$data);
        }
    }
    //游戏文章
    private function gameActiveDoc($game_id='',$relation_game_id=''){
        empty($game_id)&&$this->ajaxReturn(array('code'=>0,'msg'=>'缺少game_id'));
        $model = new DocumentModel();
        $data = $model->getArticleListsByCategory($game_id,'wap_huodong',1,3);
        $data2 = $model->getArticleListsByCategory($game_id,'wap_gg',1,4);
        $map['relation_game_id'] = $relation_game_id;
        $map['id'] = array('neq',$game_id);
        $map['game_status'] = 1;
        $map['test_status'] = 1;
        $game = M('game','tab_')->where($map)->field('id')->find();
        if($game){
            $data1 = $model->getArticleListsByCategory($game['id'],'wap_huodong',1,3);
            foreach ($data1 as $key=>$v){
                $data[] = $v;
            }
            $data3 = $model->getArticleListsByCategory($game['id'],'wap_gg',1,4);
            foreach ($data3 as $key=>$v){
                $data2[] = $v;
            }
        }
        $this->assign('gameactive',$data);
        $this->assign('gamegg',$data2);
    }
    
    /**
     *同类型游戏
     */
    public function game_type_like($relation_game_id,$game_type_id,$sort="sort desc",$limit=4){
        if(!$game_type_id){
            return false;
        }
        $map['game_status'] =1;
        $map['relation_game_id'] =array('neq',$relation_game_id);
        $map['game_type_id'] =$game_type_id;
        $game_list=M('Game','tab_')->field('id,icon,game_name,relation_game_name,relation_game_id,game_type_id,sdk_version')->where($map)->order($sort)->select();

        if(count($game_list)<$limit){
            $limit =count($game_list);
        }
        $game_keys=array_rand($game_list,$limit);
        if(is_array($game_keys)){
            foreach ($game_keys as $val) {
                $game_like['game_id']=$game_list[$val]['id'];
                $game_like['sdk_version']=$game_list[$val]['sdk_version'];
                $game_like['icon']=icon_url($game_list[$val]['icon']);
                $game_like['game_name']=$game_list[$val]['relation_game_name'];
                $game_like['game_type_id']=$game_list[$val]['game_type_id'];
                $game_like['play_url']='http://' . $_SERVER['HTTP_HOST'] .'/mobile.php/?s=/Game/open_game/game_id/'.$game_list[$val]['id'];
                if($game_list[$val]['sdk_version'] == 3){
                    $game_like['play_detail_url']=U('Game/detail',array('game_id'=>$game_list[$val]['relation_game_id'],'ish5'=>1));
                }else{
                    $game_like['play_detail_url']=U('Game/detail',array('game_id'=>$game_list[$val]['relation_game_id']));
                }
                $res[]=$game_like;
            }
        }else{
            $res[0]['game_id']=$game_list[0]['id'];
            $res[0]['sdk_version']=$game_list[0]['sdk_version'];
            $res[0]['icon']=icon_url($game_list[0]['icon']);
            $res[0]['game_name']=$game_list[0]['relation_game_name'];
            $res[0]['game_type_id']=$game_list[0]['game_type_id'];
            $res[0]['play_url']='http://' . $_SERVER['HTTP_HOST'] .'/mobile.php/?s=/Game/open_game/game_id/'.$game_list[0]['id'];
            if($game_list[0]['sdk_version'] == 3){
                $res[0]['play_detail_url']=U('Game/detail',array('game_id'=>$game_list[0]['relation_game_id'],'ish5'=>1));
            }else{
                $res[0]['play_detail_url']=U('Game/detail',array('game_id'=>$game_list[0]['relation_game_id']));
            }
        }
        $this->assign("gamelike",$res);
    }
    

    //收藏
    public function collection($collect_status=0,$game_id=0) { 
        $user_id = session('user_auth.user_id');
        if(!$user_id){
            $this->ajaxReturn(array('code'=>-1));
        }
        $map['user_id'] = $user_id;
        $map['game_id'] = $game_id;
        $map['status'] = ['in','-1,1'];
        $data = M('user_behavior as ub','tab_')
                ->where($map)
                ->find();
        if($data){
            $save['status'] = $collect_status<=0?1:-1;
            $save['id'] = $data['id'];
            $save['update_time'] = time();
            $res = M('user_behavior','tab_')->save($save);
            if($res){
                $result['code'] = 1;
                $result['data'] = $save['status'];
            }else{
                $result['code'] = 0;
            }
        }else{
            $save['user_id'] = $user_id;
            $save['game_id'] = $game_id;
            $save['status'] = $collect_status==0?1:-1;
            $save['update_time'] = time();
            $save['create_time'] = $save['update_time'];
            $res = M('user_behavior','tab_')->add($save);
            if($res){
                $result['code'] = 1;
                $result['data'] = $save['status'];
            }else{
                $result['code'] = 0;
            }
        }
        $this->ajaxReturn($result);
    }

    public function kaifu($p=1,$type=2) {
        $row= 10;
        $model = new ServerModel();
        $user = is_login();
        $data= $model->server('1,2,3',$type,$p,$row,$user,1);
        if(!empty($data['list'])){
            foreach ($data['list'] as &$val){
                $gmodel = new GiftbagModel();
                $gdata = $gmodel->getGiftLists(false,$val['game_id'],1,1,array('gb.server_id'=>$val['server_id']),$user,'gb.id desc');
                if (!$gdata){
                    $gdata = $gmodel->getGiftLists(false,$val['game_id'],1,1,[],$user,'gb.id desc');
                }
                $val['gift'] = $gdata?$gdata[0]:$gdata;
            }
        }
        $this->assign('server',$data['list']);
        $this->set_page($data['count'],$row);
        $this->assign('type',$type);
        $this->display();
    }
    public function suspension_gift($game_id){
        $gamemodel = new GameModel();
        $detail = $gamemodel->gameDetail($game_id,is_login());
        $this->assign('detail',$detail);
        $giftmodel = new GiftbagModel();
        $detail = $this->gameGift($game_id);
        $rec_status = $_POST['rec_status']?false:$_POST['rec_status'];
        $other = $giftmodel->getGameGiftLists('3',$rec_status,is_login(),array('g.id'=>array('neq',$game_id)));
        if(empty($detail)){
            $gift['detail']['status'] = 0;
        }else{
            $gift['detail']['status'] = 1;
            $gift['detail']['data'] = $detail;
        }
        if(empty($other)){
            $gift['other']['status'] = 0;
        }else{
            $gift['other']['status'] = 1;
            $gift['other']['data'] = $other;
        }
        $this->ajaxReturn(array('status'=>1,'data'=>$gift));
    }

    public function suspension_leave(){
        $map['game_id'] = $_REQUEST['game_id'];
        $map['user_id'] = is_login();
        $map['status'] = array('in','-1,1');
        $collection=M('user_behavior','tab_')->where($map)->find();
        $data['qrcode'] = get_cover(C('PC_SET_QRCODE'),'path');
        $data['like'] = $this->gsULike(3);
        $data['collection'] = $collection['status']?$collection['status']:-1;
        $this->ajaxReturn(array('status'=>1,'data'=>$data));
    }
    /**
     * 猜你喜欢和大家都在玩（随机取出四条数据）
     * @param  无
     * @return 四条游戏数据
     * @author lyf
     */
    public function gsULike($getnum=4){
        $game_list=get_game_list();
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
            $game_like['game_name']=$game_list[$val]['game_name'];
            $game_like['game_type_id']=$game_list[$val]['game_type_id'];
            $game_like['play_url']='http://' . $_SERVER['HTTP_HOST'] .'/mobile.php/?s=/Game/open_game/game_id/'.$game_list[$val]['id'];
            $game_like['play_detail_url']=U('Game/detail',array('game_id'=>$game_list[$val]['id']));
            $res[]=$game_like;
        }
        if(IS_AJAX){
            return $res;exit;
        }else{
            $this->assign('gamelike',$res);
        }
    }

    private function sharefun(){
        $result = auto_get_access_token('access_token_validity.txt');
        $appid = C('wechat.appid');
        $appsecret = C('wechat.appsecret');
        if (!$result['is_validity']) {
            $auth = new WechatAuth($appid, $appsecret);
            $token = $auth->getAccessToken();
            $token['expires_in_validity'] = time() + $token['expires_in'];
            wite_text(json_encode($token), 'access_token_validity.txt');
            $result = auto_get_access_token('access_token_validity.txt');
        }
        $auth = new WechatAuth($appid, $appsecret,$result['access_token']);
        $ticket = auto_get_ticket(dirname(__FILE__) . '/ticket.txt');
        if(!$ticket['is_validity']){
            $jsapiTicketjson = $auth->getticket();
            $jsapiTicketarr = json_decode($jsapiTicketjson,true);
            if($jsapiTicketarr['errcode']!=0){
                $this->sharefun();exit;
            }
            $jsapiTicketarr['expires_in_validity'] = time() + $jsapiTicketarr['expires_in'];
            wite_text(json_encode($jsapiTicketarr), dirname(__FILE__) . '/ticket.txt');
            $ticket = auto_get_ticket(dirname(__FILE__) . '/ticket.txt');
        }
        $signPackage = $auth->getSignPackage($ticket['ticket']);
        return $signPackage;
    }
    // game_pay 暂不支持

    public function warn(){
        if(is_weixin()){
            $this->display();
        }else{
            $http = 'http';
            if(is_https()){
                $http = 'https';
            }
            $url =  U('game_pay_sdk').'&'.http_build_query($_REQUEST);
            redirect($url);
        }
    }
    public function goldpig_return_callback(){
        $url = 'http://357p.com/api/mun.asp?mun='.I('get.orderId');
        $content = file_get_contents($url);
        $data = json_decode($content,true);
        if(is_array($data)){
            $order = $data['jinzhua'];
            $pay_where = substr($order,0,2);
            switch ($pay_where) {
                case 'SP':
                    $Spend = M('Spend',"tab_");
                    $map['pay_order_number'] = $order;
                    $orderinfo = $Spend->where($map)->find();
                    break;
                case 'PF':
                    $this->redirect("http://".$_SERVER['HTTP_HOST']."/mediawide.php?s=/Subscriber/index.html");
            }
            $this->game_pay_callback($order,$orderinfo['game_id']);
        }else{
            redirect(U('Index/index'));
        }
    }

    public function game_pay_sdk($developers = '')
    {
        if (IS_POST) {
            $user_id = is_login();
            $_REQUEST['user_id'] = $user_id;
            if (isset($_REQUEST['game_appid']) && $_REQUEST['game_appid'] != '') {
                $map['user_id'] = $user_id;
                $map['game_appid'] = $_REQUEST['game_appid'];
                $bind_ptb = M('user_play', 'tab_')
                    ->where($map)
                    ->find();
            }

            switch ($_REQUEST['payway']) {
                case 'alipay':
                    $this->alipay($_REQUEST);
                    break;
                case 'goldpig':
                    $pay = A('Pay');
                    $pay->gameGoldPigRecharge();
                    break;
                case 'wx':
                    $this->wx_jsapi_pay($_REQUEST);
                    break;
                case 'ptb':
                    $this->platform_coin_pay($_REQUEST);
                    break;
                case 'jft':
                    $this->jftpay($_REQUEST);
                    break;
                case 'bind_ptb':
                    $this->platform_coin_pay($_REQUEST, $bind_ptb);
                    break;
            }
            exit;
        }else{
            switch ($_REQUEST['payway']) {
                case 'alipay':
                    if(is_weixin()){
                        $game_data = get_game_entity($_REQUEST['game_appid']);
                        if(empty($game_data)){
                            redirect(U('index/index'));
                        }else{
                            redirect(U('Game/open_game',array('game_id'=>$game_data['id'])));
                        }
                        exit;
                    }
                    $this->alipay($_REQUEST);
                    break;
            }
        }
    }

    public function jftpay($param=''){
        if (pay_set_status('jft') == 0) {
            $this->error("网站未启用竣付通充值", '', 1);
            exit();
        }

        #判断账号是否存在
        $user = get_user_entity(get_uname(), true);

        if (empty($user)) {
            $this->error("用户不存在");
            exit();
        } else {
            $data2["user_id"] = $user["id"];
            $data2["user_account"] = $user['account'];
            $data2["user_nickname"] = $user['nickname'];
        }
        $game_data = get_game_entity($param['game_appid']);
        if(empty($game_data)){$this->error("游戏不存在");exit();}
        #支付配置
        $data['options']  = 'spend';
        $data['order_no'] = 'SP_' . date('Ymd') . date('His') . sp_random_string(4);
        $data['fee']      = $_POST['amount'];//$_POST['amount'];
        #平台币记录数据
        $data['order_number'] = "";
        $data['pay_order_number'] = $data['order_no'];
        $data['user_id'] = $data2['user_id'];
        $data['user_account'] = $data2['user_account'];
        $data['user_nickname'] = $data2['user_nickname'];
        $data['promote_id'] = $user['promote_id'];
        $data['promote_account'] = $user['promote_account'];
        $data['pay_amount'] = $param['amount'];
        $data['pay_status'] = 0;
        $data['pay_way'] = 6;
        $data['pay_source'] = 1;
        $data['uc'] = $data2['uc'];
        $data["game_id"]       = $game_data['id'];
        $data["game_appid"]    = $param["game_appid"];
        $data["game_name"]     = $game_data["game_name"];
        $data["server_id"]     = $param["server_id"];
        $data["server_name"]   = $param["server_name"];
        $data["game_player_id"]       = $param["role_id"];
        $data["game_player_name"]     = $param["role_name"];
        $pay = new PayApi();
        $pay->add_spend($data);
        $sign=think_encrypt(md5($data['pay_amount'].$data['order_no']));
        redirect(U('pay_way', array('data'=>$data,'type'=>'Alipay','account' =>$data2['user_account'],'pay_amount'=>$data['pay_amount'],'sign'=>$sign,'pay_order_number'=>$data['order_no'])));
        
    }

    //竣付通
    public function pay_way(){
        if(IS_POST){
            $msign=think_encrypt(md5($_POST['pay_amount'].$_POST['pay_order_number']));
            if($msign!==$_POST['sign']){
                $this->error('验证失败',U('Recharge/pay'));exit;
            }
             #判断账号是否存在
             $user = get_user_entity($_POST['account'], true);
             $jtpay=new Jtpay();

             #平台币记录数据            
            $data['out_trade_no'] = $_POST['pay_order_number'];
            $data['user_id'] = $user['id'];
            $data['user_account'] = $user['account'];
            $data['user_nickname'] = $user['nickname'];
            $data['promote_id'] = $user['promote_id'];
            $data['promote_account'] = $user['promote_account'];
            $data['fee'] = $_POST['pay_amount'];
            $data['pay_way'] = 6;//竣付通
            $data['pay_source'] = 1;
            $data["game_id"]       = $_POST['data']['game_id'];
            $data["game_appid"]    = $_POST['data']['game_appid'];
            $data["game_name"]     = $_POST['data']['game_name'];
            switch ($_POST['type']) {
                case 'Alipay':
                 echo $jtpay->jt_pay($_POST['pay_order_number'],$_POST['pay_amount'],$_POST['account'],get_client_ip(),'',4,'',3,2);
                    break;
                case 'wapWeChat':
                 echo $jtpay->jt_pay($_POST['pay_order_number'],$_POST['pay_amount'],$_POST['account'],get_client_ip(),'',3,'',3,2);
                    break;
                case 'WeChat':
                echo $jtpay->jt_pay($_POST['pay_order_number'],$_POST['pay_amount'],$_POST['account'],get_client_ip(),'',3,'',4,2);
                    break;
                case 'QQ':
                 echo $jtpay->jt_pay($_POST['pay_order_number'],$_POST['pay_amount'],$_POST['account'],get_client_ip(),'',11,'',3,2);
                    break;
                default:
                echo $jtpay->jt_pay($_POST['pay_order_number'],$_POST['pay_amount'],$_POST['account'],get_client_ip(),"",$_POST['p9_paymethod']);
                    break;

            }
           
        }else{
            $this->assign('type',$_GET['type']);
            $this->display();
        }
    }

    public function UnionPay(){
        $this->display();
    }



    

    /**

    *支付宝支付

    */

    Public function alipay($param=''){
        $pay = A('Pay');
        $pay->game_alipay($param);
        exit;
        //支付sign验证
        if(($param['sign']!=session('game_pay_sign'))&&$_REQUEST['from']!='wxgzh'){
            $this->error("非法操作！");exit();
        }
        #判断账号是否存在
        $userid = $_REQUEST['from']!='wxgzh'?is_login():$_REQUEST['user_id'];
        $user = get_user_entity($userid);
        if (empty($user)) {
            $this->error("用户不存在");exit();
        }else{
            $data["user_id"]       = $user["id"];
            $data["user_account"]  = $user['account'];
            $data["user_nickname"] = $user['nickname'];
        }
        $game_data = get_game_entity($param['game_appid']);
        if(empty($game_data)){$this->error("游戏不存在");exit();}
        #支付配置
        $data['options']  = 'spend';
        $data['order_no'] = 'SP_'.date('Ymd').date ( 'His' ).sp_random_string(4);
        $data['fee']      = $param['price']/100;
        $data['pay_type'] = 'alipay'; 
        #平台币记录数据
        $data["game_id"]       = $game_data['id'];
        $data["game_appid"]    = $param["game_appid"];
        $data["game_name"]     = $game_data["game_name"];

        $data["server_id"]     = $param["server_id"];
        $data["server_name"]   = $param["server_name"];
        $data["game_player_id"]     = $param["role_id"];
        $data["game_player_name"]   = $param["role_name"];

        $data["promote_id"]    = $user['promote_id'];
        $data["promote_account"] = $user['promote_account'];
        $data["pay_order_number"]= $data['order_no'];
        $data["title"] = $param['props_name'];
        $data["pay_amount"]   = $param['price']/100;
        $data["pay_way"] = 1;
        $data['code']=1;
        $data['extend']  = $param['trade_no'];
        $data['server']   = 'alipay.wap.create.direct.pay.by.user';
        $data['method']   = 'wap';
        $pay = new PayApi();
        $url = $pay->other_pay($data,C('alipay'),$data["game_id"]);
        redirect($url);exit;
    }

    public function get_wx_code(){
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
            $data["game_id"]       = $game_data['id'];
            $data["game_appid"]    = $_POST["game_appid"];
            $data["game_name"]     = $game_data["game_name"];
            $data["server_id"]     = $_POST["server_id"];
            $data["server_name"]   = $_POST["server_name"];
            $data["game_player_id"]     = $_POST["role_id"];
            $data["game_player_name"]   = $_POST["role_name"];
            $data["promote_id"]    = $user['promote_id'];
            $data["promote_account"] = $user['promote_account'];
            $data["pay_order_number"]= $data['order_no'];
            $data["title"] = $_POST['props_name'];
            $data["pay_amount"]   =  $_POST['amount']/100;
            $data["pay_way"] = 2;
            $data['extend']  = $_POST['trade_no'];
            $pay = new PayApi();
            $pay->add_spend($data);
            $openid = session("wechat_token.openid");
            $state = $data["pay_amount"] . ',' . $data['order_no'] . ',' . simple_encode(get_uname()) . ',' . $data["game_id"] . ',' . $_SERVER['HTTP_HOST'].','.$openid;//指针0金额，1订单，2用户名，3游戏id,4发起支付的域名,5openid
            //触发微信返回code码
            if(session('for_third')==C(PC_SET_DOMAIM)){
                $code_url="http://".session('for_third').'/mobile.php/Subscriber/get_wx_code/';
                $url = $jsApi->createOauthUrlForCode($code_url, $state);
                $this->ajaxReturn(array('url' => $url, 'status' => -100));
            }elseif($_POST['referer_pay']==1){
                $code_url="http://".C(PC_SET_DOMAIM).'/mobile.php/Subscriber/get_wx_code/';
                $url = $jsApi->createOauthUrlForCode($code_url, $state);
                $this->ajaxReturn(array('url' => $url, 'status' => -100));
            }else{
                $code_url="http://".$_SERVER['HTTP_HOST'].'/mobile.php/Subscriber/get_wx_code/';
            }
            $weixn=new Weixin();
            $state1=explode(',',$state);
            $amount=$state1[0];
            $out_trade_no=$state1[1];
            $game_id=$state1[2];
            $spendinfo=M('Spend','tab_')->where(array('pay_order_number'=>$out_trade_no))->find();
            $is_pay=$weixn->weixin_jsapi("游戏充值",$out_trade_no,$amount,$jsApi,$openid);
            session('is_pay',$is_pay);
            exit($is_pay);
        }
    }

    /**

    *微信支付

    */

    public function wx_pay(){
        #判断账号是否存在
        if($_REQUEST['sign']!=session('game_pay_sign')){
            $this->ajaxReturn(array("status"=>0,"msg"=>'数据异常！'));exit;
        }
        $user = get_user_entity(get_uname(),true);
        #判断账号是否存在
        if (empty($user)) {
            $this->error("用户不存在");exit();
        }else{
            $data["user_id"]       = $user["id"];
            $data["user_account"]  = $user['account'];
            $data["user_nickname"] = $user['nickname'];
        }
        $game_data = get_game_entity($_POST["game_appid"]);
        if(empty($game_data)){$this->error("游戏不存在");exit();}
        #支付配置
        $data['options']  = 'spend';
        $data['order_no'] = 'SP_'.date('Ymd').date ( 'His' ).sp_random_string(4);
        $data['fee']      = $_POST['amount']/100;
        $data['pay_type'] = 'swiftpass'; 
        $data['server'] = "pay.weixin.native";
        #平台币记录数据
        $data["game_id"]       = $game_data['id'];
        $data["game_appid"]    = $_POST["game_appid"];
        $data["game_name"]     = $game_data["game_name"];
        $data["server_id"]     = $_POST["server_id"];
        $data["server_name"]   = $_POST["server_name"];
        $data["game_player_id"]     = $_POST["role_id"];
        $data["game_player_name"]   = $_POST["role_name"];
        $data["promote_id"]    = $user['promote_id'];
        $data["promote_account"] = $user['promote_account'];
        $data["pay_order_number"]= $data['order_no'];
        $data["title"] = $_POST['props_name'];
        $data["pay_amount"]   = $_POST['amount']/100;
        $data["pay_way"] = 2;
        $data['extend']  = $_POST['trade_no'];
        $data['code']  = 1;
        $pay = new PayApi();
        if(get_wx_type()==0){//0官方
            $weixn=new Weixin();
            $is_pay=json_decode($weixn->weixin_pay($data["title"],$data['order_no'],$data['fee'],'NATIVE'),true);
            if($is_pay['status']===1){
                            $html ='<div class="d_body" style="height:px;">
                    <div class="d_content">
                        <div class="text_center">
                            <table class="list" width="100%">
                                <tbody>
                                <tr>
                                    <td class="text_right">订单号</td>
                                    <td class="text_left">'.$data['order_no'].'</td>
                                </tr>
                                <tr>
                                    <td class="text_right">充值金额</td>
                                    <td class="text_left">本次充值'.$data['fee'].'元，实际付款'.$data['fee'].'元</td>
                                </tr>
                                </tbody>
                            </table>
                            <img src="'.U('Subscriber/qrcode',array('level'=>3,'size'=>4,'url'=>base64_encode(base64_encode($is_pay['url'])))).'" height="301" width="301">
                            <br/>&nbsp;&nbsp;&nbsp;&nbsp;
                            <img src="/Public/Media/images/wx_pay_tips.png">
                        </div>              </div>
                </div>';
                $pay->add_spend($data);     
                $this->ajaxReturn(array("status"=>1,"out_trade_no"=>$data['order_no'],"html"=>$html,"game_id"=>$data["game_id"]));
            }else{
                $this->ajaxReturn(array("status"=>0,"msg"=>'支付数据错误！'));
            }
        }else{
            $data['pay_way']=4;
            $arr = $pay->other_pay($data,C('weixin'));
            if($arr['status1'] === 500){
                \Think\Log::record($arr['msg']);
                $html ='<div class="d_body" style="height:px;">
                        <div class="d_content">
                            <div class="text_center">'.$arr["msg"].'</div>
                        </div>
                        </div>';
                $json_data = array("status"=>1,"html"=>$html);
            }else{
                $html ='<div class="d_body" style="height:px;">
                        <div class="d_content">
                            <div class="text_center">
                                <table class="list" width="100%">
                                    <tbody>
                                    <tr>
                                        <td class="text_right">订单号</td>
                                        <td class="text_left">'.$arr["out_trade_no"].'</td>
                                    </tr>
                                    <tr>
                                        <td class="text_right">充值金额</td>
                                        <td class="text_left">本次充值'.$data["pay_amount"].'元，实际付款'.$data["pay_amount"].'元</td>
                                    </tr>
                                    </tbody>
                                </table>
                                <img src="'.$arr["code_img_url"].'" height="301" width="301">
                                <br/>&nbsp;&nbsp;&nbsp;&nbsp;
                                <img src="/Public/Media/images/wx_pay_tips.png">
                            </div>
                        </div>
                    </div>';
                $json_data = array("status"=>1,"out_trade_no"=>$arr["out_trade_no"],"html"=>$html,"game_id"=>$data["game_id"]);
            }
            $this->ajaxReturn($json_data);
        }
    }
    /**
    *微信wap支付
    */
    public function wx_wap_pay(){
        $user = get_user_entity(get_uname(),true);

        if (empty($user)) {

            $this->error("用户不存在");exit();

        }else{

            $data["user_id"]       = $user["id"];

            $data["user_account"]  = $user['account'];

            $data["user_nickname"] = $user['nickname'];

        }

        $game_data = get_game_entity($_POST['game_appid']);

        #支付配置

        $data['code']     = 1;

        $data['fee']    = $_POST['amount']/100;

        $data['body']     = '游戏充值';

        $data['pay_type']  = 'swiftpass';

        $data['config']   = 'weixin';

        $data['method']   = 'direct';

        $data['server']   = 'pay.weixin.wappay';

        $data['signtype'] = 'MD5';

        #平台币记录数据

        $data['pay_status']       = 0;

        $data['payway']           = 2;

        $data['pay_source']       = 1;

        $data['options']  = 'spend';

        $data['order_no'] = 'SP_'.date('Ymd').date ( 'His' ).sp_random_string(4);

        #平台币记录数据

        $data["game_id"]       = $game_data['id'];

        $data["game_appid"]    = $_POST["game_appid"];

        $data["game_name"]     = $game_data["game_name"];

        $data["server_id"]     = $_POST["server_id"];
        $data["server_name"]   = $_POST["server_name"];
        $data["game_player_id"]     = $_POST["role_id"];
        $data["game_player_name"]   = $_POST["role_name"];

        $data["promote_id"]    = $user['promote_id'];

        $data["promote_account"] = $user['promote_account'];

        $data["pay_order_number"]= $data['order_no'];

        $data["title"] = $_POST['props_name'];

        $data["pay_amount"]   = $_POST['amount']/100;

        $data["pay_way"] = 4;

        $data['extend']  = $_POST['trade_no'];

        $pay = new PayApi();

        $arr = $pay->other_pay($data,C('weixin'));

        if($arr['status1'] === 500){

            \Think\Log::record($arr['msg']);

            $html ='<div class="d_body" style="height:px;">

                    <div class="d_content">

                        <div class="text_center">'.$arr["msg"].'</div>

                    </div>

                    </div>';

            $json_data = array("status"=>0,"html"=>$html);

        }else{

            $json_data = array("status"=>1,"pay_info"=>$arr['pay_info']);

        }

        $this->ajaxReturn($json_data);

    }

    public function weixin_wap_pay(){
        $user = M('User','tab_')->field('id as user_id,account as user_account,nickname as user_nickname,promote_id,promote_account,lock_status,real_name,idcard')->find(is_login());
        if($user['lock_status']!=1){
            session('user_auth', null);
            session('user_auth_sign', null);
            session("wechat_token", null);
            $this->error("用户不存在或被禁用");
        }
        $game_data = get_game_entity($_POST['game_appid']);
        if(empty($game_data)){
            $this->error("游戏不存在");
        }
        if(pay_set_status('wei_xin')==0){
            $this->error("网站未启用微信充值",'',1);
            exit();
        }
        if($_POST['amount']<0){$this->error('充值金额有误');exit();}
        $data['out_trade_no'] = 'SP_' . date('Ymd') . date('His') . sp_random_string(4);
        //支付参数
        $param['service'] = "pay.weixin.wappay";
        $param['ip'] = get_client_ip();
        $param['pay_amount'] = $_POST['amount']/100;
        $param['game_name'] = $game_data['game_name'];
        $param['game_id'] = $game_data['id'];
        $param['out_trade_no']= $data['out_trade_no'];
        $param['body']="游戏充值";
        //spend表
        $user['order_no'] = $data['out_trade_no'];
        $user['game_id'] = $game_data['id'];
        $user['game_appid'] = $_POST['game_appid'];
        $user['game_name'] = $game_data['game_name'];
        $user['title'] = $_POST['props_name'];
        $user['pay_amount'] = $_POST['amount']/100;
        $user['pay_way'] = 2;
        $user['extend'] = $_POST['trade_no'];

        $user["server_id"]     = $_POST["server_id"];
        $user["server_name"]   = $_POST["server_name"];
        $user["game_player_id"]     = $_POST["role_id"];
        $user["game_player_name"]   = $_POST["role_name"];
        $weixn = new Weixin();
        $is_pay = json_decode($weixn->weixin_pay($param['body'], $param['out_trade_no'], $param['pay_amount'], 'MWEB'), true);
        if($is_pay['status']==1){
            $payapi = new PayApi();
            $payapi->add_spend($user);
            $json_data = array('status' => 1, 'pay_info' => $is_pay['mweb_url']);
        }else{
            $json_data = array('status' => -1, 'info'=>$is_pay['return_msg']);
        }
        $this->ajaxReturn($json_data);

    }


    /**

    *微信 JSAPI 支付

    */

    public function wx_jsapi_pay($param = array()){
        $user = get_user_entity(get_uname(),true);
        if (empty($user)) {
            $this->error("用户不存在");exit();
        }else{
            $data["user_id"]       = $user["id"];
            $data["user_account"]  = $user['account'];
            $data["user_nickname"] = $user['nickname'];
        }
        $game_data = get_game_entity($param['game_appid']);
        if(get_wx_type()==0){//0官方
        Vendor("WxPayPubHelper.WxPayPubHelper");
            //使用jsapi接口
            $jsApi = new \JsApi_pub('c05a88199e9ef60d96a15b241ce7c1cd',C('wei_xin.email'));
            //=========步骤1：网页授权获取用户openid============
            //通过code获得openid
            if (!isset($_GET['code']))
            {
                //触发微信返回code码
                $url = $jsApi->createOauthUrlForCode("http://www.1n.cn/mobile.php/Subscriber/wx_jsapi_pay/account/$account/amount/$amount");
                // $url = $jsApi->createOauthUrlForCode("http://www.1n.cn/mobile.php/Subscriber/pay");
                Header("Location: $url");
            }else
            {
                //获取code码，以获取openid
                $code = $_GET['code'];
                $jsApi->setCode($code);
                $openid = $jsApi->getOpenId();
            }
         $weixn=new Weixin();
         $datt['out_trade_no']  = "PF_".date('Ymd').date('His').sp_random_string(4);
         $datt['amount']  = $_POST['amount'];
         $datt['pay_status']       = 0;
         $datt['pay_way']           = 2;
         $datt['pay_source']       = 1;
         $is_pay=$weixn->weixin_jsapi($_POST['props_name'],$datt['out_trade_no'], $datt['amount'],$jsApi,$openid);
         $this->assign('jsApiParameters',$is_pay);
        }else{
            $data['code']     = 1;
            $data['fee']    = $_POST['amount']/100;
            $data['body']     = '游戏充值';
            $data['pay_type']  = 'swiftpass';
            $data['config']   = 'weixin';
            $data['method']   = 'direct';
            $data['server']   = 'pay.weixin.jspay';
            $data['openid'] = session("wechat_token.openid");
            $data['signtype'] = 'MD5';
            #平台币记录数据
            $data['pay_status']       = 0;
            $data['payway']           = 2;
            $data['pay_source']       = 1;
            $data['options']  = 'spend';
            $data['order_no'] = 'SP_'.date('Ymd').date ( 'His' ).sp_random_string(4);
            #平台币记录数据
            $data["game_id"]       = $game_data['id'];
            $data["game_appid"]    = $_POST["game_appid"];
            $data["game_name"]     = $game_data["game_name"];

            $data["server_id"]     = $_POST["server_id"];
            $data["server_name"]   = $_POST["server_name"];
            $data["game_player_id"]     = $_POST["role_id"];
            $data["game_player_name"]   = $_POST["role_name"];

            $data["promote_id"]    = $user['promote_id'];
            $data["promote_account"] = $user['promote_account'];
            $data["pay_order_number"]= $data['order_no'];
            $data["title"] = $_POST['props_name'];
            $data["pay_amount"]   = $_POST['amount']/100;
            $data["pay_way"] = 4;
            $data['extend']  = $_POST['trade_no'];
            $data['callbackurl']      = "http://".$_SERVER['HTTP_HOST'].U('open_game',array('game_id'=>$game_data['id']));
            $pay = new PayApi();
            $arr = $pay->other_pay($data,C('weixin'));
            if($arr['status'] != 500){
                redirect("https://pay.swiftpass.cn/pay/jspay?token_id={$arr['token_id']}&showwxtitle=1"); 
            }
        }
    }

    public function platform_coin_pay($param,$bind_ptb=''){
        if($param['sign']!=session('game_pay_sign')){
            $this->ajaxReturn(array('status'=>0,'msg'=>"非法操作！"));
        }
        #判断账号是否存在
        if (empty($param['user_id'])){
            $param['user_id'] = $param['userId'];
        }
        $user = get_user_entity($param['user_id'],false);
        if(!$user){
            $this->ajaxReturn(array('status'=>0,'msg'=>'用户不存在'));
        }
        if($bind_ptb!=null||$bind_ptb!=''){
            $money=$bind_ptb['bind_balance'];
        }else{
            $money=$user['balance'];
        }
        if($money < $param['amount']/100){
            $this->ajaxReturn(array('status'=>0,'code'=>-1,'msg'=>'余额不足'));
        }
        $code=1;
        $game_data = get_game_entity($param['game_appid']);
        if(!$game_data){
            $this->ajaxReturn(array('status'=>0,'msg'=>'游戏参数错误'));
        }
        $data_spned['user_id']          = $param["user_id"];
        $data_spned['user_account']     = $user["account"];
        $data_spned['user_nickname']    = $user["nickname"];
        $data_spned['game_id']          = $game_data["id"];
        $data_spned['game_appid']       = $game_data["game_appid"];
        $data_spned['game_name']        = $game_data["game_name"];
        $data_spned["server_id"]     = $param["server_id"];
        $data_spned["server_name"]   = $param["server_name"];
        $data_spned["game_player_id"]     = $param["role_id"];
        $data_spned["game_player_name"]   = $param["role_name"];
        $data_spned['promote_id']       = $user["promote_id"];
        $data_spned['promote_account']  = $user["promote_account"];
        $data_spned['order_number']     = $param["order_number"];
        $data_spned['pay_order_number'] = 'SP_'.date('Ymd').date ( 'His' ).sp_random_string(4);
        $data_spned['props_name']       = $param["props_name"];
        $data_spned['pay_amount']       = $param["amount"]/100;
        $data_spned['pay_time']         = NOW_TIME;
        $data_spned['pay_status']       = 1;
        $data_spned['pay_game_status']  = 0;
        $data_spned['extend']           = $param['trade_no'];
        $data_spned['pay_way']          = 0;
        $data_spned['spend_ip']         = get_client_ip();
        $shiwu = M();
        if($param['payway']=='ptb'){
            $model1=M("user","tab_");
            $shiwu->startTrans();
            $model2=M("spend","tab_");
            $koukuan=$model1->where(["id"=>$param['user_id']])->setDec("balance",$param['amount']/100);
            $balance = $model1->field('balance')->find($param['user_id']);
            if($koukuan&&$balance['balance']>=0){
                $shiwu->commit();
                $data_spned['pay_status']       = 1;
            }else{
                $shiwu->rollback();
                $this->ajaxReturn(array('status'=>0,'code'=>-1,'msg'=>'扣款失败，请重新支付'));
            }
            $spend_id = $model2->add($data_spned);
            $pay = new PayApi();
            $pay->set_ratio($data_spned['pay_order_number']);
            $pay->buyshoppoint($data_spned);//分发积分
        }else if($param['payway']=='bind_ptb'){
            // $code=2;//5.0版本绑币消费记录到spend表
            $data_spned['pay_way']          = -1;
            $model1=M("user_play","tab_");
            $shiwu->startTrans();
            $model2=M("spend","tab_");
            $map['user_id']=$param['user_id'];
            $map['game_appid']=$param['game_appid'];
            $koukuan=$model1->where($map)->setDec("bind_balance",$param['amount']/100);
            $bind_balance = $model1->field('bind_balance')->where($map)->find();
            if($koukuan&&$bind_balance['bind_balance']>=0){
                $shiwu->commit();
                $data_spned['pay_status']       = 1;
            }else{
                $shiwu->rollback();
                $this->ajaxReturn(array('status'=>0,'code'=>-1,'msg'=>'扣款失败，请重新支付'));
            }
            $spend_id = $model2->add($data_spned);
            $pay = new PayApi();
            $pay->set_ratio($data_spned['pay_order_number'],1);
        }
        $res =json_decode($this->pay_game_notify($data_spned,$code),true);
        if($res['status']=='success'||$res['msg']=='success'||$res['code']=='1009'){
            $model2->where(["id"=>$spend_id])->setField("pay_game_status",1);
            $this->record_logs($data_spned['pay_order_number'].'共通知cp 1次，已成功','INFO');
        }else{
            $this->record_logs($data_spned['pay_order_number'].'共通知cp 1次，失败','INFO');
        }
        $url = 'http://'.$_SERVER['HTTP_HOST'].'/mobile.php?s=/Game/game_pay_callback/order_number/'.$data_spned['pay_order_number'].'/game_id/'.$game_data["id"];
        $this->ajaxReturn(array('status'=>1,'msg'=>'支付成功','url'=>$url));
    }
    /**
    *日志记录
    */
    protected function record_logs($msg="",$type='ERR'){
        \Think\Log::record($msg,$type);
    }





    protected function pay_game_notify($data,$code){

        $game = new GameApi();

        $result = $game->game_pay_notify($data,$code);

        return $result;

    }



    protected function bind_spend_param($data_pt){

        $user_entity = get_user_entity($param['user_id']);

        $data_bind_spned['user_id']          = $param["user_id"];

        $data_bind_spned['user_account']     = $user_entity["account"];

        $data_bind_spned['user_nickname']    = $user_entity["nickname"];

        $data_bind_spned['game_id']          = $param["game_id"];

        $data_bind_spned['game_appid']       = $param["game_appid"];

        $data_bind_spned['game_name']        = $param["game_name"];

        $data_bind_spned["server_id"]     = $param["server_id"];
        $data_bind_spned["server_name"]   = $param["server_name"];
        $data_bind_spned["game_player_id"]     = $param["role_id"];
        $data_bind_spned["game_player_name"]   = $param["role_name"];

        $data_bind_spned['promote_id']       = $user_entity["promote_id"];

        $data_bind_spned['promote_account']  = $user_entity["promote_account"];

        $data_bind_spned['order_number']     = $param["order_number"];

        $data_bind_spned['pay_order_number'] = $param["pay_order_number"];

        $data_bind_spned['props_name']       = $param["title"];

        $data_bind_spned['pay_amount']       = $param["price"];

        $data_bind_spned['pay_time']         = NOW_TIME;

        $data_bind_spned['pay_status']       = $param["pay_status"];

        $data_bind_spned['pay_game_status']  = 0;

        $data_bind_spned['pay_way']          = 1;

        $data_bind_spned['spend_ip']         = $param["spend_ip"];

        return $data_bind_spned;

    }

    public function checkstatus(){

        // sleep(3);

        substr($_REQUEST['out_trade_no'],0,2)=="PF"?$model='deposit':$model='spend';

        $data=M($model,'tab_')->where(array('pay_order_number'=>$_REQUEST['out_trade_no']))->find();

        $config = M('config','sys_')->field('value')->where(array('name'=>'UC_SET'))->find();

        if($data['pay_status']==1){

            $url=U('Game/open_game',array('game_id'=>$data['game_id']));

            $this->ajaxReturn(array("status"=>1,'url'=>$url));

        }else{

            $this->ajaxReturn(array("status"=>0));

        }

    }

    /**

    *游戏返利

    */

    protected function set_ratio($data){
        $map['pay_order_number']=$data;
        $spend=M("Spend","tab_")->where($map)->find();
        $reb_map['game_id']=$spend['game_id'];
        $rebate=M("Rebate","tab_")->where($reb_map)->find();
        if($rebate['ratio']==0||null==$rebate){
            return false;
        }else{
            if($rebate['money']>0&&$rebate['status']==1){
                if($spend['pay_amount']>=$rebate['money']){
                    $this->compute($spend,$rebate);
                }else{
                    return false;
                }
            }else{
                $this->compute($spend,$rebate);
            }
        }
    }

    //计算返利

    protected function compute($spend,$rebate){
        $user_map['user_id']=$spend['user_id'];
        $user_map['game_id']=$spend['game_id'];            
        $bind_balance=$spend['pay_amount']*($rebate['ratio']/100);
        $spend['ratio']=$rebate['ratio'];
        $spend['ratio_amount']=$bind_balance;
        M("rebate_list","tab_")->add($this->add_rebate_list($spend));
        $re=M("UserPlay","tab_")->where($user_map)->setInc("bind_balance",$bind_balance);
        return $re;
    }

    /**

    *返利记录

    */

    protected function add_rebate_list($data){
        $add['pay_order_number']=$data['pay_order_number'];
        $add['game_id']=$data['game_id'];
        $add['game_name']=$data['game_name'];
        $add['user_id']=$data['user_id'];
        $add['pay_amount']=$data['pay_amount'];
        $add['ratio']=$data['ratio'];
        $add['ratio_amount']=$data['ratio_amount'];
        $add['promote_id']=$data['promote_id'];
        $add['promote_name']=$data['promote_account'];
        $add['create_time']=time();
        return $add;
    }
    //角色上传
    public function create_role(){
        $data = json_decode($_REQUEST['role'],true);
        $signdata['user_id']        = $data['user_id'];
        $signdata['game_appid']     = $data['game_appid'];
        $signdata['server_id']      = $data['server_id'];
        $signdata['server_name']    = $data['server_name'];
        $signdata['role_id']        = $data['role_id'];
        $signdata['role_name']      = $data['role_name'];
        $signdata['level']          = $data['level'];
        if(!empty($data)){
            $gamedata = M('GameSet','tab_')
                         ->field('game_id,game_key,game_name')
                         ->join('tab_game on tab_game.id = tab_game_set.game_id')
                         ->where(array('game_appid'=>$data['game_appid']))
                         ->find();
            $userdata = M('User','tab_')
                         ->field('account,up.id as user_play_id,game_name')
                         ->join('tab_user_play as up on tab_user.id = up.user_id')
                         ->where(array('up.game_id'=>$gamedata['game_id'],'up.user_id'=> $data['user_id']))
                         ->find();
            $game_key = $gamedata['game_key'];
            unset($signdata['sign']);
            $sign = signsortData($signdata,$game_key);
        }else{
            $this->ajaxReturn(array('code'=>1001,'msg'=>'数据不能为空'),'jsonp');
        }

        if($sign!=$data['sign']&&0){
            $this->ajaxReturn(array('code'=>1003,'msg'=>'数据签名失败'),'jsonp');
        }else{
            $roledata['user_id']            = $data['user_id'];
            $roledata['user_account']       = $userdata['account'];
            $roledata['user_play_id']       = $userdata['user_play_id'];
            $roledata['game_id']            = $gamedata['game_id'];
            $roledata['game_name']          = $gamedata['game_name'];
            $roledata['server_id']          = $data['server_id'];
            $roledata['server_name']        = $data['server_name'];
            $roledata['role_id']            = $data['role_id'];
            $roledata['role_name']          = $data['role_name'];
            $roledata['role_level']         = $data['level'];
            $roledata['play_time']          = time();
            $roledata['update_time']        = $roledata['play_time'];
            $roledata['play_ip']            = get_client_ip();
        }
        $gapi = new GameApi();
        $res = $gapi->add_user_role($roledata);
        $this->ajaxReturn(array('code'=>200,'msg'=>'角色记录成功'),'jsonp');
    }

    // 下载二维码
    public function dow_url_generate($game_id=null,$type=1){
        $url = "http://".$_SERVER['SERVER_NAME']."/media.php?s=/Down/down_file/game_id/".$game_id."/type/".$type.".html";
        $qrcode = $this->qrcode($url);
        return $qrcode;
    }
    public function qrcode($url='pc.vlcms.com',$level=3,$size=4){
        Vendor('phpqrcode.phpqrcode');
        $errorCorrectionLevel =intval($level) ;//容错级别 
        $matrixPointSize = intval($size);//生成图片大小 
        //生成二维码图片
        ob_clean();
        $object = new \QRcode();
        echo $object->png($url, false, $errorCorrectionLevel, $matrixPointSize, 2);
    }
    public function logout() {
        if(is_login_user()){
            D('User')->logout();
            session("wechat_token",null);
            $data['status'] = 1;
            echo json_encode($data);
        } else {
            $this->redirect('Game/login');
        }
    }


}

