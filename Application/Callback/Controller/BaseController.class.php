<?php

namespace Callback\Controller;
use Think\Controller;
use Common\Api\GameApi;
use Common\Api\PayApi;
/**
 * 支付回调控制器
 * @author 小纯洁 
 */
class BaseController extends Controller {

    protected function pay_game_notify($data){
        $game = new GameApi();
        $result = $game->game_pay_notify($data);
        return $result;
    }

    /**
    *充值到游戏成功后修改充值状态和设置游戏币
    */
    protected function set_spend($d){
            #通知游戏
            $rr = false;
            for ($i=1; $i <2 ; $i++) { 
                $return=$this->pay_game_notify($d);
                $result=json_decode($return,true);
                if($result['status']=='success'||$result['msg']=='success'||$result['code']=='1009'||$result['code']=='200'){//msg=sucess以及 code=1009 是白鹭返回，其他都是一般接口
                    $rr = M('spend','tab_')->where(array('pay_order_number'=>$d['pay_order_number']))->save(array('pay_game_status'=>1));
                    if($rr!==false){
                        $rr=true;
                        $this->record_logs($d['pay_order_number'].'共通知cp'. $i.'次，已成功','INFO');
                        break;
                    }else{
                        $this->record_logs($d['pay_order_number'].'共通知cp'. $i.'次，失败','INFO');
                    }
                }else{
                    $this->record_logs($d['pay_order_number'].'共通知cp'. $i.'次，失败','INFO');
                }
            }
            return $rr;
    }

    /**
    *修改订单
    */
    protected function changepaystatus($data,$d,$table){
        $table=M($table,'tab_');
        if(empty($d)){return false;}
        if($data['real_amount']<$d['pay_amount']){$this->record_logs('订单'.$data['out_trade_no'].'实际支付金额低于订单金额');return false;}
        if($d['pay_status'] == 0){
            $data_save['pay_status'] = 1;
            $data_save['order_number'] = $data['trade_no'];
            $map_s['pay_order_number'] = $data['out_trade_no'];
            $r = $table->where($map_s)->save($data_save);
            $PayApi = new PayApi();
            $PayApi->set_ratio($d['pay_order_number']);
            //APP邀请好友消费奖励平台币
            $this->inviteFriendAward($d['user_id'],$d['pay_amount'],$data['out_trade_no']);
            if($r!==false){
                $spend_data = M('spend','tab_')->where($map_s)->find();
                if(!empty($spend_data)){
                    M('user','tab_')->where(array('id'=>$d['user_id']))->setInc('cumulative',$d['pay_amount']);
                    $PayApi->buyshoppoint($spend_data);
                }
                return true;
            }else{
                $this->record_logs('订单'.$data['out_trade_no']."修改数据失败");
                return false;
            }
        }
        else{
            //游戏充值防止掉单
            if($table == 'spend'){
                return true;
            }else{
                //平台币充值防止多次增加
                return false;
            }

        }
    }

    /**
     *修改绑币充值
     */
    protected function changepaystatus2($data,$d,$table){
        $table=M($table,'tab_');
        if(empty($d)){return false;}
        if($data['real_amount']<$d['real_amount']){$this->record_logs('订单'.$data['out_trade_no'].'实际支付金额低于订单金额');return false;}
        if($d['pay_status'] == 0){
            $data_save['pay_status'] = 1;
            $data_save['order_number'] = $data['trade_no'];
            $map_s['pay_order_number'] = $data['out_trade_no'];
            $r = $table->where($map_s)->save($data_save);
            //APP邀请好友消费奖励平台币
            $this->inviteFriendAward($d['user_id'],$d['amount'],$data['out_trade_no']);
            if($r!==false){
                return true;
            }else{
                $this->record_logs('订单'.$data['out_trade_no']."修改数据失败");
                return false;
            }
        }
        else{
            return false;
        }
    }


    /**
     *修改余额充值订单状态
     */
    protected function changepaystatus1($data,$d,$table){
        $table=M($table,'tab_');
        if(empty($d)){return false;}
        if($data['real_amount']<$d['money']){$this->record_logs('余额充值'.$data['out_trade_no'].'实际支付金额低于订单金额');return false;}
        if($d['pay_status'] == 0){
            $data_save['pay_status'] = 1;
            $map_s['pay_order_number'] = $data['out_trade_no'];
            $r = $table->where($map_s)->save($data_save);
            if(!$r){
                $this->record_logs('余额充值'.$data['out_trade_no']."修改数据失败");
                return false;
            }else{
                return true;
            }
        }
        else{
            return false;
        }
    }

