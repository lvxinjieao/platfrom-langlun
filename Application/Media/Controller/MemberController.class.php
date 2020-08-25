<?php

// +----------------------------------------------------------------------

// | OneThink [ WE CAN DO IT JUST THINK IT ]

// +----------------------------------------------------------------------

// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.

// +----------------------------------------------------------------------

// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>

// +----------------------------------------------------------------------

namespace Media\Controller;

use Think\Checkidcard;
use User\Api\MemberApi;

use Org\XiguSDK\Xigu;
use Org\UcenterSDK\Ucservice;

use Admin\Controller;

/**

 * 文档模型控制器

 * 文档模型列表和详情

 */

class MemberController extends BaseController {



    public function __construct(){

        parent::__construct();

        $arr = array(
            "member/users_index","member/users_safe",
            "member/users_gift","member/profile",
            "member/record","member/phone",
            "member/resetpwd",
            "member/users_db",
        );

        if (in_array(strtolower($_SERVER['PATH_INFO']),$arr,true)) {

            $mid = parent::is_login();

            if ($mid<1) {

                $this->redirect("Member/plogin");

            }

        }
        $game_list=get_game_list();
        $game_keys=array_rand($game_list,3);
        foreach ($game_keys as $val) {
            $game_like[]=$game_list[$val];
        }
        
        $this->assign('game_like',$game_like);
        $user_id=session('member_auth.mid');
        $user=get_user_entity($user_id);
        $this->assign('user',$user);

    }

    public function users_index($value=''){
        $this->profile();
        // $this->display();
    }
    /**

    *平台币

    */

    public function platform(){

        $user=M('User','tab_');

        $map['id']=session('member_auth.mid');

        $data=$user->where($map)->find();

        $this->assign('mid',$data);

        $this->assign('name',session('member_auth.account'));

        $this->display();

    }



    /**

    *消费记录

    */

    public function record($p=0){

        $page1 = intval($p);

        $page1 = $page1 ? $page1 : 1; //默认显示第一页数据

        $game1  = M('spend','tab_');

        $user = session("member_auth");

        $map1['user_account']=$user['account'];//'wan001'

        $map1['pay_status']=1;

        $map1['pay_way']=0;

        $data1  = $game1->where($map1)->order('pay_time desc')->limit('0,10')->select();

        $count1 = $game1->where($map1)->count();

        //分页

        if($count1 > 10){

            $page1 = new \Think\Page($count1, 10);

            $page1->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');

            $this->assign('_page1', $page1->show());

        }

        $this->assign("count1",$count1);

        $this->assign('agent_data', $data1);

        $this->assign('name',$user['account']);





        $model = array(

            'm_name'=>'deposit',

            'prefix'=>'tab_',

            'field' =>true,

            'map'=>array('pay_status'=>1,'user_account'=>$user['account']),//'wan001'

            'order'=>'create_time desc',

            'tmeplate_list'=>'record',

        );

        parent::lists($model,$p);

        //$this->display();

    }

    public function is_login(){
        $mid = parent::is_login();
        if ($mid > 0) {
            $data = parent::entity($mid);
            $data['status'] = 1;
            return $this->ajaxReturn($data);
        } else {
            return $this->ajaxReturn(array('status' => 0, 'msg' => '服务器故障'));
        }
        

    }


    /**

     * 注销当前用户

     * @return void

     */

    public function logout(){

        session('member_auth', null);

        session('member_auth_sign', null);

        session('[destroy]');

        $this->ajaxReturn(array('reurl'=>'media.php'));

    }

    
    function isChineseName($name){
        if(preg_match("/^([\xe4-\xe9][\x80-\xbf]{2}){2,4}$/",$name)){
            return true;
        }else {
            return false;
        }
    }
    function isCnameajax(){
        if(isset($_POST['name'])){
            if(!$this->isChineseName($_POST['name'])){
                $this->ajaxReturn(array('status'=>0,'msg'=>'姓名输入不正确'));
            }else{
                $this->ajaxReturn(array('status'=>1,'msg'=>'姓名输入正确'));
            }
        }
    }
    function isIdcardajax(){
        if(isset($_POST['idcard'])){
            $checkidcard = new Checkidcard();
            $invidcard=$checkidcard->checkIdentity($_POST['idcard']);
            if(!$invidcard){
                $this->ajaxReturn(array('status'=>0,'msg'=>'身份证号码填写不正确,如果有字母请小写'));
            }else{
                $member = new MemberApi();
                $flag = $member->checkIdcard($_POST['idcard']);
                if(!$flag){
                    $this->ajaxReturn(array('status'=>0,'msg'=>$this->getE(-42)));
                }else{
                    $this->ajaxReturn(array('status'=>1,'msg'=>'身份证号码填写正确'));
                }
                
            }
        }
    }
    // 我的资料  lwx 2015-05-19

