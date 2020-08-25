<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: huajie <banhuajie@163.com>
// +----------------------------------------------------------------------

namespace Admin\Model;
use Think\Model;

/**
 * 文档基础模型
 */
class UserModel extends Model{

    /* 自动验证规则 */
    protected $_validate = array(
        array('account', '', -3, self::EXISTS_VALIDATE, 'unique'), //用户名被占用
    );

    /* 自动完成规则 */
    protected $_auto = array(
        array('password', 'think_ucenter_md5', self::MODEL_BOTH, 'function', UC_AUTH_KEY),
        array('anti_addiction', 0, self::MODEL_INSERT),
        array('lock_status', 1, self::MODEL_INSERT),
        array('balance', 0, self::MODEL_INSERT),
        array('cumulative', 0, self::MODEL_INSERT),
        array('vip_level', 0, self::MODEL_INSERT),
        array('register_time', NOW_TIME,self::MODEL_INSERT),
    );

    /**
     * 构造函数
     * @param string $name 模型名称
     * @param string $tablePrefix 表前缀
     * @param mixed $connection 数据库连接信息
     */
    public function __construct($name = '', $tablePrefix = '', $connection = '') {
        /* 设置默认的表前缀 */
        $this->tablePrefix ='tab_';
        /* 执行构造方法 */
        parent::__construct($name, $tablePrefix, $connection);
    }
    public function register($data,$type=''){
        $data = array(
            'account'    => $data['account'],
            'password'   => $data['password'],
            'nickname'   => $data['nickname'],
            'phone'      => $data['phone'],
            'real_name'      => $data['real_name'],
            'idcard'      => $data['idcard'],
            'openid'     => $data['openid'],
            'promote_id' => $data['promote_id'],
            'promote_account'  => $data['promote_account'],
            'third_login_type' => $data['third_login_type'],
            'register_way' => $data['register_way'],
            'register_ip'  => get_client_ip(),
            'parent_id'=>$data['parent_id'],
            'head_icon'=>$data['head_icon'],
            'parent_name'=>$data['parent_name'],
            'fgame_id'=>$data['fgame_id'],
            'fgame_name'=>$data['fgame_name'],
            'is_union'=>$data['is_union'],
            'register_time'=>time(),
            'login_time'=>time(),
            'age_status'=>$data['age_status'],
        );
        /* 添加用户 */
        if($this->create($data)){
            $uid = $this->add();
            $data['id'] = $uid;
            $u_user['uid']=$uid;
            $u_user['account']=$data['account'];
            $u_user['password']=think_encrypt($data['password']);
            $this->autoLogin($data);
            $this->updateLogin($uid); //更新用户登录信息
            if($type!='app'){
                $this->user_login_record($data,'',$game_data['id'],$game_data['game_name']);
            }
            if($data['fgame_id']){
                $game_data=D('Game')->where(array('id'=>$data['fgame_id']))->find();
                if(!$game_data){
                    return -5;//游戏不存在
                }else{
                    $this->set_user_promote($uid,$game_data['id'],$game_data['game_name']);
                    $this->add_user_play($uid,$game_data['id']);
                }
            }
            if(!empty($data['openid'])){
                $this->update_third_Login($data['id'],$data['nickname']);
            }
            return $uid ? $uid : 0; //0-未知错误，大于0-注册成功
        } else {
            return $this->getError(); //错误详情见自动验证注释
        }
    }
    //写入用户第一次游戏信息
    public  function set_user_promote($uid,$game_id,$game_name){
        $map['id']=$uid;
        $user=M("User","tab_")->where($map)->find();
        if($user['fgame_id']==0){
            $data = array('fgame_id'=>$game_id,'fgame_name'=>$game_name);
            M("user","tab_")->where($map)->setField($data);
        }
    }
    //玩家记录
     public function add_user_play($user_id,$game_id){
        //实例化 play
        $user_play = M("play","tab_user_");
        $play_map['user_id'] = $user_id;
        $play_map['game_id'] = $game_id;
        $user=get_user_entity($user_id);
        $game=game_entity_data($game_id);
        $pid=$user['promote_id'];//如果1推广员的用户走的2推广员的链接，这条记录还应该是1推广员的
        $play = $user_play->where($play_map)->find();
        if(empty($play)){
            $play_data["user_id"] = $user_id;
            $play_data["user_account"] =$user['account'];
            $play_data["user_nickname"] =$user['nickname'];
            $play_data["game_appid"] = $game['game_appid'];
            $play_data["game_id"] = $game['id'];
            $play_data["game_name"] = $game['game_name'];
            $play_data["area_id"] = 0;
            $play_data["area_name"] = "";
            $play_data["role_id"] = 0;
            $play_data["role_name"] = "";
            $play_data["role_level"] = 0;
            $play_data["balance"] = 0;
            $play_data["play_time"] = time();
            $play_data["update_time"] = time();
            $play_data["show_foot"] = 1;
            $play_data["promote_id"]=$user['promote_id'];//推广id
            $play_data["promote_account"] = $user['promote_account'];//推广姓名
            $user_play->add($play_data);
        }else{
            $play_data["play_time"] = time();
            $play_data["show_foot"] = 1;
            $user_play->where($play_map)->save($play_data);
        }

    }
    /**
     *  用于app放传token过来进行登录
     */
    public function sign_login($token){
        $token = think_decrypt($token);
        if(empty($token)){
            return "";
        }
        $info = json_decode($token,true);
        if(!$info['user_id']){
            return "";
        }

        $user_info = M('User','tab_')
            ->where(array('id'=>$info['user_id']))
            ->field('id,account,nickname')
            ->find();

        $this->updateLogin($user_info['id']); //更新用户登录信息
        $this->autoLogin($user_info);
    }
    /**
     *游戏用户注册
     *user表加game_id
     */
    public function register_($account,$password,$register_type,$register_way,$promote_id=0,$promote_account="",$phone="",$game_id="",$game_name="",$sdk_version="",$imei='',$id = 0){

        $data = array(
            'account'    => $account,
            'password'   => $password,
            'nickname'   => $account,
            'phone'      => $phone,
            'head_img'  =>"http://".$_SERVER['HTTP_HOST'].'/Public/Sdk/logoo.png',
            'promote_id' => $promote_id,
            'promote_account' => $promote_account,
            'register_way' => $register_way,
            'register_type' => $register_type,
            'register_ip'  => get_client_ip(),
            'parent_id'=>get_fu_id($promote_id),
            'parent_name'=>get_parent_name($promote_id),
            'fgame_id'  =>$game_id,
            'fgame_name'=>$game_name,
            'sdk_version'=>$sdk_version,
            'imei'=>$imei,
        );
        if($id != 0 ) {
            $data = array_merge($data,['id'=>$id,'is_transfer'=>1]);
        }
        if($register_type==2){
            $map['phone']=$phone;
            $find=M('user','tab_')->field('phone')->where($map)->find();
            if(null!==$find){
                return -3;exit;
            }
        }
        if($register_type==5){
            $map['email']=$account;
            $find=M('user','tab_')->field('email')->where($map)->find();
            if(null!==$find){
                return -3;exit;
            }
            $data['email']=$account;
        }
        /* 添加用户 */
        if($this->create($data)){
			
            $uid = $this->add();
            $u_user['uid']=$uid;
            $u_user['account']=$account;
            $u_user['password']=think_encrypt($password);
            M('user_pwd')->add($u_user);
            return $uid ? $uid : 0; //0-未知错误，大于0-注册成功
        } else {
            return $this->getError(); //错误详情见自动验证注释
        }
    }
    /**
    *app用户注册
    */
    public function app_register($account,$password,$register_way,$register_type,$nickname,$sex,$promote_id){
        $phoneUnique = phoneUnique($account);
        if(!$phoneUnique){
            return '-3';
        }
        $nickname = $nickname?$nickname:$account;
        $data = array(
            'account'    => $account,
            'password'   => $password,
            'register_way' => $register_way,            
            'register_type' => $register_type,            
            'nickname'   => $nickname,
            'sex' => $sex,
            'login_time'=>time(),
            'phone' => $account,
            'register_ip'  => get_client_ip(),
            'promote_id' => $promote_id,
            'promote_account' => get_promote_account($promote_id),
            'token' => $this->get_token($account, $is_uc),
        );
        /* 添加用户 */
        if($this->create($data)){
            $uid = $this->add();
            return $uid ? $uid : 0; //0-未知错误，大于0-注册成功
        } else {
            return $this->getError(); //错误详情见自动验证注释
        }
    }
    /**
     * 重写普通注册
     * @param $account
     * @param $password
     * @param $register_way
     * @param $register_type
     * @param $nickname
     * @param $sex
     * @param $promote_id
     * @return int|mixed|string
     * author: xmy 280564871@qq.com
     */
    public function common_register($account,$password,$register_way,$register_type,$nickname,$sex,$promote_id){
        $data = array(
            'account'    => $account,
            'password'   => $password,
            'register_way' => $register_way,
            'register_type' => $register_type,
            'nickname'   => $nickname,
            'sex' => $sex,
            'login_time'=>time(),
            'register_ip'  => get_client_ip(),
            'promote_id' => $promote_id,
            'promote_account' => get_promote_account($promote_id),
            'token' => $this->get_token($account, $is_uc),
        );
        /* 添加用户 */
        if($this->create($data)){
            $uid = $this->add();
            return $uid ? $uid : 0; //0-未知错误，大于0-注册成功
        } else {
            return $this->getError(); //错误详情见自动验证注释
        }
    }
    public function login($account,$password,$type,$game_id,$login_type=0){
        //用于不使用密码登陆游戏
        if($type=='nomima'){
            $nomima = $type;
            unset($type);
        }
        $map['account'] = $account;
        /* 获取用户数据 */
        $user = $this->where($map)->find();
        if(is_array($user) && $user['lock_status']){
            /* 验证用户密码 */
            if((think_ucenter_md5($password, UC_AUTH_KEY) === $user['password'])||$nomima=='nomima'){
                $this->updateLogin($user['id']); //更新用户登录信息
                $this->autoLogin($user);
                $this->user_login_record($user,$type,$game_data['id'],$game_data['game_name'],$login_type);
                if($game_id){
                    $game_data=D('Game')->where(array('id'=>$game_id))->find();
                    if(!$game_data){
                        return -5;//游戏不存在
                    }else{
                        $this->set_user_promote($user['id'],$game_data['id'],$game_data['game_name']);
                        $this->add_user_play($user['id'],$game_data['id']);
                    }
                }
                return $user['id']; //登录成功，返回用户ID
            } else {
                return -2; //密码错误
            }
        } else {
            if(empty($user)){
                return -1; //用户不存在
            }else if($user['lock_status'] == 0 ){
                return -4;//被禁用
            }
        }
    }
    /**
     * [login_app app登录]
     * @param  [type] $account  [description]
     * @param  [type] $password [description]
     * @param  string $type     [description]
     * @return [type]           [description]
     * @author [yyh] <[<email address>]>
     */
    public function login_app($account,$password,$type=''){
        $map['account'] = $account;
        /* 获取用户数据 */
        $user = $this->where($map)->find();
        if($user==''){
            return -1000;
        }
        if(is_array($user) && $user['lock_status']){
            $ss['old_pass']=think_ucenter_md5($password, UC_AUTH_KEY);
            $ss['pass']=$user['password'];
            /* 验证用户密码 */
            if(think_ucenter_md5($password, UC_AUTH_KEY) === $user['password'] || $type == 3){
                $this->updateLogin($user['id']); //更新用户登录信息
                $this->autoLogin($user);
                $this->user_login_record($user,$type,$game_data['id'],$game_data['game_name']);
                return $user['id']; //登录成功，返回用户ID
            } else {
                return -10021; //密码错误
            }
        } else {
            if($user['lock_status'] == 0 ){
                return -1001;//被禁用
            }
        }
    }
    //第三方登录
    public function third_login($login_data){
        $map['openid'] = $login_data['openid'];
        /* 获取用户数据 */
        $user = $this->field('id,fgame_id,fgame_name,account,nickname')->where($map)->find();
        if(is_array($user)){
            if($user['fgame_id']==0&&$login_data['fgame_id']!=0&&$login_data['fgame_name']!=''){
                $this->update_third_Login($user['id'],$login_data['nickname'],$login_data['fgame_id'],$login_data['fgame_name']); //更新用户登录信息
            }else{
                $this->update_third_Login($user['id'],$login_data['nickname']); //更新用户登录信息
            }
            $this->autoLogin($user);
            return $user['id']; //登录成功，返回用户ID
        } else {
            if(empty($user)){
                $data['account']  = $login_data['account'];
                $data['password'] = $login_data['account'];
                $data['nickname'] = $login_data['nickname'];
                $data['phone']    = "";
                $data['sex'] = $login_data['sex'];
                $data['openid']   = $login_data['openid'];
                $data['promote_id'] = $login_data['promote_id'];
                $data['parent_id'] = $login_data['parent_id'];
                $data['promote_account']  = $login_data['promote_account'];
                $data['third_login_type'] = $login_data['third_login_type'];
                $data['register_way'] = $login_data['register_way'];
                $data['fgame_id'] = $login_data['fgame_id'];
                $data['head_icon']=$login_data['head_icon'];
                $data['fgame_name'] = $login_data['fgame_name'];
                $data['is_union'] = $login_data['is_union'];
                return $this->register($data);
            }
        }
    }
    
