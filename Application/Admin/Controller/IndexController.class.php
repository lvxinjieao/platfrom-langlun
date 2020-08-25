<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace Admin\Controller;
use User\Api\UserApi as UserApi;

/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class IndexController extends AdminController {

    /**
     * 后台首页
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function index(){

        if(session('user_auth.uid')){
            $data=M('Member')
                ->field('uid,nickname,username,us.last_login_time,us.last_login_ip,login')
                ->join('sys_ucenter_member as us on sys_member.uid = us.id')
                ->where(array('uid'=>session('user_auth.uid')))
                ->find();
            header("Content-type: text/html; charset=utf-8");
            if(is_administrator()){
                $data['group']='超级管理员';
            }else{
                $data['group'] = get_auth_group_name($data['uid']);
            }
        }
        $this->assign('data',$data);
        $this->indextt();
        $this->meta_title = '管理首页';


        // 累计数据
        $user = D('User');
        $spend = D('Spend');
        $promote = D('Promote');
        $this->assign('user_count',$user->old());
        $this->assign('active_count',$user->active(['tab_user_login_record.login_time'=>['between',[mktime(0,0,0,date('m'),date('d')-7,date('Y')),mktime(0,0,0,date('m'),date('d'),date('Y'))-1]]]));
        $this->assign('player_count',$spend->player());

        //查询deposit总金额
        $sum_deposit = M('deposit','tab_')->where(['pay_status'=>1])->sum('pay_amount');

        $money_sum = $sum_deposit+$spend->totalAmount(['pay_status'=>1,'pay_way'=>['gt',0]]);
        $this->assign('money_sum',$money_sum);
        $this->assign('promote_sum',$promote->total());

        $this->display();
    }

    /*
     * 日历
     * @param integer  $start  开始时间(2018-04)
     * @param integer  $end  	 结束时间(2018-05)
     * @param boolean  $flag   是否ajax返回
     * @author 鹿文学
     */
    public function calendar($start='',$end='',$flag=false) {

        $start = $start?$start:date('Y-m',strtotime('-1 month'));
        $end = $end?$end:date('Y-m');

        if ($start == $end) {$start = date('Y-m',strtotime('-1 month',$end));}
        if (strtotime($start)>strtotime($end)) {$temp = $end;$end = $start;$start = $temp;}
        if (strtotime($end) > strtotime(date('Y-m'))) {$end = date('Y-m');$start = date('Y-m',strtotime('-1 month'));}

        $iscurrent = $end != date('Y-m')?1:0; // 默认是当前月，不可进入下一个月

        $stime = strtotime($start);
        $etime = strtotime($end);

        $sw = date('w',$stime);   // 周几
        $ew = date('w',$etime);
        $sw = $sw == 0? 6:(integer)$sw-1;
        $ew = $ew == 0? 6:(integer)$ew-1;

        $st = date('t',$stime);    // 天数
        $et = date('t',$etime);

        $sf = $ef = $sr = $er = 1; // 行数 ，日期起始值

        for($i=0;$i<7;$i++) {
            if ($i<$sw)
                $first[$sr][$i] = ['value'=>''];
            else {
                $first[$sr][$i] = ['value'=>set_date_day_format($sf),'full'=>$start.'-'.set_date_day_format($sf)];$sf++;
            }
        }
        for($i=0;$i<7;$i++) {
            if ($i<$ew)
                $last[$er][$i] = ['value'=>''];
            else {
                $eday = set_date_day_format($ef);
                if (strtotime($end.'-'.$eday)>strtotime(date('Y-m-d'))){
                    $last[$er][$i] = ['value'=>$eday,'full'=>$end.'-'.$eday,'no'=>1];$ef++;
                }else{
                    $last[$er][$i] = ['value'=>$eday,'full'=>$end.'-'.$eday];$ef++;
                }
            }
        }

        $sn = $en = 0; // 列数
        for ($i=$sf;$i<=$st;$i++) {
            if (count($first[$sr])==7){$sr++;$sn=0;}
            $sday = set_date_day_format($i);
            $first[$sr][$sn] = ['value'=>$sday,'full'=>$start.'-'.$sday];
            $sn++;
        }
        for ($i=$ef;$i<=$et;$i++) {
            if (count($last[$er])==7){$er++;$en=0;}
            $eday = set_date_day_format($i);
            if (strtotime($end.'-'.$eday)>strtotime(date('Y-m-d'))){$last[$er][$en] = ['value'=>$eday,'full'=>$end.'-'.$eday,'no'=>1];} else{$last[$er][$en] = ['value'=>$eday,'full'=>$end.'-'.$eday];}

            $en++;
        }

        $prev = date('Y-m',strtotime('-1 month',$stime)).','.$start;
        $next = $end.','.date('Y-m',strtotime('+1 month',$etime));

        $calendar = ['first'=>$first,'last'=>$last,'prev'=>$prev,'next'=>$next,'iscurrent'=>$iscurrent,'ftitle'=>date('Y年m月',$stime),'ltitle'=>date('Y年m月',$etime),'today'=>date('Y-m-d')];

        if ($flag) {

            echo json_encode($calendar);

        } else {

            $this->assign('calendar',$calendar);

        }

    }




    public function indextt(){
        $user = M("User","tab_");
        $game = M("Game","tab_");
        $spend = M('Spend',"tab_");
        $deposit = M('Deposit',"tab_");
        $promote = M("Promote","tab_");

        if($gameso){
            $gameso=implode(',',array_column($gameso, 'game_id'));
            $sourcemap['id']=array('not in',$gameso);
        }else{
            $sourcemap['id']=0;
        }
        //游戏原包管理
        $gac=$game->field('game_name')->where($sourcemap)->order('create_time desc')->select();
        $tishi['gac']=$gac;
        //代充额度
        $prolc=$promote
            ->field('account,pay_limit')
            ->where(array('pay_limit'=>array('lt',10),'set_pay_time'=>array('gt',0)))
            ->select();
        $tishi['prolc']=$prolc;
        //返利设置
        $map_rebc['endtime'] = array(array('neq',0),array('lt',time()), 'and') ;
        $rebc=M('Rebate','tab_')
            ->field('game_name,endtime')
            ->where($map_rebc)
            ->select();
        $tishi['rebc']=$rebc;
        //礼包数量
        $giftc=M('Giftbag','tab_')
            ->field('game_name,novice,giftbag_name')
            ->where(array('status'=>1))
            ->select();
        foreach ($giftc as $key => $value) {
            $novc=arr_count($value['novice']);
            if($novc>10){
                unset($giftc[$key]);
            }
        }
        // //渠道礼包
        // $pgiftc=M('promote_gift','tab_')
        //     ->field('game_name,novice,giftbag_name')
        //     ->where(array('status'=>1))
        //     ->select();
        // foreach ($pgiftc as $key => $value) {
        //     $novc=arr_count($value['novice']);
        //     if($novc>10){
        //         unset($pgiftc[$key]);
        //     }
        // }
        $tishi['giftc']=$giftc;
        // $tishi['pgiftc']=$pgiftc;
        $this->assign('tishi',$tishi);
        // $this->display('index');
    }
    public function savekuaijie(){
        $newstr['kuaijie_value']=substr($_POST['kuaijie'],0,strlen($_POST['kuaijie'])-1);
        $data=M('Member')->where(array('uid'=>UID))->save($newstr);


        //保存行为日志
        action_log('save_kuaijie','member',UID,UID);

        if($data!==false){
            $this->ajaxReturn(array('status'=>1));
        }else{
            $this->ajaxReturn(array('status'=>0));
        }
    }
    public function kuaijie(){
        $this->display();
    }



    public function setup() {

        $data = M('kuaijieicon')->field('id,title,value,url')->where(['status'=>1])->select();

        $this->assign('kuaijielist',$data);

        $this->display();
    }



}