    public function profile(){
        $res = session("member_auth");
        $res = isset($res['mid']) ? $res['mid'] : 0;
        if (IS_POST) {
            if($_POST['nickname']==''){
                    $this->ajaxReturn(array('status'=>-1,'msg'=>'昵称不能为空'));
            }
            if(isset($_POST['real_name'])){
                if(!$this->isChineseName($_POST['real_name'])){
                    $this->ajaxReturn(array('status'=>0,'msg'=>'姓名输入不正确'));
                }
            }
            if(isset($_POST['idcard'])){
                $checkidcard = new \Think\Checkidcard();
                $invidcard=$checkidcard->checkIdentity($_POST['idcard']);
                if(!$invidcard){
                    $this->ajaxReturn(array('status'=>-1,'msg'=>'身份证号码填写不正确'));
                }
                $cardd=M('User','tab_')->where(array('idcard'=>$_POST['idcard']))->find();
                if($cardd){
                    $this->ajaxReturn(array('status'=>-1,'msg'=>'身份证号码已被使用'));
                }
            }
        
            $map=$_POST;
            $map['id']=$res;
            if (get_tool_status('age') == 0){
                $map['age_status'] = 0;
            }else{
                $re = age_verify($_POST['idcard'],$_POST['real_name']);
                switch ($re)
                {
                    case -1:
                        $this->ajaxReturn(array('status'=>-1,'msg'=>'连接接口失败'));$map['age_status'] =1;
                        break;
                    case -2:
                        $this->ajaxReturn(array('status'=>-1,'msg'=>'连接接口失败'));$map['age_status'] =1;
                        break;
                    case 0:
                        $this->ajaxReturn(array('status'=>-1,'msg'=>'用户数据不匹配'));$map['age_status'] =1;
                        break;
                    case 1://成年
                        $map['age_status'] = 2;
                        break;
                    case 2://未成年
                        $map['age_status'] = 3;
                        break;
                    default:
                }
            }

            $flag = M('User','tab_')->save($map);
            if ($flag!==false) {                
                $data['msg'] = '修改成功';
                $data['status']=1;
            } else {
                $data['msg'] = '修改失败';
                $data['status']=-3;
            }
            $this->ajaxReturn($data);           
        } else {
            $user = M('User','tab_')->where("id=$res")->find();
            $phone = substr($user['phone'],2,-2);
            $user['phone'] = str_replace($phone,'*******',$user['phone']);
            $rl = mb_substr($user['real_name'],0,1,'utf-8');
            $user['real_name']= str_replace($rl,'*',$user['real_name']);
            $idcard = substr($user['idcard'],3,-3);
            $user['idcard']=str_replace($idcard,'*********',$user['idcard']);
            $this->assign('up',$user);
            $this->assign('name',$user['account']);
            $this->display();           
        }
    }

    

    // 重置密码  lwx 2015-05-19

    public function resetpwd() {
        if (IS_POST) {
            $user_id=session('user_auth.user_id');
            $account=session('user_auth.account');
            $opwd=$_POST['password_old'];
            $pwd=$_POST['password'];
            $pwd1=$_POST['password1'];
            $member = new MemberApi();
            $flag = $member->checkPassword($account,$opwd);
            if(!$flag){
                $this->ajaxReturn(array('status'=>0,'msg'=>'原密码错误'));exit;
            }
            $msg=$this->pwd($user_id,$pwd);
            $this->ajaxReturn($msg);exit;
        } else {            
            $this->display();
        }

    }

    

    public function findpwd() {
        if (IS_POST) {
            $telsvcode = session('telsvcode');
            $time = (time() - $telsvcode['time'])/60;
            if ($time>$telsvcode['delay']) {
                session('telsvcode',null);unset($telsvcode);
                echo json_encode(array('status'=>0,'msg'=>'时间超时,请重新获取短信验证码'));exit;
            }
            $phone = $_POST['phone'];
            if (!($telsvcode['code'] == $_POST['pcode']) || !($telsvcode['phone'] == $phone)) {
                echo json_encode(array('status'=>0,'msg'=>'短信验证码输入有误'));exit;
            }
            $verify = new \Think\Verify();
            if(!$verify->check(I('ppceode'),5)){
                echo json_encode(array('status'=>0,'msg'=>'验证码不正确')); exit;
            }
            $user = M('User','tab_')->where(array("account"=>$_POST['account']))->find();
            $this->ajaxReturn($this->pwd($user['id'],I('pwd')));
        } else {                        
            $this->display();             
        }
    }
    

    // 修改密码

    public function pwd($uid,$password) {
        $member = new MemberApi();
        $result = $member->updatePassword($uid,$password);
        if ($result!==false) {
            $data['status']=1;
            $data['msg']='密码修改成功';
        } else {
            $data['status']=0;
            $data['msg']='密码修改失败';
        }
        return $data;
    }
    

    // 推广员推广注册通道  lwx 2016-05-18

    public function preg() {
        $pid= I('pid');
        if (empty($pid)) $pid = 0;   
        $this->assign('pid',$pid);
        $this->display();        
    }
    
    public function plogin() {
        $this->display();           
    }
    

    public function users_safe() {
        $user = session("member_auth");
        if (IS_POST) {
        } else {
            $this->assign('name',$user['account']);
            $this->display();
        }

    }

    