    /**
     *第三方用户登录/注册
     */
    public function tr_register($userinfo){
        $data = array(
            'account'    => $userinfo['account'],
            'password'   => $userinfo['account'],
            'nickname'   => $userinfo['nickname'],
            'promote_id' => $userinfo['promote_id'],
            'promote_account' => $userinfo['promote_account'],
            'register_way' => $userinfo['register_way'],
            'register_type' => $userinfo['register_type'],
            'register_ip'  => get_client_ip(),
            'parent_id'=>get_fu_id($userinfo['promote_id']),
            'parent_name'=>get_parent_name($userinfo['$promote_id']),
            'fgame_id'  =>$userinfo['game_id'],
            'fgame_name'=>get_game_name($userinfo['game_id']),
            'sdk_version'=>$userinfo['sdk_version'],
            'openid'    =>$userinfo['openid'],
            'imei'      =>(isset($userinfo['imei']) ? $userinfo['imei'] : '')
        );
        /* 添加用户 */
        if($this->create($data)){
            $uid = $this->add();
            $u_user['uid']=$uid;
            $u_user['account']=$userinfo['account'];
            $u_user['password']=think_encrypt($userinfo['password']);
            M('user_pwd')->add($u_user);
            $this->autoLogin($uid);
            return $uid ? ['uid'=>$uid,'password'=>$data['password']] : 0; //0-未知错误，大于0-注册成功
        } else {
            return $this->getError(); //错误详情见自动验证注释
        }
    }
    public function login_1($account,$password,$type=1,$game_id,$game_name,$sdk_version){
        $map['account'] = $account;
        /* 获取用户数据 */
        $user = $this->where($map)->find();
        if(is_array($user) && $user['lock_status']){
            /* 验证用户密码 */
            if(think_ucenter_md5($password, UC_AUTH_KEY) === $user['password']||$type==2){
                $token = $this->updateLogin_($user['id'],$account,$password,$user['fgame_id'],$game_id,$game_name); //更新用户登录信息
                $this->user_login_record1($user,$type,$game_id,$game_name,$sdk_version);
                return array("user_id"=>$user['id'],"token"=>$token); //登录成功，返回用户ID
            } else {
                return -2; //密码错误
            }
        } else {
            return -1; //用户不存在或被禁用
        }
    }
    /**
    *修改用户信息
    */
    public function updateUser($data){
        $c_data = $this->create($data);
        if(empty($data['password'])){
            unset($c_data['password']);
        }
        elseif(isset($data['register_type'])){
            
        }
        else {
            if(!$this->verifyUser($data['id'],$data['old_password'])){
               return -2;
            }else{
                $u_map['uid']=$data['id'];
                M('user_pwd')->where($u_map)->setField('password',think_encrypt($c_data['password']));
            }
        }
        return  $this->where("id=".$data['id'])->save($c_data);
    }
    protected function update_third_Login($uid,$nickname,$fgame_id='',$fgame_name=''){
        $model = M('User','tab_');
        $data["id"] = $uid;
        $data['nickname'] = $nickname;
        $data["login_time"] = NOW_TIME;
        $data["fgame_id"] = $fgame_id;
        $data["fgame_name"] = $fgame_name;
        $data["login_ip"]   = get_client_ip();
        $model->save($data);

    }
    /**
    *更新玩家信息
    */
    public function updateInfo($data){
        $new_data = $this->create($data);
         if(empty($data['password'])){
            unset($new_data['password']);
        }else{
            think_encrypt($new_data['password']);
            
            //新版没有该表
            $u_map['uid']=$data['id'];
        }
        $return = M('User','tab_')->save($new_data);
        return $return;
    }
    /**
     * [updatePassword 更改密码]
     * @param  [type] $id       [description]
     * @param  [type] $password [description]
     * @return [type]           [description]
     */
    public function updatePassword($id,$password) {
        $map['id']=$id;
        $data['password']=think_ucenter_md5($password, UC_AUTH_KEY);
        $return = $this->where($map)->save($data);
        
        if ($return === false){
            return false;
        }else{

            return true;
        }
    }
    /**
     * 验证用户密码
     * @param int $uid 用户id
     * @param string $password_in 密码
     * @return true 验证成功，false 验证失败
     * @author huajie <banhuajie@163.com>
     */
    protected function verifyUser($uid, $password_in){
        $password = $this->getFieldById($uid, 'password');
        if(think_ucenter_md5($password_in, UC_AUTH_KEY) === $password){
            return true;
        }
        return false;
    }
    //用户登录记录
    public function user_login_record($data,$type,$game_id,$game_name){
        $data=array(
            'user_id'=>$data['id'],
            'user_account'=>$data['account'],
            'user_nickname'=>$data['nickname'],
            'game_id'=>$game_id,
            'game_name'=>$game_name,
            'server_id'=>null,
            'type'=>$type,
            'server_name'=>null,
            'promote_id'=>$data['promote_id'],
            'login_time'=>NOW_TIME,
            'login_ip'=>get_client_ip(),
        );
            $uid =M('user_login_record','tab_')->add($data);
            return $uid ? $uid : 0; //0-未知错误，大于0登录记录成功
    }
     //用户登录记录
    public function user_login_record1($data,$type,$game_id,$game_name,$sdk_version){
        $data=array(
            'user_id'=>$data['id'],
            'user_account'=>$data['account'],
            'user_nickname'=>$data['nickname'],
            'game_id'=>$game_id,
            'promote_id'=>$data['promote_id'],
            'game_name'=>$game_name,
            'sdk_version'=>$sdk_version,
            'server_id'=>null,
            'type'=>$type,
            'server_name'=>null,
            'login_time'=>NOW_TIME,
            'login_ip'=>get_client_ip(),
        );
        return 1; //0-未知错误，大于0登录记录成功
    }
    /**
     * [updateLogin 更新user表字段]
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    protected function updateLogin($uid){
        $model = M('User','tab_');
        $data["id"] = $uid;
        $data["login_time"] = NOW_TIME;
        $data["login_ip"]   = get_client_ip();
        $model->save($data);
    }
    //判断game_id是否有值
    protected function updateLogin_($uid,$account,$password,$user_fgame_id,$game_id,$game_name){
        $model = M('User','tab_');
        $data["id"] = $uid;
        $data["login_time"] = NOW_TIME;
        $data["login_ip"] = get_client_ip();
        $data["token"] = $this->generateToken($uid,$account,$password);
        if($user_fgame_id){
            $model->save($data);
        }else{
            $data['fgame_id']=$game_id;
            $data['fgame_name']=$game_name;
            $model->save($data);
        }
        return $data["token"];
    }
    /**
     *随机生成token
     */
    protected function generateToken($user_id,$account,$password){
        $str = $user_id.$account.$password.NOW_TIME.sp_random_string(7);
        $token = MD5($str);
        return $token;
    }
    /**
     * 自动登录用户
     * @param  integer $user 用户信息数组
     */
    private function autoLogin($user){
        /* 记录登录SESSION和COOKIES */
        $auth = array(
            'user_id'   => $user['id'],
            'account'   => $user['account'],
            'nickname'  => $user['nickname'],
        );
        session('user_auth', $auth);
        session('user_auth_sign', data_auth_sign($auth));
    }
    /**
     * 用户token生成
     * @author huajie <banhuajie@163.com>
     */
    public function get_token($account,$is_uc,$day = 7){
        $end_time = 60 * 60 * 60 * 24 * $day;
        $info['account'] = $account;
        $info['is_uc'] = $is_uc;
        
        $result = $token = think_encrypt(json_encode($info),UC_AUTH_KEY,$end_time);
        return $result;
    }
    /**
     * 用户分析
     * @param integer  $start           开始时间(时间戳)
     * @param integer  $end                 结束时间(时间戳)
     * @param integer  $promote_id  推广员编号
     * @param integer  $game_id         游戏编号
     * @return  array   用户分析结果集
     * @author 鹿文学 change yyh
     */
    public function user($start,$end,$promote_id='',$game_id='') {

        $data = [];

        $datelist = get_date_list($start,$end);
        $map2=[];
        $map = array();
        if ($promote_id) {
            $map2['tab_user_login_record.promote_id'] = $map2['tab_user.promote_id']=$map['promote_id'] = $promote_id;
        }

        if ($game_id) {
            $map2['tab_user_login_record.game_id']=$map['fgame_id'] = $game_id;
        }

        // 老用户
        $old = $this->totalPlayerByGroup($datelist,array_merge($map2,array('tab_user_login_record.login_time'=>['between',[$start,$end+86399]])),'old','time',1,'time',true);
        $week = $this->getActiveBeforeSetDate($datelist,6,$start,$end,$map2,'week','time',true);
        $month = $this->getActiveBeforeSetDate($datelist,29,$start,$end,$map2,'month','time',true);
        foreach ($datelist as $key => $value) {
            $one = $this->active(['tab_user_login_record.login_time'=>['between',[strtotime($value),strtotime($value)+86399]]],$promote_id,$game_id);
            $data[$key]['time'] = $value;
            $data[$key]['news'] = 0 ;
            $data[$key]['old'] = 0;
            $data[$key]['dau'] = $one;
            foreach ($old as $v) {
                if ($v['time'] == $value) {
                    $data[$key]['old'] = $v['old'];
                    break;
                }
            }
            $data[$key]['wau'] = 0;
            foreach ($week as $k => $v) {
                if ($v['time']==$value) {
                    $data[$key]['wau'] += $v['week'];break;
                }
            }
            $data[$key]['mau'] = 0;
            foreach ($month as $k => $v) {
                if ($v['time']==$value) {
                    $data[$key]['mau'] += $v['month'];break;
                }
            }
        }

        // 新增用户
        $user = $this->newsAdd(array_merge($map,array('register_time'=>['between',[$start,$end+86399]])));
        if ($user) {
            foreach($data as $key => $value) {
                foreach($user as $k => $v) {
                    if ($v['time']==$value['time']) {
                        $data[$key]['news'] = $v['news'];
                        break;
                    }
                }
            }
        }

        krsort($data);

        return $data;

    }
    /*
     * 活跃排行(推广员)
     * @param  oneday    是否
     * @param  string    $timestr      时间条件  (between 开始时间戳 and 结束时间戳)
     * @param  string    $fieldname    名称
     * @param  array     $promoteid      推广员编号数组
     * @param  integer   $row                限制条数（0:不限）
     * @param  string    $ordermark    排序（asc:升序，desc:降序）
     * @author 鹿文学
     */
    public function activeRankOnPromote($oneday=0,$timestr,$fieldname='count',$promoteid='',$row=0,$ordermark='desc') {
        $map['tab_user.promote_id']=array('gt',0);
        
        if ($promoteid[0]) {$map['tab_user.promote_id']=array('in',$promoteid);}
        
        if($oneday){
            $lasttimearr = explode(' ',trim($timestr));
            $lasttime = $lasttimearr[1];

            $mid = $this->field('tab_user.promote_id,promote_account,tab_user.id,uu.login_time')
                ->join('tab_user_login_record as uu on uu.user_id=tab_user.id and uu.login_time>register_time','inner')
                ->join('tab_promote on(tab_user.promote_id = tab_promote.id)')
                ->where(['register_time'=>['lt',$lasttime],$map])
                ->where(['uu.login_time '.$timestr,$map])
                ->where("FROM_UNIXTIME(uu.login_time,'%Y-%m-%d') > FROM_UNIXTIME(tab_user.register_time,'%Y-%m-%d')")
                ->group('tab_user.promote_id,tab_user.id')
                ->select(false);

        }else{

            $lasttimearr = explode(' ',trim($timestr));
            $lasttime = $lasttimearr[3];

            $mid = $this->field('tab_user.promote_id,promote_account,tab_user.id,uu.login_time')
                ->join('tab_user_login_record as uu on uu.user_id=tab_user.id and uu.login_time>register_time','inner')
                ->join('tab_promote on(tab_user.promote_id = tab_promote.id)')
                ->where(['register_time'=>['lt',$lasttime],$map])
                ->where(['uu.login_time '.$timestr,$map])
                ->where("FROM_UNIXTIME(uu.login_time,'%Y-%m-%d') > FROM_UNIXTIME(tab_user.register_time,'%Y-%m-%d')")
                ->group('tab_user.promote_id,tab_user.id')
                ->select(false);
        }


        $last = $this->table('('.$mid.') as a')->field('a.promote_id,a.promote_account,GROUP_CONCAT(a.id),count(a.id) as '.$fieldname.',a.login_time')
                    ->group('a.promote_id')->select(false);

        if($row>0) {
            
            $data = $this->table('('.$last.') as b')->limit($row)->order('b.'.$fieldname.' '.$ordermark.',b.login_time')->select();
            
            
        }   else {

            $data = $this->table('('.$last.') as b')->order('b.'.$fieldname.' '.$ordermark.',b.login_time ')->select();
            
        }

        return $data;
    }
    /*
     * 活跃排行(游戏)
     * @param  string    $timestr      时间条件  (between 开始时间戳 and 结束时间戳)
     * @param  string    $fieldname    名称
     * @param  array     $promoteid      游戏编号数组
     * @param  integer   $row                限制条数（0:不限）
     * @param  string    $ordermark    排序（asc:升序，desc:降序）
     * @author 鹿文学
     */
    public function activeRankOnGame($oneday=0,$timestr,$fieldname='count',$gameid='',$row=0,$ordermark='desc') {

        $map2['fgame_id']=$map['uu.game_id']=array('gt',0);
        if ($gameid[0]) {$map2['fgame_id']=$map['uu.game_id']=array('in',$gameid);}

        if($oneday){
            $lasttimearr = explode(' ',trim($timestr));
            $lasttime = $lasttimearr[3];

            $mid = $this->field('uu.game_id,uu.game_name,tab_user.id')
                ->join('tab_user_login_record as uu on uu.user_id=tab_user.id and uu.login_time>register_time','inner')
                ->where(['register_time'=>['lt',$lasttime],$map2])
                ->where(['uu.login_time '.$timestr,$map])
                ->where("FROM_UNIXTIME(uu.login_time,'%Y-%m-%d') > FROM_UNIXTIME(register_time,'%Y-%m-%d')")
                ->group('uu.user_id,uu.game_id')
                ->order('uu.login_time')
                ->select(false);
        }else{

            $lasttimearr = explode(' ',trim($timestr));
            $lasttime = $lasttimearr[3];

            $mid = $this->field('uu.game_id,uu.game_name,tab_user.id')
                ->join('tab_user_login_record as uu on uu.user_id=tab_user.id and uu.login_time>register_time','inner')
                ->where(['register_time'=>['lt',$lasttime],$map2])
                ->where(['uu.login_time '.$timestr,$map])
                ->where("FROM_UNIXTIME(uu.login_time,'%Y-%m-%d') > FROM_UNIXTIME(register_time,'%Y-%m-%d')")
                ->group('uu.user_id,uu.game_id')
                ->order('uu.login_time')
                ->select(false);
        }
        $last = $this->table('('.$mid.') as a')->field('a.game_id,a.game_name,GROUP_CONCAT(a.id),count(a.id) as '.$fieldname)
            ->group('a.game_id')->select(false);
        if($row>0) {

            $data = $this->table('('.$last.') as b')->limit($row)->order('b.'.$fieldname.' '.$ordermark)->select();


        }   else {

            $data = $this->table('('.$last.') as b')->order('b.'.$fieldname.' '.$ordermark)->select();

        }

        return $data;

    }
    /*
     * 新用户 按时间分组
     * @param  array    $map      条件数组
     * @param  string   $field    字段别名
     * @param  string   $group    分组字段名
     * @author 鹿文学
     */
    public function newsAdd($map=array(),$field='news',$group='time',$flag=1,$order='time') {
        
        switch($flag) {
            case 2:{$dateform = '%Y-%m';};break;
            case 3:{$dateform = '%Y-%u';};break;
            case 4:{$dateform = '%Y';};break;
            case 5:{$dateform = '%Y-%m-%d %H';};break;
            default:$dateform = '%Y-%m-%d';
        }
        
        $user = $this->field('FROM_UNIXTIME(register_time, "'.$dateform.'") as '.$group.',group_concat(id) as id ,COUNT(id) AS '.$field)
                    ->where($map)
                    ->group($group)
                    ->order($order)
                    ->select();
        
        return $user;
        
    }