    /**
     * 余额充值成功后的设置
     */
    protected function set_balance($d){
        $promote = M("promote","tab_");
        $map['account'] = $d['recharge_account'];
        $promote->where($map)->setInc("balance_coin",$d['money']);
    }
    /**
    *充值平台币成功后的设置
    */
    protected function set_deposit($d){
        $user = M("user","tab_");
        $user->where("id=".$d['user_id'])->setInc("balance",$d['pay_amount']);
        $user->where("id=".$d['user_id'])->setInc("cumulative",$d['pay_amount']);
    }

    /**
    *设置代充数据信息
    */
    protected function set_agent($d){
        $user = M("UserPlay","tab_");
        $map_play['user_id'] = $d['user_id'];
        $map_play['game_id'] = $d['game_id'];
        $user->where($map_play)->setInc("bind_balance",$d['amount']);
    }
    
    /**
     * 获取奖励平台币 总额
     * @param $invite_id
     * @param $user_id
     * @return mixed
     * author: xmy 280564871@qq.com
     */
    public function getUserInviteCoin($invite_id,$user_id){
        $tab_sha=M('share_record','tab_');
        $map['invite_id'] = $invite_id;
        $map['user_id'] = $user_id;
        $data = $tab_sha->field("sum(award_coin) as award_coin")
            ->where($map)
            ->group("user_id")
            ->find();
        return $data['award_coin'];
    }

    /**
     * 绑币充值回调
     * @param $data
     * @return bool
     * author: xmy 280564871@qq.com
     */
    protected function set_bind_recharge($data)
    {
        $user_play = M("user_play", "tab_");
        $map_play['user_id'] = $data['user_id'];
        $map_play['game_id'] = $data['game_id'];
        $user_play->where($map_play)->setInc("bind_balance", $data['real_amount']);
        $user = M("user","tab_");
        $user->where("id=".$data['user_id'])->setInc("cumulative",$data['amount']);
    }

    /**
     * 充值获得积分
     * @param $user_id
     * @param $pay_amount
     * @return bool
     * author: xmy 280564871@qq.com
     */
    public function rechargeAwardPoint($user_id,$pay_amount){
        $point_recorddd=M('point_record','tab_');
        $user = M("user","tab_")->find($user_id);
        if(empty($user_id)){
            return true;
        }
        //奖励用户积分
        $point_type = $this->getPointType("recharge_spend");
        $point = intval($point_type['point'] * $pay_amount);

        $point_recorddd->startTrans();

        if($point > 0){
            $user['point'] += $point;

            //积分记录
            $data['user_id'] = $user_id;
            $data['type_id'] = $point_type['id'];
            $data['point'] = $point;
            $data['create_time'] = time();
            $data['type'] = 1;
            $point_result = M("point_record","tab_")->add($data);//积分记录存储
        }
        $user_result = M("user","tab_")->save($user);//被邀请人积分存储

        if($point_result === false || $user_result === false){
            $point_recorddd->rollback();
            return false;
        }else{
            $point_recorddd->commit();
            return true;
        }
    }

    /**
     * 邀请好友消费奖励平台币
     * @param $user_id
     * @param $pay_amount
     * @param $order_number
     * @return bool
     * author: xmy 280564871@qq.com
     */
    public function inviteFriendAward($user_id,$pay_amount,$order_number){
        $tab_sha=M('share_record','tab_');
        $map['user_id'] = $user_id;
        $share_record = $tab_sha->where($map)->find();
        $this->record_logs(json_encode($share_record));
        if(empty($share_record)){
            return true;
        }
        $invite_id = $share_record['invite_id'];

        //计算奖励
        $award_coin = round($pay_amount * 0.05,2);

        //增加邀请用户 平台币
        $invite = M("user","tab_")->find($invite_id);//邀请人

        //获取该邀请人共获得多少平台币
        $total = $this->getUserInviteCoin($invite_id,$user_id);

        //是否到达上限
        if($total >= 100){
            return true;
        }

        if($total+$award_coin > 100){
            $award_coin = 100 - $total;
        }
//$award_coin=99;
        $invite['balance'] += $award_coin;

        //奖励平台币记录
        $share_record['create_time'] = time();
        $share_record['award_coin'] = $award_coin;
        $share_record['order_number'] = $order_number;
        unset($share_record['id']);

        //开启事务
        $tab_sha->startTrans();
        
        $this->record_logs('----------------');
        $this->record_logs(json_encode($share_record));
        $this->record_logs('----------------');
        $record_result = $tab_sha->add($share_record);//平台币奖励记录
        $invite_result = M("user","tab_")->save($invite);//邀请人平台币存储
        if($record_result === false || $invite_result === false){
            $tab_sha->rollback();
            return false;
        }else{
            $tab_sha->commit();
            return true;
        }
    }
    /**
    *日志记录
    */
    protected function record_logs($msg="",$type='ERR'){
        \Think\Log::record($msg,$type);
    }

}