    // 绑定手机 lwx
    public function users_phone() {
        if (IS_POST) {
            $telsvcode = session('telsvcode');
            $time = (time() - $telsvcode['time'])/60;
            if ($time>$telsvcode['delay']) {
                session('telsvcode',null);unset($telsvcode);
                echo json_encode(array('status'=>0,'msg'=>'时间超时,请重新获取短信验证码'));exit;
            }
            $phone = $_POST['phone'];
            if (!($telsvcode['code'] == $_POST['vcode']) || !($telsvcode['phone'] == $phone)) {
                echo json_encode(array('status'=>0,'msg'=>'短信验证码输入有误'));exit;
            }
            $user = session("member_auth");
            $res = $user['mid'];
            if($_POST['jiebang']==1){
                $phone='';
            }
            M('User','tab_')->where("id=$res")->setField('phone',$phone);
            $flag = M('User','tab_')->where(array("id"=>$res,'phone'=> $phone) )->find();
            if ($flag!==false) {
                $data['status']=1;
                if($_POST['jiebang']==1){
                    $data['msg']='手机解绑成功';
                }else{
                    $data['msg']='手机绑定成功';
                }
            } else {
                $data['status']=0;
                if($_POST['jiebang']==1){
                    $data['msg']='手机解绑失败';
                }else{
                    $data['msg']='手机绑定失败';
                }
            }
            session('telsvcode',null);unset($telsvcode);
            echo json_encode($data);
        } else {
            $res = session('member_auth.mid');            
            $ph = M('User','tab_')->field("phone")->where("id=$res")->find();            
            if (!empty($ph) && is_array($ph)) {
                $phone=array();
                if($ph['phone']!=''){
                    $phone1=substr($ph['phone'],0,3);
                    $phone2=substr($ph['phone'],-4);
                    $phone['mi']=$phone1.'****'.$phone2;
                }
                $phone['ming']=$ph['phone'];
                $this->assign('phone',$phone);
            }
            $this->assign('name',session('member_auth.account'));
            $this->display();
        }
    }
    
    public function changeph() {
        if (IS_POST) {
            $telsvcode = session('telsvcode');

            $time = (time() - $telsvcode['time'])/60;

            if ($time>$telsvcode['delay']) {

                session('telsvcode',null);unset($telsvcode);

                echo json_encode(array('status'=>0,'msg'=>'时间超时,请重新获取验证码'));exit;

            }

            $phone = $_POST['phone'];

            if (!($telsvcode['code'] == $_POST['vcode']) || !($telsvcode['phone'] == $phone)) {

                echo json_encode(array('status'=>0,'msg'=>'安全码输入有误'));exit;

            }
            $res = session("member_auth.mid");
            
            M('User','tab_')->where("id=$res")->setField('phone','');
            
            $flag = M('User','tab_')->where("id=$res and phone = $phone")->find();
            
            if (!$flag) {

                $data['status']=1;

                $data['msg']='手机解绑成功';

            } else {

                $data['status']=0;

                $data['msg']='手机解绑失败';

            }

            session('telsvcode',null);unset($telsvcode);

            echo json_encode($data);
            
        } else {
            echo json_encode(array('status'=>0,'msg'=>'服务器故障'));exit;
        }
    }

    

    // 绑定证件  lwx  2015-05-20

    public function card() {
        $user = session('member_auth');
        if (IS_POST) {
            $real_name =I('real_name');
            $idcard = I('idcard');
            if (empty($real_name) || empty($idcard)) {
                echo json_encode(array('status'=>0,'msg'=>'提交的数据有误'));
            }
            $data['id']=$user['mid'];
            $data['real_name']=$real_name;
            $data['idcard']=$idcard;
            $flag = M('User','tab_')->save($data);
            $data='';
            if ($flag) {
                $data['status']=1;
                $data['msg']='认证成功';
            } else {
                $data['status']=0;
                $data['msg']='认证失败';
            }
            echo json_encode($data);
        } else {
            $user = M('User','tab_')->where("id=".$user['mid'])->find();
            if (!empty($user['real_name']) && !empty($user['idcard'])) {
                $this->assign('istrue','1');
            }
            $this->display();
        }       

    }

    

    public function sendvcode() {
        if (!IS_POST) {
            echo json_encode(array('status'=>0,'msg'=>'请按正确的流程'));exit;
        }
        $verify = new \Think\Verify();
        if(!$verify->check(I('verify'),I('vid'))){
            echo json_encode(array('status'=>2,'msg'=>'*验证码不正确')); exit;
        }
        $phone = I('phone');
        $this->telsvcode($phone);             
    }

    

    // 忘记密码 lwx 2015-05-19

    public function forget() {

        $mid = parent::is_login();

        if ($mid>0) {

            $this->redirect('Member/resetpwd/t/m/name/'.session('member_auth.account'));

        }

        if (IS_POST) {

            $account = I('account');

            $user = M('User','tab_')->where("account='$account'")->find();

            if (!empty($user) && is_array($user) && (1 == $user['lock_status'])) {

                $data['status']=1;

                $data['phone']=$user['phone'];

            } else {

                $data['status']=0;

            }   

            echo json_encode($data);

        } else {            

            $this->display();

        }

    }