    /**
     * 分组统计玩家
     * @param  array    $map      条件数组
     * @param  string   $fieldname  字段别名
     * @param  string   $group      分组字段名
     * @param  integer  $flag           时间类别（1：天，2：月，3：周）
     * @param  string   $order      排序
     * @return array       详细数据
     * @author 鹿文学
     */
    public function totalPlayerByGroup($datelist,$map,$fieldname='count',$group='time',$flag=1,$order='time') {
            switch($flag) {
                case 2:{$dateform = '%Y-%m';};break;
                case 3:{$dateform = '%Y-%u';};break;
                case 4:{$dateform = '%Y';};break;
                case 5:{$dateform = '%Y-%m-%d %H';};break;
                default:$dateform = '%Y-%m-%d';
            }
            if (strpos($order,'desc') !== false) {rsort($datelist);}
            
            foreach($datelist as $k => $v) {
                $data[$k]=[$group=>$v,$fieldname=>0];
            }
            if ($flag == 5) {
                $start = $map['tab_user_login_record.login_time'];
                $map['tab_user.register_time'] = array('lt',$start[1][0]);
                $sql = $this->field('FROM_UNIXTIME(tab_user_login_record.login_time,"'.$dateform.'") as '.$group.',tab_user_login_record.user_id,FROM_UNIXTIME(register_time,"'.$dateform.'") as rtime')
                        
                            ->join('tab_user_login_record on tab_user_login_record.user_id=tab_user.id and tab_user_login_record.login_time>register_time','inner')
                            
                            ->where($map)->group('tab_user_login_record.user_id')->order('tab_user_login_record.login_time')->select(false);

                $list = $this->table('('.$sql.') as a ')->field('a.time,GROUP_CONCAT(a.user_id) as list,count(distinct a.user_id) as '.$fieldname)->where('UNIX_TIMESTAMP(a.rtime)<UNIX_TIMESTAMP(a.'.$group.')')->group('a.'.$group)->order($order)->select();
                
            } elseif ($flag == 2) {
                
                $sql = $this->field('FROM_UNIXTIME(tab_user_login_record.login_time,"'.$dateform.'") as '.$group.',FROM_UNIXTIME(tab_user_login_record.login_time,"%Y-%m-%d") AS ttime,tab_user_login_record.user_id,FROM_UNIXTIME(register_time,"'.$dateform.'") as rtime,FROM_UNIXTIME(register_time, "%Y-%m-%d") AS trtime')
                        
                            ->join('tab_user_login_record on tab_user_login_record.user_id=tab_user.id and tab_user_login_record.login_time>register_time','inner')
                            
                            ->where($map)->order('tab_user_login_record.login_time')->select(false);
                
                $mid = $this->table('('.$sql.') as a ')

                            ->where('a.trtime < a.ttime')->group('a.user_id,a.'.$group)->select(false);
                            
                $list = $this->table('('.$mid.') as b ')->field('b.rtime,b.'.$group.',count(distinct b.user_id) as '.$fieldname)
                
                            ->group('b.'.$group)->order($order)->select();
                
            } else {
                
                $sql = $this->field('FROM_UNIXTIME(tab_user_login_record.login_time,"'.$dateform.'") as '.$group.',tab_user_login_record.user_id,FROM_UNIXTIME(register_time,"'.$dateform.'") as rtime')
                        
                            ->join('tab_user_login_record on tab_user_login_record.user_id=tab_user.id and tab_user_login_record.login_time>register_time','inner')
                            
                            ->where($map)->order('tab_user_login_record.login_time')->select(false);

                $mid = $this->table('('.$sql.') as a ')->field('a.time,GROUP_CONCAT(user_id) as list')->where('UNIX_TIMESTAMP(a.rtime)<UNIX_TIMESTAMP(a.'.$group.')')->group('a.user_id,a.'.$group)->select(false);

                $list = $this->table('('.$mid.') as b ')->field('b.'.$group.',count(distinct b.list) as '.$fieldname)

                            ->group('b.'.$group)->order($order)->select();
            
            }
            if ($list[0]) {
                if ($flag == 5) {
                    foreach ($data as $k => $v) {
                        foreach($list as $lv) {
                            $time = explode(' ',$lv[$group]);
                            if ($time[1] == $v[$group]){
                                $data[$k][$fieldname] += (integer)$lv[$fieldname];
                                break;
                            }
                        }
                    }
                    
                } else {
                    foreach ($data as $k => $v) {
                        foreach($list as $lv) {
                            if ($v[$group] == $lv[$group]){
                                $data[$k][$fieldname] += (integer)$lv[$fieldname];
                                break;
                            }
                        }
                    }
                    
                }
            }
                
            
            return $data;
    }


