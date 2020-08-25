<?php
/**
 * Created by PhpStorm.
 * User: xmy
 * Date: 2016/11/4
 * Time: 16:04
 */
namespace SDK\Controller;
use Think\Controller;

class OAController extends Controller{

    /**
     * 游戏列表接口
     */
    public function game_list(){
        $game = M('game','tab_')->field('id,game_name,game_type_id as game_type,IF (`game_status` =0, 2, 1) as status,create_time')->select();
        $this->AjaxReturn($game);
    }

    /**
     * 游戏类型接口
     */
    public function game_type(){
        $type = M('game_type','tab_')->field('id,type_name as name,IF (`status` =- 1, 2, 1) AS status,create_time')->select();
        $this->AjaxReturn($type);
    }

    /**
     * 区服列表
     */
    public function server_list(){
        $server = M('server','tab_')->field('id,game_id,server_name as name,start_time as open_time,IF (`show_status` = 0, 2, 1) AS status')->select();
        $this->AjaxReturn($server);
    }

    /**
     * 渠道列表
     */
    public function promote_list(){
        $promote = M('promote','tab_')->field('id,account,create_time,IF (`status` = 0, 2, 1) as status')->select();
        $this->AjaxReturn($promote);
    }

    /**
     * 注册明细
     * @param regist_detail 渠道帐号
     */
    public function regist_detail(){
        $param = json_decode(base64_decode(I('post.param')),true);
        $map['promote_account'] = array('in',$param['account']);
        $map['register_time'] = array('gt',$param['time']);
        $data = M('user','tab_')->field('account as regist_account,register_ip as regist_ip,fgame_id as regist_game_id,register_time as regist_time,promote_account')->where($map)->select();
        $this->AjaxReturn($data);
    }

    /**
     * 充值明细
     */
    public function recharge_detail(){
        $param = json_decode(base64_decode(I('post.param')),true);
        $map['promote_account'] = array('in',$param['account']);
        $map['pay_time'] = array('gt',$param['time']);
        $map['pay_status'] = 1;
        $data = M('spend','tab_')
            ->field('user_account as recharge_account,game_id as recharge_game_id,server_id as recharge_server_id,promote_account,pay_amount as recharge_money,pay_time as recharge_time')
            ->where($map)
            ->select();
        $this->AjaxReturn($data);
    }
}