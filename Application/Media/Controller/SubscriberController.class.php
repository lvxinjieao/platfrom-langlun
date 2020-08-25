<?php
namespace Media\Controller;
use Think\Controller;
use User\Api\MemberApi;
use Common\Api\GameApi;
use Org\ThinkSDK\ThinkOauth;
use Org\WeiXinSDK\WeiXinOauth;
use Org\WeiXinSDK\Weixin;
use Org\UcpaasSDK\Ucpaas;
use Org\XiguSDK\Xigu;
use Common\Api\PayApi;
use Org\UcenterSDK\Ucservice;
use Org\JtpaySDK\Jtpay;
use Com\Wechat;
use Com\WechatAuth;
use Think\Checkidcard;
use Admin\Model\PointTypeModel;
use Think\Model;
use Common\Model\GiftbagModel;
use Common\Model\GameModel;
use Common\Model\UserPlayModel;
use Common\Model\UserModel;
use Common\Model\PointShopRecordModel;
use Common\Model\DocumentModel;

class SubscriberController extends BaseController {

    protected function _initialize(){
        /* 读取站点配置 */
        $config = api('Config/lists');
        C($config); //添加配置
    }
    public function __construct() {
        parent::__construct();
        //个人中心 必须登录才能访问
        $autharr= array(
            'index','account','user_bind_phone','user_balance',
            'user_password','user_gift','user_collection',
            'user_order','user_address','optionBehavior','downDel'
        );
        $action_name = strtolower(ACTION_NAME);
        if(in_array($action_name, $autharr)&&!is_login()){
            redirect(U('subscriber/login'));
        }

        $map['id']=is_login();
        $map['lock_status']=1;
        $user = M('User','tab_')->field('nickname,account,balance,qq,real_name,idcard,login_time,register_way')->where($map)->find();
        $phone = substr($user['phone'],2,-2);
        $user['phone'] = str_replace($phone,'*******',$user['phone']);
        $rl = mb_substr($user['real_name'],0,1,'utf-8');
        $user['real_name']= str_replace($rl,'*',$user['real_name']);
        $idcard = substr($user['idcard'],3,-3);
        $user['idcard']=str_replace($idcard,'*********',$user['idcard']);
        
        $this->assign('user_data',$user);
        if(empty($user)&&is_login()){
            redirect(U('subscriber/login'));
        }
    }
    /** 个人中心 */
    public function index() {
        

        $pointtype = new PointTypeModel();
        $lgmap['pt.key'] = 'sign_in';
        if(is_login()){
            $lgjoin .= ' and pr.user_id = '.is_login();
            // $lgjoin .= ' and pr.create_time '.total(1,true);
        }else{
            $lgjoin .= ' and pr.user_id = '.is_login();
        }
        $loginpont = $pointtype->getUserLists($lgmap,$lgjoin,'ctime desc',1,1);
        if(empty($loginpont[0]['user_id'])){
            $issignin = 0;//今日是否签到
        }elseif(!empty($loginpont[0]['user_id'])&&$loginpont[0]['ct']==date('Y-m-d',time())){
            $issignin = 1;
        }else{
            $issignin = 0;
            if($loginpont[0]['ct']!=date("Y-m-d",strtotime("-1 day"))){
                $loginpont[0]['day'] = 0;
            }
        }
        if($loginpont[0]['day']>=7||$loginpont[0]['day']<=0||empty($loginpont[0]['day'])){
            $signday = 1;
        }else{
            $signday = $loginpont[0]['day']+1;
        }

        $addpoint = $loginpont[0]['point']+($signday-1)*$loginpont[0]['time_of_day'];//增加多少，今日签过 改变量不使用
        $this->assign('issignin',$issignin);
        $this->assign('addpoint',$addpoint);//今日
        $this->display();
    }
    //个人资料
    public function profile(){
        $res = session("user_auth");
        $res = isset($res['user_id']) ? $res['user_id'] : 0;
        if (IS_POST) {
            if($_POST['nickname']==''){
                    $this->ajaxReturn(array('status'=>-1,'msg'=>'昵称不能为空'));
            }
            if(isset($_POST['real_name']) && !empty($_POST['real_name'])){
                if(!$this->isChineseNamereturn($_POST['real_name'])){
                    $this->ajaxReturn(array('status'=>0,'msg'=>'姓名输入不正确'));
                }
            }
            if(isset($_POST['idcard']) && !empty($_POST['idcard'])){
                $idc = $this->isidcardreturn($_POST['idcard'],$_POST['real_name']);
                switch ($idc) {
                    case '-1':
                        $this->ajaxReturn(array('status' => 0, 'msg' => '请填写正确的身份证号码，字母需小写'));
                        break;
                    
                    case '-2':
                        $this->ajaxReturn(array('status' => 0, 'msg' => '身份证号码已被使用'));
                        break;
                    case '-3':
                        $this->ajaxReturn(array('status'=>0,'msg'=>'认证失败，请稍后再试'));
                        break;
                }
            }
            unset($_POST['balance']);
            $map=$_POST;
            $map['id']=$res;
            $flag = M('User','tab_')->save($map);
            if ($flag!==false) {                
                $data['msg'] = '修改成功';
                $data['status']=1;
            } else {
                $data['msg'] = '修改失败';
                $data['status']=-3;
            }
            $this->ajaxReturn($data);           
        }
    }