    /**
     * LTV统计
     * @param integer  $start  			开始时间(时间戳)
     * @param integer  $end  				结束时间(时间戳)
     * @return 	array 	用户分析结果集
     * @author 郭家屯
     */
    public function new_ltv($start,$end) {
        $map['time'] = array('between',array(date('Y-m-d',strtotime('-29 day',$start)),date('Y-m-d',strtotime('+30 day',$end))));
        $record = M('ltv','tab_')->field('money as amount,active_count as active,active_id,new_count,new_id,time')->where($map)->order('time asc')->select();
        $day =  floor(($start-strtotime($record[0]['time']))/86400);
        $data = array();
        $datelist = get_date_list($start,$end);
        foreach ($record as $key=>$v){
            if($v['time']< date('Y-m-d',$start)){
                $data[$key]['time'] = $v['time'];
                $data[$key]['amount'] = $v['amount'];
                $data[$key]['active'] = 0;
            }
            if($v['time']>= date('Y-m-d',$start) && $v['time']<= date('Y-m-d',$end)){
                $data[$key]['time'] = $v['time'];
                $data[$key]['amount'] = $v['amount'];
                $data[$key]['active'] = $v['active'];
                $ltvactive = array();
                for ($i=1;$i<31;$i++){
                    $active = array();
                    for($j=0;$j<$i;$j++){
                        $active[] = explode(',',$record[$key-$j]['active_id']);
                    }
                    $ltvactive[$i] = count(array_unique(array_reduce($active, 'array_merge', array())));
                }
                $data[$key]['ltvactive'] = $ltvactive;
            }
        }
        //dump($data);
        foreach ($datelist as $key=>$v){
            $subscript = 0;
            foreach ($record as $k=>$vo) {
                if($vo['time'] == $v) {
                    $subscript = $k;
                }
            }
            for($i=1;$i<31;$i++){
                if($record[$subscript+$i]){
                    $new = explode(',',$record[$subscript]['new_id']);
                    $active = explode(',',$record[$subscript+$i]['active_id']);
                    $count = count(array_intersect($new,$active));
                    $rate['rate'.$i]= $count/$record[$subscript]['new_count'] * 100;
                }
                $ratention[$v] = $rate;
            }
        }
        foreach ($data as $k => $v) {
            if ($k<$day) {continue;}
            foreach($ratention as $i => $j) {
                if($i == $v['time']) {
                    $am = 0;$ac = 0;$rate = 1;
                    for($m=1;$m<31;$m++) {
                        if (strtotime($i)+$m*86400>mktime(0,0,0,date('m'),date('d'),date('Y'))){continue;}
                        $am += $data[$k-$m+1]['amount'];
                        //$ac += $data[$k-$m+1]['active'];
                        $ac = $data[$k]['ltvactive'][$m];
                        $rate += $j['rate'.$m]/100;
                        if ($m<8 || $m == 14 || $m == 30) {
                            $data[$k]['ltv'.$m] = round($am/$ac*$rate,2);
                        }
                    }

                }
            }
        }

        $data = array_slice($data,$day);
        krsort($data);
        return $data;
    }