    public function login(){
        if (empty($_POST['account'])) {
            $this->ajaxReturn(array('status' => -1001, 'code'=>-1001,'msg' => '账号不能为空'));
        }
        if (empty($_POST['password'])) {
            $this->ajaxReturn(array('status' => -1002, 'code'=>-1002,'msg' => '密码不能为空'));
        }
        if (empty($_POST['yzm'])&&isset($_POST['yzm'])) {
            $this->ajaxReturn(array('status' => -1003, 'code'=>-1003,'msg' => '验证码不能为空'));
        }else if(isset($_POST['yzm'])){
            $verify = new \Think\Verify();
             if(!$verify->check(I('yzm'))){
                $this->ajaxReturn(array('status' => -1003, 'code'=>-10031, 'msg' => '验证码错误'));
            }
        }
            $data = array();
            $member = new MemberApi();
            $res = $member->otpLogin($_POST['account'], $_POST['password']);
            if ($res > 0) {
                if($_POST['remuser']==1){
                    setcookie('media_account',$_POST['account'],time()+3600*10000,$_SERVER["HTTP_HOST"]);
                }else{
                    setcookie('media_account',$_POST['account'],time()-1,$_SERVER["HTTP_HOST"]);
                }
                parent::autoLogin($res);
                $this->ajaxReturn(array('status' => 1000, 'code'=>1000, 'msg' => '登录成功'));
            } else {
                switch ($res) {
                    case -1000:
                        $data = array('status' => -1000, 'code'=>-1000, 'msg' => '用户不存在');
                        break;
                    case -1001:
                        $data = array('status' => -1001, 'code'=>-1001, 'msg' => '用户被禁用，请联系客服');
                        break;
                    case -10021:
                        $data = array('status' => -1002, 'code'=>-10021,'msg' => '密码错误');
                        break;
                    case -1004:
                        $data = array('status' => -1004,'code'=>-1004);
                        break;
                    case -10041:
                        $data = array('status' => -1004,'code'=>-10041, 'msg' => "动态密码错误");
                        break;
                    default:
                        $data = array('status' => -500,'code'=>-500, 'msg' => '未知错误');
                        break;
                }
                $this->ajaxReturn($data);
            }

    }
    public function uc_login($account,$password){
        $user['account']=$account;
        $user['password']=$password;
        $uc = new Ucservice();
            $uidarray = $uc->uc_login_($user['account'], $user['password']);
            if (is_array($uidarray)) {
                $is_c = find_uc_account($user['account']);
                if (is_array($is_c)) {
                    $map['id'] = $is_c['id'];
                    M('user', 'tab_')->where($map)->setField('login_time', time());
                    $uidd = $is_c['id'];
                    $nickname = $is_c['account'];
                } else {
                    $uidd = $uidarray['uid'];
                    $nickname = "Uc用户";
                }
                $auth = array(
                    'mid' => $uidd,
                    'account' => $user['account'],
                    'nickname' => $nickname,
                    'balance' => empty($is_c['balance']) ? 0 : $is_c['balance'],
                    'last_login_time' => empty($is_c['login_time']) ? "*******" : $is_c['login_time']
                );
                session('member_auth', $auth);
                session('member_auth_sign', data_auth_sign($auth));

                $this->ajaxReturn(array('status' => 1000, 'msg' => '登录成功'));
            }else{
                if($uidarray == -1) {
                    $this->ajaxReturn(array('status'=>-1,'msg'=>'用户不存在,或者被删除'));
                } elseif($uidarray == -2) {
                    $this->ajaxReturn(array('status'=>-2,'msg'=>'密码错误')) ;
                } elseif($uidarray == -3) {
                    $this->ajaxReturn(array('status'=>-3,'msg'=>'验证码错误')) ;
                } else {
                    $this->ajaxReturn(array('status'=>-4,'msg'=>'未定义')) ;
                }
            }
    }

    /**
    *注册后完成登录
    */
    public function res_login(){
        parent::autoLogin($_POST['uid']);
        $this->ajaxReturn(array("status"=>0,"uid"=>$_POST['uid']));
    }

