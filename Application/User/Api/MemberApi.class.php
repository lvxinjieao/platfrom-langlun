<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace User\Api;
use User\Api\Api;
use Admin\Model\UserModel;

class MemberApi extends Api{
    /**
     * 构造方法，实例化操作模型
     */
    protected function _init(){
        $this->model = new UserModel();
    }

    /**
     * 注册一个新用户
     * @param  string $account 用户名
     * @param  string $password 用户密码
     * @return integer          注册成功-用户信息，注册失败-错误编号
     */
    public function register($data=array(),$type=''){
        return $this->model->register($data,$type);
    }
    /**
     * 注册一个新用户user表加game_id
     * @param  string $account 用户名
     * @param  string $password 用户密码
     * @return integer          注册成功-用户信息，注册失败-错误编号
     */
    public function register_($account, $password,$register_way=1,$register_type=2,$promote_id=0,$promote_account="自然注册",$phone="",$game_id,$game_name,$sdk_version=0,$imei=''){
        return $this->model->register_($account,$password,$register_way,$register_type,$promote_id,$promote_account,$phone,$game_id,$game_name,$sdk_version,$imei='');
    }
    /**
     * 用户登录认证
     * @param  string  $username 用户名
     * @param  string  $password 用户密码
     * @param  integer $type     用户名类型 （1-用户名，2-邮箱，3-手机，4-UID）
     * @return integer           登录成功-用户ID，登录失败-错误编号
     */
    public function login($account, $password,$type=2,$gid){
        return $this->model->login($account, $password,$type,$gid);
    }

    public function third_login($data = array()){
        return $this->model->third_login($data);
    }
    public function tr_register($data){
        return $this->model->tr_register($data);
    }
    /**
    *修改数据
    */
    public function updateUser($data=""){
        return $this->model->updateUser($data);
    }
    public function login_($account, $password,$type=2,$game_id=0,$game_name='',$sdk_version){
        return $this->model->login_1($account, $password,$type,$game_id,$game_name,$sdk_version);
    }
    /**
    *忘记密码后更新密码
    */
    public function updatePassword($user_id=0,$password=""){
        return $this->model->updatePassword($user_id,$password);
    }

    /**
     * 获取用户信息
     * @param  string  $uid         用户ID或用户名
     * @param  boolean $is_username 是否使用用户名查询
     * @return array                用户信息
     */
    public function info($uid, $is_username = false){
        return $this->model->info($uid, $is_username);
    }

    /**
     * 检测用户名
     * @param  string  $field  用户名
     * @return integer         错误编号
     */
    /* public function checkUsername($username){
        return $this->model->checkField($username, 1);
    } */
    
    public function checkUsername($username) {
        return $this->model->checkUsername($username);
    }
    
    public function checkPassword($account,$password) {
        return $this->model->checkPassword($account,$password);
    }

    /**
     * 检测邮箱
     * @param  string  $email  邮箱
     * @return integer         错误编号
     */
    public function checkEmail($email){
        return $this->model->checkField($email, 2);
    }

    /**
     * 检测手机
     * @param  string  $mobile  手机
     * @return integer         错误编号
     */
    public function checkMobile($mobile){
        return $this->model->checkField($mobile, 3);
    }

    /**
     * 更新用户信息
     * @param int $uid 用户id
     * @param string $password 密码，用来验证
     * @param array $data 修改的字段数组
     * @return true 修改成功，false 修改失败
     * @author huajie <banhuajie@163.com>
     */
    public function updateInfo($data){
        $return = $this->model->updateInfo($data);
        return $return;
    }


    /**
     * 用于用户签到的时候进行登录(app方)
     */
    public function sign_login($sign){
        $return  = $this->model->sign_login($sign);
        return $return;
    }
    /**
     * 注册一个新用户
     * @param  string $account 用户名
     * @param  string $password 用户密码
     * @return integer          注册成功-用户信息，注册失败-错误编号
     */
    public function app_register($account, $password,$register_way=0,$register_type=2,$nickname="",$sex=0,$promote_id=0){
        return $this->model->app_register($account,$password,$register_way,$register_type,$nickname,$sex,$promote_id);
    }
}