    /**
     * LTV统计
     * @param integer  $start           开始时间(时间戳)
     * @param integer  $end                 结束时间(时间戳)
     * @return  array   用户分析结果集
     * @author 鹿文学 change yyh
     */
    public function ltv($start,$end) {

        $datelist = get_date_list(strtotime('-29 day',$start),$end);

        foreach ($datelist as $k => $v) {

            $data[$k]['time'] = $v;

            $data[$k]['amount'] = 0;

            $data[$k]['active'] = 0;
            if ($k>28)
                $ratentionRate[$v]=array();

        }

        $between = ['between',[strtotime('-29 day',$start),$end+86399]];

        // 新增用户
        $news = $this->newsAdd(array('register_time'=>['between',[$start,$end+86399]]));

        // 活跃用户
        $active = $this->ltvActive(array_slice($datelist,29));

        // 收入
       // $spend = D('Spend')->allAmountByGroup(array('pay_time'=>$between,'pay_way'=>['gt',0]));
        $spend = D('Spend')->allAmountByGroup(array('pay_time'=>$between,'pay_way'=>['gt',0]));
        // 留存率
        $ratention = $this->ratentionRateData($news,$ratentionRate);


        foreach ($data as $k => $v) {
            foreach($active as $j => $i) {
                if ($j == $v['time']) {
                    $data[$k]['active'] = $i[1];
                    $data[$k]['ltvactive'] = $i;
                    break;
                }
            }
            foreach($spend as $i) {
                if($i['time'] == $v['time']) {
                    $data[$k]['amount'] = $i['amount'];break;
                }
            }
        }

        // ARPU = 总收入/总活跃用户
        // LTV = LTV周期内平均活跃用户ARPU * (1+1日留存%+2日留存%+....+n日留存%)
        // 20180308日的LTV30=（20170207-20180308）周期内平均ARPU*（1+次留%+....+30日留存%）
        foreach ($data as $k => $v) {
            if ($k<29) {continue;}
            foreach($ratention as $i => $j) {
                if($i == $v['time']) {
                    $am = 0;$ac = 0;$rate = 1;
                    for($m=1;$m<31;$m++) {
                        if (strtotime($i)+$m*86400>mktime(0,0,0,date('m'),date('d'),date('Y'))){continue;}
                        $am += $data[$k-$m+1]['amount'];
                        //$ac += $data[$k-$m+1]['active'];
                        $ac = $data[$k]['ltvactive'][$m];
                        $rate += $j['rate'.$m]/100;
                        if ($m<8 || $m == 14 || $m == 30) {
                            if($ac!=0){
                                $data[$k]['ltv'.$m] = round($am/$ac*$rate,2);
                            }else{
                                $data[$k]['ltv'.$m] = '0';
                            }
                        }
                    }

                }
            }
        }

        $data = array_slice($data,29);

        krsort($data);

        return $data;

    }
    // yyh
    public function ltvActive($datelist) {
        foreach($datelist as $v) {
            for($i=1;$i<31;$i++) {
                $end = strtotime($v);
                $data[$v][$i] = $this->active(['tab_user_login_record.login_time'=>['between',[strtotime('-'.($i-1).' day',$end),$end+86399]]]);
            }
        }

        return $data;

    }
    /*
     * 用户总数
     * @param  array    $map      条件数组
     * @author 鹿文学 change yyh
     */
    public function old($map=array()) {

        return $this->field('id')->where($map)->count();

    }
    