    public function register(){
        if(IS_POST){
            if(C("USER_ALLOW_REGISTER")==1){    
                if(empty($_POST['uname']) || empty($_POST['upwd'])){
                    return $this->ajaxReturn(array('status'=>0,'msg'=>'账号或密码不能为空'));
                } else if(strlen($_POST['uname'])>15||strlen($_POST['uname'])<6){
                    return $this->ajaxReturn(array('status'=>0,'msg'=>'用户名长度在6~15个字符'));
                }else if(!preg_match('/^[a-zA-Z0-9]{6,15}$/', $_POST['uname'])){
                    return $this->ajaxReturn(array('status'=>0,'msg'=>'用户名包含特殊字符'));
                }
                if($uc_id==-3){
                    $msg ="账号已存在";
                    return $this->ajaxReturn(array('status'=>0,'msg'=>$msg));
                }else{
                $member = new MemberApi();
                $pid = $_POST['pid'];
                if ($pid) {
                    $promote = M("Promote","tab_")->where("id=$pid")->find();
                    if($promote){
                        $ppid=$promote['id'];
                        $ppaccount=$promote['account'];
                        $parent_id=$promote['parent_id'];
                        $parent_name=$promote['parent_name'];
                    }else{
                        $ppid=0;
                        $ppaccount='自然注册';
                    }
                }else{
                    $ppid=0;
                    $ppaccount='自然注册';
                }
                $resdata=array();
                $resdata['account']=trim($_POST['uname']);
                $resdata['nickname']=trim($_POST['uname']);
                $resdata['password']=$_POST['upwd'];
                $resdata['register_way']=3;
                $resdata['register_type']=1;
                $resdata['promote_id']=$ppid;
                $resdata['promote_account']=$ppaccount;
                $resdata['parent_id']=$parent_id;
                $resdata['parent_name']=$parent_name;
                $resdata['phone']='';
                $resdata['real_name']=$_POST['rname'];
                $resdata['idcard']=$_POST['icard'];

                $verify = new \Think\Verify();
                if(!$verify->check($_POST['r_quan'],666)){//vid 1000 注意
                    $this->ajaxReturn(array('status'=>0,'msg'=>'验证码不正确'));
                }
                
                //调用数据传递身份证和姓名验证是否是确定的
                if (get_tool_status("age") == 1){
                    $re = age_verify($resdata['real_name'],$resdata['idcard']);
                    if ($re == 0){
                        return $this->ajaxReturn(array('status'=>0,'msg'=>'审核失败！'));
                    }
                    if ($re == -1){
                        return $this->ajaxReturn(array('status'=>0,'msg'=>'短信数量已使用完！'));
                    }
                    if ($re == -2){
                        return $this->ajaxReturn(array('status'=>0,'msg'=>'连接错误，请检查配置！'));
                    }
                    if ($re == 1){
                        $resdata['age_status'] = 2;
                    }
                    if ($re == 2){
                        $resdata['age_status'] = 3;
                    }
                }
                

                $res = $member->register($resdata);
                if($res > 0 ){
                    return $this->ajaxReturn(array('status'=>1,'msg'=>'注册成功',"uid"=>$res));
                }
                else{
                    $msg = $res == -1 ?"账号已存在":"注册1失败";
                    return $this->ajaxReturn(array('status'=>0,'msg'=>$msg));
                } 
                }
                   
                }else{
                    return $this->ajaxReturn(array('status'=>0,'msg'=>'未开放注册！！'));
                }
            }else{
                $this->display();
            }
    }
  
    
    // 发送手机安全码

    public function telsvcode($phone=null,$delay=10,$flag=true) {
        if (empty($phone)) {
            echo json_encode(array('status'=>0,'msg'=>'请输入手机号码'));exit; 
        }
        /// 产生手机安全码并发送到手机且存到session
        $rand = rand(100000,999999);
        $param = $rand.",".$delay;
        if(get_tool_status("sms_set")){
            checksendcode($phone,C('sms_set.limit'));
            $xigu = new Xigu(C('sms_set.smtp'));
	        $result = json_decode($xigu->sendSM(C('sms_set.smtp_account'),$phone,C('sms_set.smtp_port'),$param),true);
	        if ($result['send_status'] != '000000') {
		        echo json_encode(array('status'=>0,'msg'=>'发送失败，请重新获取'));exit;
	        }
        }elseif(get_tool_status("alidayu")){
            checksendcode($phone,C('alidayu.limit'));
            $xigu = new Xigu('alidayu');
	        $result = $xigu->alidayu_send($phone,$rand,$delay);
            $result['send_time'] = time();
	        if($result == false){
		        echo json_encode(array('status'=>0,'msg'=>'发送失败，请重新获取'));exit;
	        }
        }elseif(get_tool_status('jiguang')){
            checksendcode($phone,C('jiguang.limit'));
            $xigu = new Xigu('jiguang');
            $result = $xigu->jiguang($phone,$rand,$delay);
            $result['send_time'] = time();
            if($result == false){
                echo json_encode(array('status'=>0,'msg'=>'发送失败，请重新获取'));exit;
            }
        }else{
	        echo json_encode(array('status'=>0,'msg'=>'没有配置短信发送'));exit;
        }
        
	    // 存储短信发送记录信息
        $result['send_status'] = '000000';
        $result['phone'] = $phone;
        $result['create_time'] = time();
        $result['pid']=0;
        $result['create_ip']=get_client_ip();
        $r = M('Short_message')->add($result);

        $telsvcode['code']=$rand;
        $telsvcode['phone']=$phone;
        $telsvcode['time']=time();
        $telsvcode['delay']=$delay;
        session('telsvcode',$telsvcode);
        if ($flag) {
            echo json_encode(array('status'=>1,'msg'=>'安全码已发送，请查收'));
        } else
            echo json_encode(array('status'=>1,'msg'=>''));
    }