    //验证中文名字
    function isChineseName($name){
        if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
            if(preg_match("/^([\xe4-\xe9][\x80-\xbf]{2}){2,4}$/",$name)){
                $this->ajaxReturn(array('status'=>1,'msg'=>'中文名正确'));
            }else {
                $this->ajaxReturn(array('status'=>0,'msg'=>'中文名错误'));
            }
        }else{
            if(preg_match("/^([\xe4-\xe9][\x80-\xbf]{2}){2,4}$/",$name)){
                return true;
            }else {
                return false;
            }
        }
    }
    //验证中文名字
    function isChineseNamereturn($name){
        if(preg_match("/^([\xe4-\xe9][\x80-\xbf]{2}){2,4}$/",$name)){
            return true;
        }else {
            return false;
        }
    }

    //验证身份证号码
    function isidcard($idcard,$name){
        if (get_tool_status('age') != 0){
            $cardd=M('User','tab_')->where(array('idcard'=>$_POST['idcard']))->find();
            if($cardd){
                $this->ajaxReturn(array('status'=>-2,'msg'=>'身份证号码已被使用'));
            }
            $re = ageVerify($idcard,$name);
            switch ($re)
            {
                // case -1:
                //     $this->set_message(1068,'连接接口失败','');
                //     break;
                // case -2:
                //     $this->set_message(1068,'连接接口失败','');
                //     break;
                case 0:
                    $this->ajaxReturn(array('status'=>-1,'msg'=>'认证失败 请稍后再试'));
                    break;
                case 1://成年
                    cookie(is_login(),1,array('prefix'=>'idcard_'));
                    break;
                case 2://未成年
                    cookie(is_login(),2,array('prefix'=>'idcard_'));
                    break;
                default:
            }
        }else{
            $checkidcard = new \Think\Checkidcard();
            $invidcard=$checkidcard->checkIdentity($idcard);
            if(!$invidcard){
                $this->ajaxReturn(array('status'=>-1,'msg'=>'身份证号码填写不正确，字母需小写'));
            }
        }
        $idcardinfo = getIDCardInfo($_POST['idcard']);
        if($idcardinfo['error']==2&&$idcardinfo['isAdult']==0){
            cookie(is_login(),1,array('prefix'=>'idcard_'));
        }else{
            cookie(is_login(),2,array('prefix'=>'idcard_'));
        }
        $this->ajaxReturn(array('status'=>1,'msg'=>'身份证号码正确未被使用'));
    }
    
    function isidcardreturn($idcard,$name){
        $cardd=M('User','tab_')->where(array('idcard'=>$_POST['idcard']))->find();
        if($cardd){
            return -2;
        }
        if (get_tool_status('age') != 0){
            $re = ageVerify($idcard,$name);
            switch ($re)
            {
              
                case 0:
                    return -3;//接口问题  余额不足 或接口连接失败
                    break;
                case 1://成年
                    cookie(is_login(),1,array('prefix'=>'idcard_'));
                    break;
                case 2://未成年
                    cookie(is_login(),2,array('prefix'=>'idcard_'));
                    break;
                default:
            }
        }else{
            $checkidcard = new \Think\Checkidcard();
            $invidcard=$checkidcard->checkIdentity($idcard);
            if(!$invidcard){
                return -1;
            }
        }
        $idcardinfo = getIDCardInfo($_POST['idcard']);
        if($idcardinfo['error']==2&&$idcardinfo['isAdult']==0){
            cookie(is_login(),1,array('prefix'=>'idcard_'));
        }else{
            cookie(is_login(),2,array('prefix'=>'idcard_'));
        }
        return 1;
    }


    //账号安全
    public function account() {
        $user = session("user_auth");
        $map['id']=session('user_auth.user_id');
        $user = M('User','tab_')->where($map)->find();
        $this->assign('user',$user);
        $this->display();
    }

    //绑定手机
    public function user_bind_phone(){
        $user = session("user_auth");
        $map['id']=session('user_auth.user_id');
        $user = M('User','tab_')->where($map)->find();
        $this->assign('phone',$user['phone']);
        $this->display();
    }
    
    //绑定手机验证
    public function sendvcode() {
        if (!IS_POST) {
            echo json_encode(array('status'=>0,'msg'=>'请按正确的流程'));exit;
        }
        $verify = new \Think\Verify();
        if(!$verify->check(I('verify'),I('vid'))){
            echo json_encode(array('status'=>2,'msg'=>'*图片验证码不正确')); exit;
        }

        $phone = I('phone');
        if($_POST['jiebang']==4){
            $userp=D('User')->where(array('phone'=>$phone,'id'=>session("user_auth.user_id")))->find();
            if(!$userp){
                echo json_encode(array('status'=>0,'msg'=>'数据错误'));exit;
            }
        }else{
            $userp=D('User')->where(array('phone'=>$phone))->find();
            if($userp){
                echo json_encode(array('status'=>0,'msg'=>'手机已使用'));exit;
            }
        }
        $this->telsvcode($phone);             
    }
    

    public function user_password() {
        if(IS_POST){
            $this->change_paw();
        }
        $user = M('User','tab_')->field('register_way')->find(is_login());
        if($user['register_way']>2){
            redirect(U('Subscriber/index'));exit;
        }
        $this->display();
    }

    /** 绑定平台币 */
    public function user_balance($p=1){
        $row = 20;
        if(is_login()){
            $model = new UserPlayModel();
            $bindmap['user_id'] = is_login();
            $bindmap['bind_balance'] = array('gt',0);
            $binddata = $model->getPlayList($bindmap,"u.play_time desc",$p,$row);
        }else{
            $binddata = array();
        }
        $count = $binddata['count'];
        $this->set_page($count,$row);
        
        $user_id = session("user_auth.user_id");
        $user = get_user_entity($user_id);
        
        $this->assign('binddata',$binddata['data']);
        $this->assign('user',$user);
        $this->display();
    }

    /* 用户礼包  */
    function user_gift($p=1){
        $row = 5;
        $giftbgmodel = new GiftbagModel();
        $user = is_login();
        $allgamegift = $giftbgmodel->myGiftRecord($user,[],$p);
        $data = array_slice($allgamegift, ($p-1)*$row, $row);
        $count = count($allgamegift);
        $this->set_page($count,$row);
        $this->assign('mygift',$data);
        $this->display();
    }


    /**
    *我的收藏
    */
    public function user_collection($p=1,$type=0){
        $row = 40;
        $model = new GameModel();
        $user = is_login();
        $data1 = $model->myGameCollect($user,1,$p,[],$row,1);
        $this->assign('coll',$data1);
        $data2 = $model->myGameFoot($user,2,$p,[],$row,1);
        $this->assign('foot',$data2);
        $data3 = $model->downRecordLists(false,$user,$p,$row);
        $data3count = $model->downRecordLists(false,$user,$p,200);
        $this->assign('down',$data3);
        $this->assign('downcount',count($data3count));

        $this->assign('type',$type);

        $this->set_page($data1['count'],$row);
        $this->set_page($data2['count'],$row,'_page2');
        $this->set_page(count($data3count),$row,'_page3');
        
        $this->display();
    }

    public function optionBehavior(){
        $ids = explode(',',$_POST['ids']);
        $ids = array_unique($ids);
        if(empty($ids)) $this->ajaxReturn(array('status'=>0));
        $ids = implode(',',$ids);
        $model = new GameModel();
        $user = is_login();
        $map['id'] = array('in',$ids);
        $data = $model->optionBehavior($user,$_POST['type'],$map);
        if($data!=false){
            $this->ajaxReturn(array('status'=>1,'msg'=>'已删除'));
        }else{
            $this->ajaxReturn(array('status'=>0,'msg'=>'删除失败'));
        }
    }
    public function downDel(){
        $ids = explode(',',$_POST['ids']);
        $ids = array_unique($ids);
        if(empty($ids)) $this->ajaxReturn(array('status'=>0));
        $record_id = implode(',',$ids);
        $model = new GameModel();
        $user = is_login();
        $data = $model->downRecordDel($user,$record_id);
        if($data!=false){
            $this->ajaxReturn(array('status'=>1,'msg'=>'已删除'));
        }else{
            $this->ajaxReturn(array('status'=>0,'msg'=>'删除失败'));
        }
    }
    public function user_address($type=0){
        if(IS_POST){
            $model = new UserModel();
            $user = is_login();
            $shop_address['consignee'] = $_POST['consignee'];
            $shop_address['consignee_phone'] = $_POST['consignee_phone'];
            $shop_address['consignee_address'] = $_POST['province'].' '.$_POST['city'].' '.$_POST['district'].'-'.$_POST['detailed_address'];
            if($type==1){
                $shop_address = '';
            }
            $res = $model->updateShopAddress($user,json_encode($shop_address));
            if($res!==false){
                $this->ajaxReturn(array('status'=>1));
            }else{
                $this->ajaxReturn(array('status'=>0));
            }
        }else{
            $user = get_user_entity(is_login());
            $shop_address_info = json_decode($user['shop_address'],true);
            $shop_address= explode('-',$shop_address_info['consignee_address']);
            $this->assign('user',$user);
            $this->assign('baseinfo',$shop_address_info);
            $this->assign('address',explode(' ',$shop_address[0]));
            $this->assign('detail',$shop_address[1]);
            $this->display();
        }
    }
    
    function checkaccount($account){
        $user = get_user_entity($account,true);
        if(!$user){
            $this->ajaxReturn(array('status'=>0,'msg'=>'用户不存在','code'=>0));exit;
        }else{
            if($user['phone']==''){
                $this->ajaxReturn(array('status'=>0,'msg'=>'该账号不是手机注册用户','code'=>1));exit;
            }else{
                $this->ajaxReturn(array('status'=>1,'msg'=>'用户存在','code'=>1));exit;
            }
        }
    }

    function checkphoneexsite($phone, $type = '')
    {
        $userp = M('User','tab_')->where(array('phone' => $phone))->find();
        if ($userp) {
            if ($userp['id'] != is_login()) {
                $this->ajaxReturn(array('status' => 1, 'msg' => '此手机号不是您已绑定的手机号', 'code' => 0));//status = 1存在手机号码 code = 0 已登录用户未绑定该手机号码   用于解绑
                exit;
            }
            $this->ajaxReturn(array('status' => 1, 'msg' => '手机存在', 'code' => 1));
            exit;
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => '手机不存在', 'code' => 0));
            exit;
        }
    }

    function forget2(){
        if($_POST['account']==''){
            $this->error('非法操作');exit;
        }
        if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
            session('forgot2','no');

            $return = $this->check_tel_code($_POST['account'],'','',$_POST['code'],1);
            if(!$return['status']){
                $return['msg'] = $return['msg'] ? $return['msg'] :'短信验证码不正确';
                $this->ajaxReturn($return);
            }

            $ppost['api_key'] = C('AUTO_VERIFY_ADMIN');
            $ppost['response'] = $_POST['luotest_response'];
            $o = "";
            foreach ( $ppost as $k => $v )
            {
                $o.= "$k=" . urlencode( $v ). "&" ;
            }
            $post_data = substr($o,0,-1);
            $check_verify = json_decode(request_post('https://captcha.luosimao.com/api/site_verify',$post_data),true);
            if($check_verify['res']!='success'){
                $this->ajaxReturn(array('status'=>0,'msg'=>'人机验证失败'));
            }

            session('forgot2','ok');
            $this->ajaxReturn(array('status'=>1,'msg'=>'验证成功'));
        }
        if(session('forgot2')!='ok'){
            $this->error('非法操作');exit;
        }
        $data=D('User')->field('id,account')->where(array('phone'=>$_POST['account']))->find();
        if(empty($data)){
            $this->ajaxReturn(array('status'=>0,'msg'=>'用户不存在'));
        }
        $this->assign('data',$data);
        $this->display();
    }


    function forget3(){
        if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
            session('forgot2','no');
            cookie('account',$_POST['account']);
            if($_POST['account']==''){
                $this->ajaxreturn('非法操作');exit;
            }
            if(!preg_match('/^[a-zA-Z0-9]{6,15}$/', $_POST['password'])){
                $this->ajaxReturn(['status'=>0,'msg'=>'密码不能含有特殊字符']);
                exit;
            }
            $res = $this->update_paw($_POST['account'],$_POST['password']);
                if($res!==false){
                $this->error('修改成功');
            }else{
                $this->error('修改失败');
            }
        }
        $account = cookie('account');
        if($account==''){
           redirect(U('login'));
        }
        cookie('account',null);
        $this->display();
    }
    public function user_argeement(){
        $type = 'ur';
        if (empty($type)) {return;}
        $name = 'wap_'.$type;
        $article = A('Article');
        $category = $article->category($name);
        $news = M("Document")->field("d.id")->table("__DOCUMENT__ as d")
        ->join("__CATEGORY__ as c on(c.id=d.category_id and c.name='$name')",'right')
        ->where("d.status>0 and d.display=1")->find();
        $doc = new DocumentModel();
        $data = $doc->articleDetail($news['id']);
        $this->assign('data',$data);
        $this->assign('category',$category);
        $this->assign('isapp',$_REQUEST['isapp']);
        $this->display();
    }

    protected function getE($num="") {

        switch($num) {

            case -1:  $error = '用户名长度必须在6-30个字符以内！'; break;

            case -2:  $error = '用户名被禁止注册！'; break;

            case -3:  $error = '用户名被占用！'; break;

            case -4:  $error = '密码长度不合法'; break;

            case -5:  $error = '邮箱格式不正确！'; break;

            case -6:  $error = '邮箱长度必须在1-32个字符之间！'; break;

            case -7:  $error = '邮箱被禁止注册！'; break;

            case -8:  $error = '邮箱被占用！'; break;

            case -9:  $error = '手机格式不正确！'; break;

            case -10: $error = '手机被禁止注册！'; break;

            case -11: $error = '手机号被占用！'; break;

            case -20: $error = '请填写正确的姓名';break;

            default:  $error = '未知错误';

        }

        return $error;

    }

    /**
     * 登录
     */
    public function login($username='',$password='',$type="",$gid='') {
        if($gid){
            $gid = simple_decode($gid);
        }
        if(IS_POST){
            if($type == "phone" && !preg_match("/^1[3456789][0-9]{9}$/",$username)){
                $this->ajaxReturn(array("status"=>0,"msg"=>"请输入正确的手机号码"));exit;
            }
            $user = new MemberApi();
            if($_POST['jzmm']){
                setcookie('media_account',$username,time()+3600*10000,$_SERVER["HTTP_HOST"]);
                setcookie('media_pas',$password,time()-1,$_SERVER["HTTP_HOST"]);
                // setcookie('media_pas',$password,time()+3600*10000,$_SERVER["HTTP_HOST"]);
            }else{
                setcookie('media_account',$username,time()-1,$_SERVER["HTTP_HOST"]);
                setcookie('media_pas',$password,time()-1,$_SERVER["HTTP_HOST"]);
            }
            //本站登录
            $result = $user->login($username,$password,'',$gid);

            switch ($result) {
                case -1:#账号不存在
                    if($type=="phone"){
                        $this->telsvcode($username,1,0,$type);
                    }else{
                        $this->ajaxReturn(array("status"=>0,"msg"=>"账号不存在"));
                    }
                    break;
                case -2:
                    $this->ajaxReturn(array("status"=>0,"msg"=>"密码错误"));
                    break;
                case -4:
                    $this->ajaxReturn(array("status"=>0,"msg"=>"用户被锁定"));
                    break;
                case -5:
                    $this->ajaxReturn(array("status"=>0,"msg"=>"游戏不存在"));
                    break;
                default:
                    if($gid){
                        $GameApi = new GameApi();
                        $sdk2cplogin = $GameApi->sdk2cplogin($result,$gid);
                    }
                    if(session('pro_gid')){
                        $url = U('Game/open_game',array('game_id'=>session('pro_gid')));
                    }else{
                        $url = U('Subscriber/index');
                    }
                    session('pro_gid',null);
                    session('pro_pid',null);
                    $this->ajaxReturn(array("status"=>1,"msg"=>"登录成功","url"=>$url,'data'=>$sdk2cplogin));
                    break;
            }

        }else{
            if ($this->is_login()){
                $this->redirect('Index/index');
            }
            $map1['name']='PC_QQ_GROUP';
            $info1 = M('Config')->field('value')->where($map1)->find();
            $map['name']='PC_QQ_GROUP_KEY';
            $info = M('Config')->field('value')->where($map)->find();
            $map3['name']='PC_SET_DOMAIM';
            $info3 = M('Config')->field('value')->where($map3)->find();
            $this->assign('info3',$info3);
            $this->assign('info',$info);
            $this->assign('info1',$info1);
            $this->display();
        }
    }

    //注册第一步
    public function registerf(){
        if(IS_POST){
            if(C("USER_ALLOW_REGISTER")==1){
                $type=$_POST['type'];

                if($type=='account'){
                    if(empty($_POST['account'])){
                        return $this->ajaxReturn(array('status'=>0,'msg'=>'账号不能为空'));exit;
                    } else if(strlen($_POST['account'])>15||strlen($_POST['account'])<6){
                        return $this->ajaxReturn(array('status'=>0,'msg'=>'用户名长度在6~15个字符'));
                    }else if(!preg_match('/^[a-zA-Z0-9]{6,15}$/', $_POST['account'])){
                        return $this->ajaxReturn(array('status'=>0,'msg'=>'用户名包含特殊字符'));exit;
                    }

                    $ppost['api_key'] = C('AUTO_VERIFY_ADMIN');
                    $ppost['response'] = $_POST['luotest_response'];
                    $o = "";
                    foreach ( $ppost as $k => $v )
                    {
                        $o.= "$k=" . urlencode( $v ). "&" ;
                    }
//                    $post_data = substr($o,0,-1);
//                    $check_verify = json_decode(request_post('https://captcha.luosimao.com/api/site_verify',$post_data),true);
//                    if($check_verify['res']!='success'){
//                        return $this->ajaxReturn(array('status'=>0,'msg'=>'人机验证失败'));exit;
//                    }

                    $registerverify = md5($_POST['verify'].time());
                    session('registerverify',$registerverify);
                    return $this->ajaxReturn(array('status'=>1,'msg'=>'验证通过','registerverify'=>$registerverify));exit;

                }else{
                    if(empty($_POST['account'])){
                        return $this->ajaxReturn(array('status'=>0,'msg'=>'手机号不能为空'));exit;
                    }else if(!preg_match('/^1[3456789]\d{9}$/', $_POST['account'])){
                        return $this->ajaxReturn(array('status'=>0,'msg'=>'手机格式不正确！'));exit;
                    }



                    $return = $this->check_tel_code($_POST['account'],'','',$_POST['verify'],1);
                    if($return['status']){
                        $registerverify = md5($_POST['verify'].time());
                        session('registerverify',$registerverify);
                        $return['registerverify'] = $registerverify;
                    }else{
                        return $this->ajaxReturn($return);exit;
                    }

                    $ppost['api_key'] = C('AUTO_VERIFY_ADMIN');
                    $ppost['response'] = $_POST['luotest_response'];
                    $o = "";
                    foreach ( $ppost as $k => $v )
                    {
                        $o.= "$k=" . urlencode( $v ). "&" ;
                    }
//                    $post_data = substr($o,0,-1);
//                    $check_verify = json_decode(request_post('https://captcha.luosimao.com/api/site_verify',$post_data),true);
//                    if($check_verify['res']!='success'){
//                        return $this->ajaxReturn(array('status'=>0,'msg'=>'人机验证失败'));exit;
//                    }

                    return $this->ajaxReturn($return);exit;

                }
            }else{
                return $this->ajaxReturn(array('status'=>0,'msg'=>'未开放注册！！'));
            }
        }else{
            if ($this->is_login()){
                $this->redirect('Index/index');
            }
            $this->display();
        }
    }
    //注册第二步
    public function register2(){
        if($_POST['registerverify']!=session('registerverify')){
            redirect(U('Subscriber/registerf'));
        }else{
            $zhuceauth = C('REAL_NAME_REGISTER');
            $this->assign('open_name_auth',$zhuceauth);
            $this->assign('data',$_POST);
            $this->display();
        }
    }
    //注册第三步
    public function register3(){
        if($_POST['registerverify']!=session('registerverify')){
            redirect(U('Subscriber/registerf'));
        }else{
            $this->assign('data',$_POST);

            $this->display();
        }
    }
    public function ykregister(){
        $this->ajaxReturn(array("status"=>0,"msg"=>"PC站游客通道已关闭"));
        // $this->register();
    }
    //注册第四步
    public function register(){
        if(IS_POST){
            if(C("USER_ALLOW_REGISTER")==1){
                $type=$_POST['type'];
                $open_name_auth = C('REAL_NAME_REGISTER');

                if($open_name_auth&&$type!='yk'){
                    if($_POST['real_name']==''){
                        $this->ajaxReturn(array('status' => 0, 'msg' => '未填写真实姓名'));
                    }elseif($_POST['idcard']==''){
                        $this->ajaxReturn(array('status' => 0, 'msg' => '未填写身份证号码'));
                    }
                    $rn = $this->isChineseNamereturn($_POST['real_name']);
                    if(!$rn){
                        $this->ajaxReturn(array('status' => 0, 'msg' => '请填写真实姓名'));
                    }
                    $idc = $this->isidcardreturn($_POST['idcard'],$_POST['real_name']);
                    switch ($idc) {
                        case '-1':
                            $this->ajaxReturn(array('status' => 0, 'msg' => '请填写正确的身份证号码，字母需小写'));
                            break;

                        case '-2':
                            $this->ajaxReturn(array('status' => 0, 'msg' => '身份证号码已被使用'));
                            break;
                        case '-3':
                            $this->ajaxReturn(array('status'=>0,'msg'=>'认证失败，请稍后再试'));
                            break;
                    }
                }
                if($type!='yk'){

                    if($type=='account'){

                        if(empty($_POST['account']) || empty($_POST['password'])){
                            return $this->ajaxReturn(array('status'=>0,'msg'=>'账号或密码不能为空'));exit;
                        }else if(strlen($_POST['account'])>15||strlen($_POST['account'])<6){
                            return $this->ajaxReturn(array('status'=>0,'msg'=>'用户名长度在6~15个字符'));
                        }
                        if(!preg_match('/^[a-zA-Z0-9]{6,15}$/', $_POST['account'])){
                            return $this->ajaxReturn(array('status'=>0,'msg'=>'用户名包含特殊字符'));exit;
                        }

                        if($_POST['isrenji'] == 1){
                            $ppost['api_key'] = C('AUTO_VERIFY_ADMIN');
                            $ppost['response'] = $_POST['luotest_response'];
                            $o = "";
                            foreach ( $ppost as $k => $v )
                            {
                                $o.= "$k=" . urlencode( $v ). "&" ;
                            }
                            $post_data = substr($o,0,-1);
                            $check_verify = json_decode(request_post('https://captcha.luosimao.com/api/site_verify',$post_data),true);
                            if($check_verify['res']!='success'){
                                return $this->ajaxReturn(array('status'=>0,'msg'=>'人机验证失败'));exit;
                            }
                        }
                    }else{
                        //$return = $this->check_tel_code($_POST['account'],'','',session('verify'),1);//验证手机验证码

                        $first =  substr( $_POST['account'], 0, 1 );
                        if(preg_match('/^[1a-zA-z]$/', $first)){
                            if(!preg_match('/^1[3456789]\d{9}$/', $_POST['account'])){
                                return $this->ajaxReturn(array('status'=>0,'msg'=>'手机格式不正确！'));exit;
                            }
                        }

                    }

                }


                //平台注册
                if(session('union_host')!=''){
                    $_REQUEST['p_id']=session('union_host')['union_id'];//判断是否联盟站域名
                    $data['is_union']=1;
                }else{
                    $_REQUEST['p_id'] = session('pro_pid');
                }
                $_REQUEST['g_id'] = session('pro_gid');
                $user = new MemberApi();
                $data['account']  = $_POST['account'];
                $data['password'] = $_POST['password'];
                $data['nickname'] = $_POST['account'];
                $data['phone']    = $type=='phone'?$_POST['account']:$_POST['phone'];
                $data['real_name']        = $_POST['real_name'];
                $data['idcard']        = $_POST['idcard'];
                $data['promote_id']        = $_REQUEST['p_id']?:0;
                $data['promote_account']   = get_promote_name($_REQUEST['p_id']);
                $data['parent_id']        = get_fu_id($_REQUEST['p_id']);
                $data['parent_id']        = get_fu_id($_REQUEST['p_id']);
                $data['parent_name']        = get_parent_name($_REQUEST['p_id']);
                $data['fgame_id']        = $_REQUEST['g_id'];
                $data['fgame_name']        = get_game_name($data['fgame_id']);
                $data['register_time']        = time();
                $data['third_login_type']  = $type == "phone"?1:0;
                if($type=='phone'){
                    $data['register_way'] = 2;
                }elseif($type=='account'){
                    $data['register_way'] = 1;
                }else{
                    $data['register_way'] = 0;
                }
                $result = $user->register($data);
                if($type=="account"||$type='phone'){
                    switch ($result) {
                        case -3:
                            $return=array("status"=>-3,"msg"=>"账号已存在");
                            break;
                        case 0:
                            $return=array("status"=>0,"msg"=>"注册失败");
                            break;
                        default:
                            if(session('pro_gid')){
                                $ggurl = U('Game/open_game',array('game_id'=>session('pro_gid')));
                            }else{
                                $ggurl = U('Subscriber/index');
                            }
                            session('pro_gid',null);
                            session('pro_pid',null);
                            if($data['fgame_id']){
                                $GameApi = new GameApi();
                                $sdk2cplogin = $GameApi->sdk2cplogin($result,$data['fgame_id']);
                            }
                            $return=array("status"=>1,"msg"=>"注册成功","url"=>$ggurl,'data'=>$sdk2cplogin);

                            if ($type == "phone"){
                                $pointtype = new PointTypeModel();
                                $bindaddpoint = $pointtype->userGetPoint(is_login(),'bind_phone',$data['account']);
                            }
                            break;
                    }
                    return $this->ajaxReturn($return);exit;
                }

            }else{
                return $this->ajaxReturn(array('status'=>0,'msg'=>'未开放注册！！'));
            }
        }else{
            $map1['name']='PC_QQ_GROUP';
            $info1 = M('Config')->field('value')->where($map1)->find();
            $map['name']='PC_QQ_GROUP_KEY';
            $info = M('Config')->field('value')->where($map)->find();
            $map3['name']='PC_SET_DOMAIM';
            $info3 = M('Config')->field('value')->where($map3)->find();
            $this->assign('info3',$info3);
            $this->assign('info',$info);
            $this->assign('info1',$info1);
            $this->display();
        }
    }

    /**
    *忘记密码
    */
    public function update_paw($username,$password,$type=1){
        if($username==''){
            $this->error('非法操作');exit;
        }
        $map['account'] = $username;
        $user_data = M("user","tab_")->where($map)->find();
        if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'&&$type==1){
            if(empty($user_data)){
                $this->ajaxReturn(array('status'=>0,'msg'=>'用户不存在'));
            }
            $user = new MemberApi();
            $result = $user->updatePassword($user_data['id'],$password);
            if($result!==false){
                $this->ajaxReturn(array('status'=>1,'msg'=>'修改成功'));
            }else{
                $this->ajaxReturn(array('status'=>0,'msg'=>'修改失败'));
            }
        }else{
            if(empty($user_data)){
                return -1;
            }
            $user = new MemberApi();
            $result = $user->updatePassword($user_data['id'],$password);    
            return $result;
        }
    }

    /**
    *修改密码
    */
    private function change_paw(){
        if(empty($_POST['oldpwd'])){
            return $this->ajaxReturn(array('status'=>0,'msg'=>'原密码不能为空'));exit;
        }else if(strlen($_POST['newpwd'])>20||strlen($_POST['newpwd'])<6){
            return $this->ajaxReturn(array('status'=>0,'msg'=>'新密码长度在6~15个字符'));
        }elseif(!preg_match('/^[a-zA-Z0-9_\.]+$/',$_POST['newpwd'])){
            return $this->ajaxReturn(array('status'=>0,'msg'=>'新密码由字母或数字组成'));
        }

        $user_id = is_login();
        $data['id']=$user_id;
        $data['old_password']=$_POST['oldpwd'];
        $data['password']=$_POST['newpwd'];
        $user = new MemberApi();
        $result = $user->updateUser($data);
        if($result==-2){
            return $this->ajaxReturn(array('status'=>-2,'msg'=>'原密码错误'));
        }elseif($result!==false){
            if($result==false){
                return $this->ajaxReturn(array('status'=>-1,'msg'=>'新密码和原密码不能一致'));
            }
            session('user_auth', null);
            session('user_auth_sign', null);
            session("wechat_token", null);

            return $this->ajaxReturn(array('status'=>1,'msg'=>'修改成功！'));
        }else{
            return $this->ajaxReturn(array('status'=>-1,'msg'=>'修改失败！'));
        }
    }

    /**
     * [只有注册用户才能发送验证码]
     * @param null $phone
     * @param int $delay
     * @param int $way
     * @param string $type
     * @author 幽灵[syt]
     */
    public function account_telsvcode($phone = null, $delay = 10, $way = 1, $type = "phone"){
        $map['phone'] = $phone;
        $data = M('User','tab_')->where($map)->getField('id');
        if ($data){
            $this->telsvcode($phone, $delay = 10, $way = 1, $type = "phone");
        }else{
            $this->ajaxReturn(array('status'=>0,'msg'=>'该手机号未绑定账户！'));
        }
    }

    /**
     * [手机号不被使用才可以]
     * @param null $phone
     * @param int $delay
     * @param int $way
     * @param string $type
     * @author 幽灵[syt]
     */
    public function not_account_telsvode($phone = null, $delay = 10, $way = 1, $type = "phone"){
        $map['phone|account'] = $phone;
        $data = M('User','tab_')->where($map)->getField('id');
        if (!$data){
            $this->telsvcode($phone, $delay = 10, $way = 1, $type = "phone");
        }else{
            $this->ajaxReturn(array('status'=>0,'msg'=>'该手机号已被使用！'));
        }
    }

    // 发送手机安全码
    public function telsvcode($phone = null, $delay = 10, $way = 1, $type = "phone")
    {
        /// 产生手机安全码并发送到手机且存到session
        $rand = rand(100000, 999999);
        $param = $rand . "," . $delay;
        if(session('send_code_time') && (time() - session('send_code_time')) <60){
            echo json_encode(array('status' => 0, 'msg' => '请一分钟后再次尝试'));exit;
        }
        if(get_tool_status("sms_set")){
            checksendcode($phone,C('sms_set.limit'));
            $xigu = new Xigu(C('sms_set.smtp'));
            $result = json_decode($xigu->sendSM(C('sms_set.smtp'),$phone,C('sms_set.smtp_password'),$param),true);
            if ($result['send_status'] != '000000') {
               echo json_encode(array('status' => 0, 'msg' => '发送失败，请重新获取'));
            }
        } elseif (get_tool_status("alidayu")) {
            checksendcode($phone, C('alidayu.limit'));
            $xigu = new Xigu('alidayu');
            $result = $xigu->alidayu_send($phone, $rand, $delay);
            $result['send_time'] = time();
            if ($result == false) {
                echo json_encode(array('status' => 0, 'msg' => '发送失败，请重新获取'));
                exit;
            }
        } elseif (get_tool_status('jiguang')) {
            checksendcode($phone, C('jiguang.limit'));
            $xigu = new Xigu('jiguang');
            $result = $xigu->jiguang($phone, $rand, $delay);
            $result['send_time'] = time();
            if ($result == false) {
                echo json_encode(array('status' => 0, 'msg' => '发送失败，请重新获取'));
                exit;
            }
        } elseif (get_tool_status('alidayunew')) {
            checksendcode($phone, C('alidayunew.limit'));
            $xigu = new Xigu('alidayunew');
            $result = $xigu->alidayunew_send($phone, $rand, $delay);
            $result['send_time'] = time();
            if ($result == false) {
                echo json_encode(array('status' => 0, 'msg' => '发送失败，请重新获取'));
                exit;
            }
        } elseif (get_tool_status('alidayumsg')) {
            checksendcode($phone, C('alidayumsg.limit'));
            $xigu = new Xigu('alidayumsg');
            $result = $xigu->alidayumsg_send($phone, $rand, $delay);
            $result['send_time'] = time();
            if ($result == false) {
                echo json_encode(array('status' => 0, 'msg' => '发送失败，请重新获取'));
                exit;
            }
        } else {
            echo json_encode(array('status' => 0, 'msg' => '没有配置短信发送'));
            exit;
        }
        // 存储短信发送记录信息
        $result['send_status'] = '000000';
        $result['phone'] = $phone;
        $result['create_time'] = time();
        $result['pid'] = 0;
        $result['create_ip'] = get_client_ip();
        $r = M('Short_message')->add($result);
        // $telsvcode['code'] = $rand;
        $telsvcode['phone'] = $phone;
        $telsvcode['time'] = time();
        $telsvcode['delay'] = $delay;
        $telsvcode['code'] = $rand;
        session('telsvcode', $telsvcode);
        session('send_code_time',time());
        unset($telsvcode['code']);
        if ($way == 0) {
            echo json_encode(array(
                    'status' => 1,
                    'msg' => "注册成功！请在" . $delay . "分钟内完成<br/>验证码已经发送到 $phone",
                    "type" => $type,
                    'data' => $telsvcode)
            );
        } else if ($way == 1) {
            echo json_encode(array('status' => 1, 'msg' => '验证码已发送', "type" => $type, 'data' => $telsvcode));
        } else if ($way == 2) {
            echo json_encode(array('status' => 1, 'msg' => "请输入验证码，验证码已经发送到 $phone", "type" => $type, 'data' => $telsvcode));
        }
    }
    

    /**
     *短信验证
     */

    public function check_tel_code($account = '', $password = '', $type = '', $verify = '', $way = '', $g_id = '', $g_name = '', $p_id = '', $unsetcode = 1)
    {
        $telcode = session('telsvcode');
        if (!$telcode) {
            if(ACTION_NAME=='registerf'||ACTION_NAME=='forget2'){
                return array('status' => 0, 'msg' => '短信验证码无效，请重新获取');
            }
            echo json_encode(array('status' => 0, 'msg' => '短信验证码无效，请重新获取'));
            exit;
        }
        if ($account != $telcode['phone']) {
            if(ACTION_NAME=='registerf'||ACTION_NAME=='forget2'){
                return array('status' => 0, 'msg' => '短信验证码无效，请重新获取');
            }
            echo json_encode(array('status' => 0, 'msg' => '短信验证码无效，请重新获取'));//发送验证码后手机号不允许修改
            exit;
        }
        $time = (time() - $telcode['time']) / 60;
        if ($time > $telcode['delay']) {
            session('telsvcode', null);
            unset($telcode);
            if(ACTION_NAME=='registerf'||ACTION_NAME=='forget2'){
                return array('status' => 0, 'msg' => '时间超时,请重新获取短信验证码');
            }
            echo json_encode(array('status' => 0, 'msg' => '时间超时,请重新获取短信验证码'));
            exit;
        }
        if ($telcode['code'] == $verify) {
            //unsetcode 注销
            if($unsetcode==1){
                session('telsvcode', null);//使用后销毁
            }
            switch ($way) {
                case 0:#注册成功
                    $result = $this->register($account, $password, $type, $g_id, $g_name, $p_id, $p_account, $way);
                    if ($result) {
                        $url = U("Subscriber/index");
                        $this->ajaxReturn(array("status" => 1, "msg" => "注册成功", "url" => $url));
                    } else {
                        $this->ajaxReturn(array("status" => 0, "msg" => "注册失败", "url" => $url));
                    }
                    break;
                case 1:
                    if(ACTION_NAME=='register'||ACTION_NAME=='registerf'||ACTION_NAME=='forget2'){
                        return array('status' => 1, 'msg' => '成功');
                    }
                    echo json_encode(array('status' => 1, 'msg' => '成功'));exit;
                    break;
                case 2:
                    $phone = $account;
                    $userp = D('User')->where(array('phone' => $phone))->find();
                    $binddata = D('User')->where(array('id' => is_login_user()))->setField('phone', $phone);
                    if ($binddata !== false) {
                        $this->ajaxReturn(array("status" => 1, "msg" => "绑定成功"));
                    } else {
                        $this->ajaxReturn(array("status" => 0, "msg" => "绑定失败"));
                    }
                    break;
                case 3:
                    $pointtype = new PointTypeModel();
                    $phone = $account;
                    $userp = D('User')->where(array('phone' => $phone))->find();
                    $pointtype->startTrans();
                    $binddata = D('User')->where(array('id' => is_login_user()))->setField('phone', $phone);
                    if ($binddata !== false) {
                        $bindaddpoint = $pointtype->userGetPoint(is_login(),'bind_phone',$phone);
                        if($bindaddpoint['status']==1){
                            $pointtype->commit();
                            $this->ajaxReturn(array("status" => 1, "msg" => "绑定成功",'firstbid'=>$bindaddpoint['point']));
                        }elseif($bindaddpoint==-100){//不是首绑
                            $pointtype->commit();
                            $this->ajaxReturn(array("status" => 1, "msg" => "绑定成功",'firstbid'=>0));
                        }else{
                            $pointtype->rollback();
                            $this->ajaxReturn(array("status" => 0, "msg" => "绑定失败"));
                        }
                    }  else {
                        $pointtype->rollback();
                        $this->ajaxReturn(array("status" => 0, "msg" => "绑定失败"));
                    }
                    break;
                case 4://解绑
                    $phone = $account;
                    $binddata = D('User')->where(array('id' => is_login_user()))->setField('phone', '');
                    if ($binddata !== false) {
                        $this->ajaxReturn(array("status" => 1, "msg" => "解绑成功"));
                    } else {
                        $this->ajaxReturn(array("status" => 0, "msg" => "解绑失败"));
                    }
                    break;
                default:
                    $result = $this->update_paw($account, $password,1);
                    if ($result>0) {
                        $url = U("Subscriber/index");
                        $this->ajaxReturn(array("status" => 1, "msg" => "修改成功", "url" => $url));
                    } else {
                        $msg = $result != -3 ? "手机未绑定" : "修改失败";
                        $this->ajaxReturn(array("status" => 0, "msg" => $msg,));
                    }
                    break;
            }
        } else {
            if(ACTION_NAME=='registerf'||ACTION_NAME=='forget2'){
                return array('status' => 0, 'msg' => '短信验证码不正确，请重新输入');
            }
            echo json_encode(array('status' => 0, 'msg' => '短信验证码不正确，请重新输入'));exit;
        }
    }


    /** * 第三方登陆 * */
    public function thirdlogin($type = null)
    {
        empty($type) && $this->error('参数错误');
        //加载ThinkOauth类并实例化一个对象
        $sns = ThinkOauth::getInstance($type);
        if (!empty($sns)) {
            if ($type == "weixin") {
                if (is_weixin()) {
                    $this->wechat_login(1);
                } else {
                    $this->wechat_qrcode_login(1);
                }
            }else {
                //跳转到授权页面
                redirect($sns->getRequestCodeURL());
            }
        }
    }
    /** * 回调函数 */
    public function callback($type="", $code =""){
        if($_REQUEST['sta']!=''&&$type!='baidu'){
            $re_uri = 'http://'.$_REQUEST['sta'].U('callback',array('type'=>$type,'code'=>$code,'pid'=>$_REQUEST['pid'],'gid'=>$_REQUEST['gid']));
            redirect($re_uri);
        }
        if(empty($type)||empty($code)){

            $this->error('参数错误',U("index"));

        }
        //加载ThinkOauth类并实例化一个对象
        $sns  = ThinkOauth::getInstance($type);
        $token = $sns->getAccessToken($code , $extend);
        //获取当前登录用户信息
        if(is_array($token)){
            if ($type=='qq') {
                $user_info = A('Type','Event')->qq($token);
                $regway = 4;
                $data['headpic']=$user_info['headpic'];
                $data['head_icon']=$data['headpic'];
            }else {
                //微信 不使用
                $user_info = $sns->getUserInfo($token['access_token'],$token['openid']);
                $user_info = json_decode($user_info,true);
                $regway = 3;
                $data['headpic']=$user_info['headimgurl'];
                $data['head_icon']=$data['headpic'];
            }
            switch ($type) {
                case 'qq':
                    $prefix="QQ_";
                    break;
                case 'wx':
                    $prefix="WX_";
                    break;
                
            }
            $data['account']  = $prefix.date ( 's' ).sp_random_string(6);
            $data['nickname'] = $user_info['nickname'];
            $data['openid']   = $user_info['openid'];
            $data['promote_id'] = $_REQUEST['pid']?:0;
            $data['promote_account'] = get_promote_name($_REQUEST['pid']);
            $data['parent_id']        = get_fu_id($_REQUEST['pid']);
            $data['third_login_type'] = $regway;
            $data['register_way'] = $regway;
            if(get_game_name($_REQUEST['gid'])){
                $data['fgame_id']  = $_REQUEST['gid'];
                $data['fgame_name']  = get_game_name($_REQUEST['gid']);
            }
            
            $user = new MemberApi();
            $res = $user->third_login($data);
            if($res){
                session("wechat_token",null);
                session(array('expire' => $token['expires_in']));
                $token['headimgurl'] = $data['headpic'];
                session("wechat_token", $token);
                session('nickname',$data['nickname']);
                                
                if ($_REQUEST['gid']) {
                    $ggurl="http://".$_SERVER['HTTP_HOST'].U('Game/open_game',array('game_id'=>$_REQUEST['gid']));
                    Header("Location: $ggurl"); exit;
                }else{
                    $this->redirect("Subscriber/index");
                }
            }
            else{

                $data['info']=$up->getError();

                $data['status']=0;
            }
        }
    }

    public function trdLogin(){
        //用于第三方联盟跳转
        //暂时百度使用
        $data= array_map('urldecode', $_REQUEST);
        $bdsign = $data['bdsign'];
        unset($data['bdsign']);
        ksort($data);
        $bdssign = md5(http_build_query($data).C('THINK_SDK_WEIBO.APP_SECRET'));
        if($bdssign!=$bdsign){
            exit('第三方数据错误');
        }
        $user = new MemberApi();
        $res = $user->third_login($data);
        if($res){
            $token['headimgurl'] = $data['headpic'];
            if ($data['fgame_id']) {
                $ggurl="http://".$_SERVER['HTTP_HOST'].U('Game/open_game',array('game_id'=>$data['fgame_id']));
                Header("Location: $ggurl"); exit;
            }else{
                $this->redirect("Index/index");
            }
        }
    }

    /** 用户协议 */
    public function agreement() {
        $category=D('category')->where(array('name'=>'wap_ur'))->find();
        $docu=D('document')->where(array('category_id'=>$category['id'],'status'=>1))->find();
        $document_article=D('document_article')->where(array('id'=>$docu['id']))->find();
        $this->assign('article',$document_article['content']);
        $this->display();
    }

    public function isLogin(){
        $data = $this->is_login();
        if($data){
            $this->ajaxReturn(array("status"=>1));
        }else{
            $this->ajaxReturn(array("status"=>0));
        }
    }

    /**
    * 退出
    */
    public function logout() {
        unset($_SESSION['url']);
        if(is_login_user()){
            D('User')->logout();
            session("wechat_token",null);
            $data['url'] = U("login");
            echo json_encode($data);
        } else {
            $this->redirect('login');
        }
    }


    //验证码
    public function verify($vid = '')
    {
        $config = array(
            'seKey' => 'ThinkPHP.CN',   //验证码加密密钥
            'fontSize' => 16,              // 验证码字体大小(px)
            'imageH' => 42,               // 验证码图片高度
            'imageW' => 107,               // 验证码图片宽度
            'length' => 4,               // 验证码位数
            'fontttf' => '4.ttf',              // 验证码字体，不设置随机获取
        );
        ob_clean();
        $verify = new \Think\Verify($config);
        $verify->entry($vid);
    }

    public function qrcode($level=3,$size=4,$url=""){
        if(strpos($url, 'mediawide.php')){
            $url = str_replace('mediawide.php', 'mobile.php', $url);
        }
        Vendor('phpqrcode.phpqrcode');
        $errorCorrectionLevel =intval($level) ;//容错级别
        $matrixPointSize = intval($size);//生成图片大小
        //生成二维码图片
        $object = new \QRcode();
        echo $object->png(base64_decode(base64_decode($url)), false, $errorCorrectionLevel, $matrixPointSize, 2);
    }

    //金猪支付
    public function user_recharge_pig() {
        $this->assign('data',$_GET);
        $this->assign('money',$_GET['fee']);
        $this->display();
    }


    /**
     * [仅供支付成功支付回调使用]
     * @author 幽灵[syt]
     */
    public function user_recharge(){
        $this->redirect('Subscriber/index');
    }
    public function pay(){
        $this->display();
    }
    public function payStatus($out_trade_no){
        $map['pay_order_number'] = $out_trade_no;
        // $map['user_id'] = is_login();
        $res = M('deposit','tab_')->field('user_account,pay_order_number,pay_amount,pay_status,pay_way')->where($map)->find();
        if(empty($res)){
            redirect(U('Subscriber/pay'));
        }
        $this->assign('data',$res);
        if($res['pay_status']==1){
            $this->display('pay_ok');
        }else{
            $this->display('pay_fail');
        }
    }
    function pay_fail(){
        redirect(U('Subscriber/pay'));
    }
    function pay_ok(){
        redirect(U('Subscriber/pay'));
    }
    /**
     * 我的订单
     */
    public function user_order($p=1){
        $row = 5;
        $PointShopRecord = new PointShopRecordModel();
        $map['sr.user_id'] = is_login();
        $data = $PointShopRecord->getRecordLists($map,"create_time desc",$p,$row);
        $map1['user_id'] = is_login();
        $count = $PointShopRecord->getCount($map1);
        //分页
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $this->assign('_page', $page->show());
        }
        //var_dump($data);
        $this->assign('list',$data);
        $this->display();
    }
}