    /*
     * 活跃用户总数
     * @param  array    $map      条件数组
     * @author 鹿文学 change yyh
     */
    public function active($map=array(),$promote_id='',$game_id='') {
        $data = M('user_login_record','tab_')->where($map)->count('distinct(user_id)');
        return $data;
//        $start = $map['tab_user_login_record.login_time'];
//        if($promote_id){
//            $map['tab_user.promote_id'] =  $promote_id;
//            $map1['promote_id'] = $promote_id;
//        }
//        if($game_id){
//            $map['tab_user.fgame_id'] = $game_id;
//            $map1['fgame_id'] = $game_id;
//        }
//        $map['register_time'] = array('lt',$start[1][0]);
//        $data = $this->field('FROM_UNIXTIME(tab_user_login_record.login_time,"%Y-%m-%d") as time,group_concat(tab_user.id) as list,count(distinct tab_user.id)as count,FROM_UNIXTIME(register_time,"%Y-%m-%d") as rtime')
//
//            ->join('tab_user_login_record on tab_user_login_record.user_id=tab_user.id','inner')
//
//            ->where($map)->order('tab_user_login_record.login_time')->select();
//
//        $map1['register_time'] = $start;
//
//        $news = $this->old($map1);
//        return $data[0]?$data[0]['count']+$news:0+$news;

    }