    // 短信验证
    public function checktelsvcode($phone,$vcode,$flag=true) {       
        $telsvcode = session('telsvcode');
        $time = (time() - $telsvcode['time'])/60;
        if ($time>$telsvcode['delay']) {
            session('telsvcode',null);unset($telsvcode);
            echo json_encode(array('status'=>0,'msg'=>'时间超时,请重新获取验证码'));exit;
        }
        if (!($telsvcode['code'] == $vcode) || !($telsvcode['phone'] == $phone)) {
            echo json_encode(array('status'=>0,'msg'=>'安全码输入有误'));exit;
        }
        session('telsvcode',null);
        unset($telsvcode); 
        if ($flag) {
            echo json_encode(array('status'=>1));
        }
    }
    // 验证码验证
    public function checkverifycode() {   
        $verify = new \Think\Verify();
        if(!$verify->check($_POST['code'],1000)){//vid 1000 注意
            $this->ajaxReturn(array('status'=>0,'msg'=>'*验证码不正确'));
        }else{
            $this->ajaxReturn(array('status'=>1,'msg'=>'*验证码正确'));
        }
    }

    public function result($phone,$vcode,$password,$pid=0) {
        $member = new MemberApi();
        $this->checktelsvcode($phone,$vcode,false);
        $flag = $member->checkUsername($phone);
        if (!$flag) {
            $data['msg']  = $this->getE(-11);
            $data['status'] =  0;
            $this->ajaxReturn($data,C('DEFAULT_AJAX_RETURN'));exit;
        }
        $uid = $member->register(trim($phone),trim($password));
        if($uid>0) {
            M('User','tab_')->save(array("id"=>$uid,"phone"=>$phone));
            if ($pid) {
                M('User','tab_')->where("id=$uid")->setField('promoteid',$pid);
            }
            $data['msg']="注册成功";
            $data['status']=1;
            $data['url']='';
        } else {
            $data['msg']  = '注册失败';
            $data['status'] =  0;
        }           
        $this->ajaxReturn($data,C('DEFAULT_AJAX_RETURN')); 

    }
    public function users_gift(){
        $data=M('gift_record','tab_')
            ->field('tab_gift_record.id,tab_gift_record.novice,tab_game.icon,tab_gift_record.gift_name,tab_game.relation_game_name,tab_giftbag.start_time,tab_giftbag.end_time,tab_giftbag.desribe,tab_giftbag.digest')
            ->where(array('user_id'=>session('member_auth.mid')))
            ->join('tab_game on tab_gift_record.game_id=tab_game.id')
            ->join('tab_giftbag on tab_gift_record.game_id=tab_giftbag.game_id')
            ->group('tab_gift_record.id')
            ->select();
        $this->assign('list_data',$data);
        $this->display();
    }
    public function users_db(){
        $mid=session('user_auth.user_id');
        $data=M('user_play','tab_')
            ->where(array('user_id'=>$mid))
            ->select();
        $this->assign('list_data',$data);
        $this->display();
    }

    public function telregister() {
        $data = array();
        if (IS_POST) {
            $member = new MemberApi();
            $telsvcode = session('telsvcode');
            $time = (time() - $telsvcode['time'])/60;
            if ($time>$telsvcode['delay']) {
                session('telsvcode',null);unset($telsvcode);
                echo json_encode(array('status'=>0,'msg'=>'时间超时,请重新获取验证码'));exit;
            }
            if (!($telsvcode['code'] == $_POST['msg_code']) || !($telsvcode['phone'] == $_POST['telnum'])) {
                echo json_encode(array('status'=>0,'msg'=>'安全码输入有误'));exit;
            }
            if($uc_id==-3){
                return $this->ajaxReturn(array('status'=>0,'msg'=>'"账号已存在"'));
            }else{
            $member = new MemberApi();
            $pid = $_POST['pid'];
            if ($pid) {
                $promote = M("Promote","tab_")->where("id=$pid")->find();
                if($promote){
                    $ppid=$promote['id'];
                    $ppaccount=$promote['account'];
                    $parent_id=$promote['parent_id'];
                    $parent_name=$promote['parent_name'];
                }else{
                    $ppid=0;
                    $ppaccount='自然注册';
                }
            }else{
                $ppid=0;
                $ppaccount='自然注册';
            }
            $resdata=array();
            $resdata['account']=trim($_POST['telnum']);
            $resdata['nickname']=trim($_POST['telnum']);
            $resdata['password']=$_POST['upwd'];
            $resdata['register_way']=3;
            $resdata['register_type']=2;
            $resdata['promote_id']=$ppid;
            $resdata['promote_account']=$ppaccount;
            $resdata['parent_id']=$parent_id;
            $resdata['parent_name']=$parent_name;
            $resdata['phone']=trim($_POST['telnum']);
            $resdata['real_name']=$_POST['rname'];
            $resdata['idcard']=$_POST['t_icard'];
            $res = $member->register($resdata);
            if($res > 0 ){
                if ($pid) {
                    $promote = M("Promote","tab_")->where("id=$pid")->find();
                    $data=array('id'=>$res,'promote_id'=>$pid,'promote_account'=>$promote['account']);
                    $b = M('User','tab_')->save($data);
                    if (!$b) {
                        M('User','tab_')->save($data);
                    }
                }
                return $this->ajaxReturn(array('status'=>1,'msg'=>'注册成功',"uid"=>$res));
            }
            else{
                $msg = $res == -1 ?"账号已存在":"注册失败";
                return $this->ajaxReturn(array('status'=>0,'msg'=>$msg));
            }
          
            $flag = $member->checkUsername($_POST['account']);
            if (!$flag) {
                $data['msg']  = $this->getE(-11);
                $data['status'] =  0;
                $this->ajaxReturn($data,C('DEFAULT_AJAX_RETURN'));exit;
            }
            $pid = $_POST['pid'];
            $paccount=M('Promote','tab_')->field('account')->where(array('id'=>$pid))->find();
            $uid = $member->register(trim($_POST['account']),trim($_POST['password']),0,$pid,$paccount['account'],$_POST['account']);
            if($uid>0) {
                M('User','tab_')->save(array("id"=>$uid,"phone"=>$_POST['account']));
                if ($pid) {
                    M('User','tab_')->where("id=$uid")->setField('promoteid',$pid);
                }
                $data['msg']="注册成功";
                $data['status']=1;
                $data['url']='';
                //$this->ajaxReturn($data,C('DEFAULT_AJAX_RETURN'));
            } else {
                $data['msg']  = '注册失败';
                $data['status'] =  0;
            }
            session('telsvcode',null);unset($telsvcode);
            $this->ajaxReturn($data,C('DEFAULT_AJAX_RETURN'));
        }
            
        } else {
            $this->redirect('Index/index');
        }       

    }

     public function reg_data(){
        $member = new MemberApi();
        $pid = $_POST['pid'];
        $res = $member->register(trim($_POST['account']),$_POST['password']);
        if($res > 0 ){
            if ($pid) {
                $promote = M("Promote","tab_")->where("id=$pid")->find();
                $data=array('id'=>$res,'promote_id'=>$pid,'promote_account'=>$promote['account']);
                $b = M('User','tab_')->save($data);
                if (!$b) {
                    M('User','tab_')->save($data);
                }
            }
            return $this->ajaxReturn(array('status'=>1,'msg'=>'注册成功',"uid"=>$res));
        }
        else{
            $msg = $res == -1 ?"账号已存在":"注册失败";
            return $this->ajaxReturn(array('status'=>0,'msg'=>$msg));
        }
    }      

    /**

    * 验证用户名

    */

    public function checkUser() {

        if (IS_POST) {
            $username = $_POST['username'];

            $len = strlen($username);

            if ($len !== mb_strlen($username)) {

                return $this->ajaxReturn(array('status'=>0,'msg'=>$this->getE(-22)),C('DEFAULT_AJAX_RETURN'));

            }

            if ($len<6 || $len >30) {

                return $this->ajaxReturn(array('status'=>0,'msg'=>$this->getE(-22)),C('DEFAULT_AJAX_RETURN'));

            }

            if(!preg_match("/^[a-zA-Z]+[0-9a-zA-Z_]{5,29}$/u",$username)) {

                return $this->ajaxReturn(array('status'=>-21,'msg'=>$this->getE(-21)),C('DEFAULT_AJAX_RETURN'));

            }

            $member = new MemberApi();

            $flag = $member->checkUsername($username);

            if ($flag) {

                return $this->ajaxReturn(array('status'=>1),C('DEFAULT_AJAX_RETURN'));

            } else {

                return $this->ajaxReturn(array('status'=>0,'msg'=>$this->getE(-3)),C('DEFAULT_AJAX_RETURN'));

            }

        }

    }

    /**

    * 验证手机号码

    */

    public function checkPhone() {

        if (IS_POST) {
            C(api('Config/lists'));
            $username = $_POST['username'];

            $len = strlen($username);

            if ($len !== mb_strlen($username)) {

                return $this->ajaxReturn(array('status'=>0,'msg'=>$this->getE(-9)),C('DEFAULT_AJAX_RETURN'));

            }

            if ($len !== 11) {

                return $this->ajaxReturn(array('status'=>0,'msg'=>$this->getE(-12)),C('DEFAULT_AJAX_RETURN'));

            }

            if(!preg_match("/^1[358][0-9]{9}$/u",$username)) {

                return $this->ajaxReturn(array('status'=>-21,'msg'=>$this->getE(-12)),C('DEFAULT_AJAX_RETURN'));

            }

            $member = new MemberApi();
            $flag = $member->checkUsername($username);
            if ($flag) {
                return $this->ajaxReturn(array('status'=>1),C('DEFAULT_AJAX_RETURN'));
            } else {
                return $this->ajaxReturn(array('status'=>0,'msg'=>$this->getE(-11)),C('DEFAULT_AJAX_RETURN'));

            }

        }

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

            case -12: $error = '手机号码必须由11位数字组成';break;

            case -20: $error = '请填写正确的姓名';break;

            case -21: $error = '用户名必须由字母、数字或下划线组成,以字母开头';break;

            case -22: $error = '用户名必须由6~30位数字、字母或下划线组成';break;

            case -31: $error = '密码错误';break;

            case -32: $error = '用户不存在或被禁用';break;

            case -41: $error = '身份证无效';break;
            case -42: $error = '身份证已使用';break;

            default:  $error = '未知错误';

        }