    /*
     * 活跃用户总数2
     * @param  array    $map      条件数组
     * @author zsl  chnage yyh
     */
    public function active2($map=array()) {

        if($map['tab_user.promote_id'] || $map['tab_user_login_record.game_id']){

            $data = $this->field('FROM_UNIXTIME(tab_user_login_record.login_time,"%Y-%m-%d") as time,group_concat(tab_user.id) as list,count(distinct tab_user.id)as count,FROM_UNIXTIME(register_time,"%Y-%m-%d") as rtime')

                ->join('tab_user_login_record on tab_user_login_record.user_id=tab_user.id','inner')

                ->where($map)->order('tab_user_login_record.login_time')->select();


            return $data[0]?$data[0]['count']:0;

        }else{

            $usermap['register_time'] = $map['tab_user_login_record.login_time'];
            $recordmap['login_time'] = $map['tab_user_login_record.login_time'];

            $sql = $this->field('id as user_id')->where($usermap)->select(false);

            $a = M('user_login_record','tab_')
                ->field('user_id')
                ->where($recordmap)
                ->group('user_id')
                ->union($sql)
                ->select();
            $count = count($a);

            return $count?$count:0;

        }



    }
    /**
     * 玩家1-7,14,30天留存率
     * @param  array    $news       新增用户数据(按天分组)
     * @param  array    $rate       留存率初始化数组(按天分组)
     * @return array       详细数据
     * @author 鹿文学 change yyh
     */
    public function ratentionRateData($news,$rate) {

        $ratention = [];

        for($i=1;$i<31;$i++) {
            $ratention = array_merge($ratention,$this->ratentionRate($news,[],$i,'rate'.$i));
        }

        foreach($rate as $k => $v) {
            foreach($ratention as $i) {if($k==$i['login_time']){$rate[$k][$i['key']] = $i[$i['key']];continue;}}
        }

        return $rate;

    }
    /**
     * 玩家留存率
     * @param  array    $news       新增用户数据（按天分组）
     * @param  array    $map        条件数组
     * @param  integer  $flag       留存类型（1：1天，3：3天，7：7天）
     * @param  string   $fieldname  字段别名
     * @param  string   $group      分组字段名
     * @return array       详细数据
     * @author 鹿文学 change yyh
     */
    public function ratentionRate($news,$map,$flag=1,$fieldname='retentionrate',$group='login_time') {

        $map['lock_status']=1;

        $data = array();

        foreach ($news as $value) {
            $ct1 = strtotime("+$flag day",strtotime($value['time']));
            $ct2 = strtotime("+1 day",$ct1)-1;

            $map['tab_user_login_record.login_time'] = array(array('egt',$ct1),array('elt',$ct2));

            $map['user_id']=array('in',$value['id']);
            $count = count(explode(',',$value['id']));

            $d=$this
                ->field('count(distinct user_id) as '.$fieldname.' ,FROM_UNIXTIME(tab_user_login_record.login_time,"%Y-%m-%d") as '.$group)
                ->join('tab_user_login_record on tab_user.id=tab_user_login_record.user_id','right')
                ->where($map)
                ->group($group)
                ->select();

            if ($d)
                $data[]=array(
                    $group=>$value['time'],'key'=>$fieldname,
                    $fieldname=>($d[0][$fieldname]==0)?0:sprintf("%.2f",($d[0][$fieldname]/$count)*100)
                );

        }

        return $data;
    }
    /**
     * 分组统计玩家
     * @param  string   $type       减去天数
     * @param  integer  $start      开始时间戳
     * @param  integer  $end            结束时间戳
     * @param  array    $map        条件数组
     * @param  string   $fieldname  字段别名
     * @param  string   $group      分组字段名
     * @return array       详细数据
     * @author 鹿文学 change yyh
     */
    public function getActiveBeforeSetDate($datelist,$type,$start,$end,$map,$fieldname='count',$group='time') {


        foreach ($datelist as $k => $v) {

            $t = strtotime($v);

            $map['tab_user_login_record.login_time'] = ['between',[strtotime('-'.$type.' day',$t),$t+86399]];

            $count = $this->active2($map);

            $data[$k] = [$group=>$v,$fieldname => $count];

        }

        return $data;

    }

    /**
     * 修改平台币
     */
    public function edit_user_balance_coin($account,$num,$type,$sid=0){
        //开启事务
        $this->startTrans();
        $map['account'] = $account;
        $data = $this->where($map)->find();
        if($type == 1){
            $data['balance'] += (int)$num;
            $res = M('User','tab_')->where($map)->save($data);
        }
        if($type == 2){
            $data['balance'] -= (int)$num;
            if($data['balance'] < 0){
                $this->error = "该用户平台币小于所要扣除的平台币！";
                $this->rollback();
                return false;
            }
            $res = $this->where($map)->save($data);
        }
        $rec = D('UserCoin')->record($account,$sid,$num,$type);
        if($res && $rec){
            //事务提交
            $this->commit();
            return true;
        }else{
            //事务回滚
            $this->rollback();
            return false;
        }
    }

}