        return $error;

    }
    function checkfindpwd(){
        $model=M('User','tab_');
        if(isset($_POST['name'])){
            if($_POST['name']==''){
                $this->ajaxReturn(array('status'=>-1,'msg'=>'请输入用户账号'));
            }
            $user=$model->where(array('account'=>$_POST['name']))->find();
            if($user==''){
                $this->ajaxReturn(array('status'=>-1,'msg'=>'用户名输入错误或不存在'));
            }
            if($_POST['type']==1&&$user){
                $this->ajaxReturn(array('status'=>1,'msg'=>'用户名输入正确'));
            }
        }
        if(isset($_POST['phone'])){
            if($_POST['name']==''){
                $this->ajaxReturn(array('status'=>-1,'msg'=>'请输入用户账号'));
            }
            if($_POST['phone']==''){
                $this->ajaxReturn(array('status'=>-2,'msg'=>'请输入您的手机号码'));
            }
            $user=$model->where(array('account'=>$_POST['name'],'phone'=>$_POST['phone']))->find();
            if($user==''){
                $this->ajaxReturn(array('status'=>-2,'msg'=>'不是绑定手机号'));
            }
            if($_POST['type']==2&&$user){
                $this->ajaxReturn(array('status'=>2,'msg'=>'绑定手机号输入正确'));
            }
        }
        if(isset($_POST['code'])){
            if($_POST['name']==''){
                $this->ajaxReturn(array('status'=>-1,'msg'=>'请输入用户账号'));
            }
            if($_POST['phone']==''){
                $this->ajaxReturn(array('status'=>-2,'msg'=>'请输入您的手机号码'));
            }
            if($_POST['code']==''){
                $this->ajaxReturn(array('status'=>-3,'msg'=>'验证码不能为空'));
            }
            $verify = new \Think\Verify();
            if(!$verify->check($_POST['code'],4)){
                $this->ajaxReturn(array('status'=>-3,'msg'=>'验证码不正确'));
            }
            $this->ajaxReturn(array('status'=>3,'msg'=>'验证通过'));
        }
    }


    /**

    * 领取礼包

    */

    public function getGameGift() { 
        $mid = parent::is_login();;

        if($mid==0){

            echo  json_encode(array('status'=>'0','msg'=>'请先登录'));

            exit();

        }
        $list=M('record','tab_gift_');
        $is=$list->where(array('user_id'=>$mid,'gift_id'=>$giftid));
        if($is) {   
                $map['user_id']=$mid;
                $map['gift_id']=$_POST['giftid'];
                $msg=$list->where($map)->find();
            if($msg){
                $data=$msg['novice'];
                echo  json_encode(array('status'=>'1','msg'=>'no','data'=>$data));
            }
            else{           
                $bag=M('giftbag','tab_');               
                $giftid= $_POST['giftid'];
                $ji=$bag->where(array("id"=>$giftid))->field("novice,end_time")->find();
                if(empty($ji['novice'])){
                    echo json_encode(array('status'=>'1','msg'=>'noc'));
                }
                else{
                    if($ji['end_time']!=0&&$ji['end_time']-time()<0){
                        echo json_encode(array('status'=>'1','msg'=>'not'));
                    }else{
                        $at=explode(",",$ji['novice']);
                        $gameid=$bag->where(array("id"=>$giftid))->field('server_id,server_name,game_id')->find();
                        $add['game_id']=$gameid['game_id'];
                        $add['game_name']=get_game_name($gameid['game_id']);
                        $add['gift_id']=$_POST['giftid'];
                        $add['gift_name']=$_POST['giftname'];
                        $add['server_id'] =$gameid['server_id'];
                        $add['server_name'] =$gameid['server_name'];
                        $add['status']=1;
                        $add['novice']=$at[0];
                        $add['user_id'] =$mid;
                        $add['user_account']=get_user_account($mid);
                        $add['create_time']=strtotime(date('Y-m-d h:i:s',time()));
                        $list->add($add);
                        $new=$at;
                        if(in_array($new[0],$new)){
                            $sd=array_search($new[0],$new);
                            unset($new[$sd]);
                        }
                        $act['novice']=implode(",", $new);
                        $bag->where("id=".$giftid)->save($act);
                        echo  json_encode(array('status'=>'1','msg'=>'ok','data'=>$at[0]));
                    }
                }   
            } 
        }

    }


    public function verify($vid=''){
        $config = array(
            'seKey'     => 'ThinkPHP.CN',   //验证码加密密钥
            'fontSize'  => 22,              // 验证码字体大小(px)
            'imageH'    => 42,               // 验证码图片高度
            'imageW'    => 150,               // 验证码图片宽度
            'length'    => 4,               // 验证码位数
            'fontttf'   => '4.ttf',              // 验证码字体，不设置随机获取
        );
        ob_clean();
        $verify = new \Think\Verify($config);
        $verify->entry($vid);
    }





}

