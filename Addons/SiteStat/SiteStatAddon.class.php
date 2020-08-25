<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: yangweijie <yangweijiester@gmail.com> <code-tech.diandian.com>
// +----------------------------------------------------------------------
namespace Addons\SiteStat;

use Common\Controller\Addon;

/**
 * 系统环境信息插件
 * @author thinkphp
 */
class SiteStatAddon extends Addon
{

    public $info = array(
        'name' => 'SiteStat',
        'title' => '站点统计信息',
        'description' => '统计站点的基础信息',
        'status' => 1,
        'author' => 'thinkphp',
        'version' => '0.1'
    );

    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    //实现的AdminIndex钩子方法
    public function AdminIndex($param)
    {
        $shuju = M('Data', 'tab_')->order('create_time desc')->find();
        $this->assign('shuju', $shuju);

        $config = $this->getConfig();
        $this->assign('addons_config', $config);
        if ($config['display']) {
            $user = M("User", "tab_");
            $userlogin = M("UserLoginRecord", "tab_");
            $game = M("Game", "tab_");
            $spend = M('Spend', "tab_");
            $deposit = M('Deposit', "tab_");
            $promote = M("Promote", "tab_");
            $yesterday = $this->total(5);
            $today = $this->total(1);
            $week = $this->total(9);
            $month = $this->total(3);
            $doc = D("Document");
            // $b = $this->cate("blog");
            // $m = $this->cate("media");
            // $blog = $doc->table("__DOCUMENT__ as d")
                // ->where("d.status=1 and d.display=1 and d.category_id in (" . $b . ")")->count();
            // $media = $doc->table("__DOCUMENT__ as d")
                // ->where("d.status=1 and d.display=1 and d.category_id in (" . $m . ")")->count();
            // $info['document'] = $this->huanwei($blog + $media);
            // $info['blog'] = $this->huanwei($blog);
            // $info['media'] = $this->huanwei($media);
            //待办事项
            $this->daiban();
            //提示事项indexcontroller
            $this->tishi();
            //昨日新增用户分析
            //$this->newsPlayerInYestoday();

            //昨日新增用户分析
            $this->zrxz();

            $this->display('info');
        }
    }


    /**
     * 昨日新增用户情况
     * @author <zsl>
     */

    public function zrxz(){
        //昨天日期
        $start = mktime(0,0,0,date('m'),date('d')-1,date('Y'));
        $end = mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
        //前天日期
        $start2 = mktime(0,0,0,date('m'),date('d')-2,date('Y'));
        $end2 = mktime(0,0,0,date('m'),date('d')-1,date('Y'))-1;

        //昨天新增用户数量
        $zr_user_num = $this->xz_user_count($start,$end);
        //前天新增用户数量
        $qt_user_num = $this->xz_user_count($start2,$end2);

        //计算用户新增百分比
        $news['rate'] = $this->rate_count($zr_user_num,$qt_user_num);
        $news['count'] = $zr_user_num;
        $this->assign('news',$news);


        //昨日新增用户领取礼包数量
        $zr_gift_user = $this->xz_user_gift_count($start,$end);
        //前天新增用户领取礼包数量
        $qt_gift_user = $this->xz_user_gift_count($start2,$end2);

        //昨日新增用户签到数量
        $zr_point_user = $this->xz_user_point_count($start,$end);
        //前天新增用户签到数量
        $qt_point_user = $this->xz_user_point_count($start2,$end2);

        //昨日新增用户兑换商品数量
        $zr_pointshop_user = $this->xz_user_pointshop_count($start,$end);
        //前天新增用户兑换商品数量
        $qt_pointshop_user = $this->xz_user_pointshop_count($start2,$end2);

        //昨日新增用户邀请好友数量
        $zr_share_user = $this->xz_user_share_count($start,$end);
        //前天新增用户邀请好友数量
        $qt_share_user = $this->xz_user_share_count($start2,$end2);

        //昨日新增用户充值人数
        $zr_spend_user = $this->xz_user_spend_count($start,$end);
        //前天新增用户充值人数
        $qt_spend_user = $this->xz_user_spend_count($start2,$end2);

        //昨日留存
        $zr_ratention = $this->ratention_count($start,$end);
        //前天留存
        $qt_ratention = $this->ratention_count($start2,$end2);

        //昨日行为总数
        $total_num = $zr_gift_user+$zr_point_user+$zr_pointshop_user+$zr_share_user+$zr_spend_user+$zr_ratention;
        //前天行为总数
        $tq_total_num = $qt_gift_user+$qt_point_user+$qt_pointshop_user+$qt_share_user+$qt_spend_user+$qt_ratention;


        //用户礼包领取数量
        $gift = $this->formart_data($zr_gift_user,$total_num,$qt_gift_user,$tq_total_num);
        $this->assign('gift',$gift);

        //用户签到数量
        $point = $this->formart_data($zr_point_user,$total_num,$qt_point_user,$tq_total_num);
        $this->assign('point',$point);

        //用户兑换商品数量
        $pointshop = $this->formart_data($zr_pointshop_user,$total_num,$qt_pointshop_user,$tq_total_num);
        $this->assign('pointshop',$pointshop);

        //用户邀请好友数量
        $share = $this->formart_data($zr_share_user,$total_num,$qt_share_user,$tq_total_num);
        $this->assign('share',$share);

        //用户充值数量
        $money = $this->formart_data($zr_spend_user,$total_num,$qt_spend_user,$tq_total_num);
        $this->assign('money',$money);

        //用户留存数量
        $rate = $this->formart_data($zr_ratention,$total_num,$qt_ratention,$tq_total_num);
        $this->assign('rate',$rate);


    }



    /*
     * 计算数据分析所需格式
     *
     */

    private function formart_data($zr_num,$zr_total_num,$qt_num,$qt_total_num){

        $user_per = $this->per_count($zr_num,$zr_total_num);
        $user_per2 = $this->per_count($qt_num,$qt_total_num);

        
        $data['per'] = $user_per;
//        $data['rate'] = $this->rate_count($user_per,$user_per2);
        $data['rate'] = $user_per-$user_per2;
        $data['count'] = $zr_num;

        return $data;
    }

    /**
     * 计算百分比
     * num1 占 num2 的百分比
     */
    private function per_count($num1,$num2){
        if($num1>$num2){
            $rate = $num1?(round($num2/$num1*100,2)):0;
        }else{
            $rate = $num2?(round($num1/$num2*100,2)):0;
        }
        return $rate;
    }



    /**
     * 计算百分比
     * num1 比 num2 增长的百分比数
     */
    private function rate_count($num1,$num2){
        $float = $num1-$num2;
        $rate = $num2?(round($float/$num2*100,2)):'0';
        return $rate;
    }


    /*
     *  获取新增用户数量
     *  @author <zsl>
     */
    private function xz_user_count($start,$end){
        $user = M('User','tab_');
        $qt_num = $user->field('id')->where(array('register_time'=>['between',[$start,$end]]))->count();
        return $qt_num?$qt_num:'0';
    }

    /*
     *  获取新增用户领取礼包数量
     *  @author <zsl>
     */
    private function xz_user_gift_count($start,$end){

        $gift = M('GiftRecord','tab_');
        $count = $gift
            ->field('tab_user.id as id')
            ->join('tab_user on(tab_user.id = tab_gift_record.user_id)')
            ->where(array('register_time'=>['between',[$start,$end]],'tab_gift_record.create_time'=>['between',[$start,$end]]))
            ->group('tab_user.id')
            ->select();
        return count($count);
    }


    /*
     *  获取新增用户签到数量
     *  @author <zsl>
     */
    private function xz_user_point_count($start,$end){

        $pointRecord = M('PointRecord','tab_');
        $count = $pointRecord
            ->field('tab_user.id as id')
            ->join('tab_user on(tab_user.id = tab_point_record.user_id)')
            ->where(array('register_time'=>['between',[$start,$end]],'create_time'=>['between',[$start,$end]]))
            ->group('tab_user.id')
            ->select();

        return count($count);
    }

    /*
     *  获取新增用户兑换商品数量
     *  @author <zsl>
     */

    private function xz_user_pointshop_count($start,$end){

        $pointShopRecord = M('PointShopRecord','tab_');
        $count = $pointShopRecord
            ->field('tab_user.id as id')
            ->join('tab_user on(tab_user.id = tab_point_shop_record.user_id)')
            ->where(array('register_time'=>['between',[$start,$end]],'create_time'=>['between',[$start,$end]]))
            ->group('tab_user.id')
            ->select();

        return count($count);
    }

    /*
     *  获取新增用户邀请好友数量
     *  @author <zsl>
     */
    private function xz_user_share_count($start,$end){
        $shareRecord = M('ShareRecord','tab_');
        $count = $shareRecord
            ->field('tab_user.id as id')
            ->join('tab_user on(tab_user.id = tab_share_record.invite_id)')
            ->where(array('register_time'=>['between',[$start,$end]],'create_time'=>['between',[$start,$end]]))
            ->group('tab_user.id')
            ->select();

        return count($count);
    }

    /*
     *  获取新增用户充值人数
     *  @author <zsl>
     */

    private function xz_user_spend_count($start,$end){

        $spend = M('Spend','tab_');
        $spendlist = $spend
            ->field('tab_user.id as id')
            ->join('tab_user on(tab_user.id = tab_spend.user_id)')
            ->where(array('register_time'=>['between',[$start,$end]],'pay_time'=>['between',[$start,$end]]))
            ->group('tab_user.id')
            ->select();
        return count($spendlist);
    }


    /*
     *  获取留存人数
     *  @author <zsl>
     */

    private function ratention_count($start,$end){

        // 新增用户
        $user = M('User','tab_');
        $newslist = $user->field('FROM_UNIXTIME(register_time, "%Y-%m-%d") as time,COUNT(id) AS count,group_concat(id) as id')
            ->where(array('register_time'=>['between',[$start,$end]]))
            ->group('time')->order('time desc')
            ->select();


        // 留存
        $ratention = $this->ratentionRate($newslist);

        $yes = date('Y-m-d',$end);

        $rate['count'] = $ratention[$yes]['count']?$ratention[$yes]['count']:0;

        return $rate['count'];

    }



    /*
     * 昨日新增用户分析
     * @author 鹿文学
     */
    public function newsPlayerInYestoday() {
        $start = mktime(0,0,0,date('m'),date('d')-3,date('Y'));
        $end = mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
        // 新增用户
        $user = M('User','tab_');
        $newslist = $user->field('FROM_UNIXTIME(register_time, "%Y-%m-%d") as time,COUNT(id) AS count,group_concat(id) as id')
            ->where(array('register_time'=>['between',[$start,$end]]))
            ->group('time')->order('time desc')
            ->select();

        if ($newslist[0] && $newslist[0]['time']==date('Y-m-d',$end)) {

            $news['count'] = $newslist[0]['count']?$newslist[0]['count']:0;
            $news['rate'] = $newslist[0] && $newslist[1]? round(($newslist[0]['count']/$newslist[1]['count']-1)*100,2):($newslist[0]&&!$newslist[1]?(100):(!$newslist[0]&&$newslist[1]?(-100):0));

            $start = mktime(0,0,0,date('m'),date('d')-2,date('Y'));

            $this->assign('news',$news);

            // 礼包
            $gift = M('GiftRecord','tab_');
            $giftlist = $gift->field('FROM_UNIXTIME(tab_gift_record.create_time, "%Y-%m-%d") as time,COUNT(distinct tab_gift_record.user_id) AS count')
                ->join('tab_user on(tab_user.id = tab_gift_record.user_id)')
                ->where(array('register_time'=>['between',[$start,$end]],'tab_gift_record.create_time'=>['between',[$start,$end]]))
                ->group('time')->order('time desc')
                ->select();
            if ($giftlist[0] && $giftlist[0]['time']==date('Y-m-d',$end)) {
                $getgift['count'] = $giftlist[0]['count']?$giftlist[0]['count']:0;
                $getgift['rate'] = $giftlist[0] && $giftlist[1]? round(($giftlist[0]['count']/$giftlist[1]['count']-1)*100,2):($giftlist[0]&&!$giftlist[1]?(100):(!$giftlist[0]&&$giftlist[1]?(-100):0));
            }   else {
                $getgift['count'] = 0;
                $getgift['rate'] = $giftlist[1]?-100:0;
            }
            $getgift['per'] = $news['count']?round($getgift['count']/$news['count']*100,0):0;
            $this->assign('gift',$getgift);

            // 签到
            $pointRecord = M('PointRecord','tab_');
            $pointrecordlist = $pointRecord->field('FROM_UNIXTIME(create_time, "%Y-%m-%d") as time,COUNT(distinct user_id) AS count')
                ->join('tab_user on(tab_user.id = tab_point_record.user_id)')
                ->where(array('register_time'=>['between',[$start,$end]],'create_time'=>['between',[$start,$end]]))
                ->group('time')->order('time desc')->select();

            if ($pointrecordlist[0] && $pointrecordlist[0]['time']==date('Y-m-d',$end)) {
                $point['count'] = $pointrecordlist[0]['count']?$pointrecordlist[0]['count']:0;
                $point['rate'] = $pointrecordlist[0] && $pointrecordlist[1]? round(($pointrecordlist[0]['count']/$pointrecordlist[1]['count']-1)*100,2):($pointrecordlist[0]&&!$pointrecordlist[1]?(100):(!$pointrecordlist[0]&&$pointrecordlist[1]?(-100):0));
            }else {
                $point['count'] = 0;
                $point['rate'] = $pointrecordlist[1]?-100:0;
            }
            $point['per'] = $news['count']?round($point['count']/$news['count']*100,0):0;
            $this->assign('point',$point);



            // 兑换商品
            $pointShopRecord = M('PointShopRecord','tab_');
            $pointshoprecordlist = $pointShopRecord->field('FROM_UNIXTIME(create_time, "%Y-%m-%d") as time,COUNT(distinct user_id) AS count')
                ->join('tab_user on(tab_user.id = tab_point_shop_record.user_id)')
                ->where(array('register_time'=>['between',[$start,$end]],'create_time'=>['between',[$start,$end]]))
                ->group('time')->order('time desc')->select();
            if ($pointshoprecordlist[0] && $pointshoprecordlist[0]['time']==date('Y-m-d',$end)) {
                $pointshop['count'] = $pointshoprecordlist[0]['count']?$pointshoprecordlist[0]['count']:0;
                $pointshop['rate'] = $pointshoprecordlist[0] && $pointshoprecordlist[1]? round(($pointshoprecordlist[0]['count']/$pointshoprecordlist[1]['count']-1)*100,2):($pointshoprecordlist[0]&&!$pointshoprecordlist[1]?(100):(!$pointshoprecordlist[0]&&$pointshoprecordlist[1]?(-100):0));
            }else {
                $pointshop['count'] = 0;
                $pointshop['rate'] = $pointshoprecordlist[1]?-100:0;
            }
            $pointshop['per'] = $news['count']?round($pointshop['count']/$news['count']*100,0):0;

            $this->assign('pointshop',$pointshop);

            // 邀请好友
            $shareRecord = M('ShareRecord','tab_');
            $sharerecordlist = $shareRecord->field('FROM_UNIXTIME(create_time, "%Y-%m-%d") as time,COUNT(distinct invite_id) AS count')
                ->join('tab_user on(tab_user.id = tab_share_record.invite_id)')
                ->where(array('register_time'=>['between',[$start,$end]],'create_time'=>['between',[$start,$end]]))
                ->group('time')->order('time desc')->select();
            if ($sharerecordlist[0] && $sharerecordlist[0]['time']==date('Y-m-d',$end)) {
                $share['count'] = $sharerecordlist[0]['count']?$sharerecordlist[0]['count']:0;
                $share['rate'] = $sharerecordlist[0] && $sharerecordlist[1]? round(($sharerecordlist[0]['count']/$sharerecordlist[1]['count']-1)*100,2):($sharerecordlist[0]&&!$sharerecordlist[1]?(100):(!$sharerecordlist[0]&&$sharerecordlist[1]?(-100):0));
            }else {
                $share['count'] = 0;
                $share['rate'] = $sharerecordlist[1]?-100:0;
            }
            $share['per'] = $news['count']?round($share['count']/$news['count']*100,0):0;

            $this->assign('share',$share);

            // 充值
            $spend = M('Spend','tab_');
            $spendlist = $spend->field('FROM_UNIXTIME(pay_time, "%Y-%m-%d") as time,COUNT(distinct user_id) AS count')
                ->join('tab_user on(tab_user.id = tab_spend.user_id)')
                ->where(array('register_time'=>['between',[$start,$end]],'pay_time'=>['between',[$start,$end]]))
                ->group('time')->order('time desc')->select();
            if ($spendlist[0] && $spendlist[0]['time']==date('Y-m-d',$end)) {
                $money['count'] = $spendlist[0]['count']?$spendlist[0]['count']:0;
                $money['rate'] = $spendlist[0] && $spendlist[1]? round(($spendlist[0]['count']/$spendlist[1]['count']-1)*100,2):($spendlist[0]&&!$spendlist[1]?(100):(!$spendlist[0]&&$spendlist[1]?(-100):0));
            } else {
                $money['count'] = 0;
                $money['rate'] = $spendlist[1]?-100:0;
            }
            $money['per'] = $news['count']?round($money['count']/$news['count']*100,0):0;

            $this->assign('money',$money);

            // 留存
            $ratention = $this->ratentionRate($newslist);

            $yes = date('Y-m-d',$end);
            $one = date('Y-m-d',$end-86400);

            $rate['count'] = $ratention[$yes]['count']?$ratention[$yes]['count']:0;
            $rate['rate'] = $ratention[$yes] && $ratention[$one]? ($ratention[$yes]['retentionrate']-$ratention[$one]['retentionrate']):($ratention[$yes]&&!$ratention[$one]?(100):(!$ratention[$yes]&&$ratention[$one]?(-100):0));
            $rate['per'] = $news['count']?round($rate['count']/$news['count']*100,0):0;

            $this->assign('rate',$rate);
        }

    }


    /**
     * 玩家留存率
     * @param  array    $news       新增用户数据（按天分组）
     * @param  array    $map        条件数组
     * @param  integer  $flag       留存类型（1：1天，3：3天，7：7天）
     * @param  string   $fieldname  字段别名
     * @param  string   $group      分组字段名
     * @return array       详细数据
     * @author 鹿文学
     */
    private function ratentionRate($news,$map=[],$flag=1,$fieldname='retentionrate',$group='login_time') {

        $map['lock_status']=1;

        $data = array();

        $user = M('User','tab_');

        foreach ($news as $value) {
            $ct1 = strtotime("+$flag day",strtotime($value['time']));
            $ct2 = strtotime("+1 day",$ct1)-1;

            $map['tab_user_login_record.login_time'] = array(array('egt',$ct1),array('elt',$ct2));

            $map['user_id']=array('in',$value['id']);
            $count = count(explode(',',$value['id']));

            $d=$user
                ->field('count(distinct user_id) as '.$fieldname.' ,FROM_UNIXTIME(tab_user_login_record.login_time,"%Y-%m-%d") as '.$group)
                ->join('tab_user_login_record on tab_user.id=tab_user_login_record.user_id','right')
                ->where($map)
                ->group($group)
                ->select();

            if ($d)
                $data[$value['time']]=array(
                    $group=>$value['time'],'key'=>$fieldname,'count'=>($d[0][$fieldname]==0)?0:$d[0][$fieldname],
                    $fieldname=>($d[0][$fieldname]==0)?0:sprintf("%.2f",($d[0][$fieldname]/$count)*100)
                );

        }


        return $data;
    }

    public function foreach_stat($dday, $type = 1)
    {

        $ss="";
        foreach ($dday as $key => $value) {
            if ($type == 1) {
                $ss .= '"' . $value . '",';
            } else {
                $ss .= $value . ',';
            }

        }

        return $ss;
    }


    public function foreach_data($day, $data, $type = 1)
    {
        foreach ($day as $s => $d) {
            $spendd[$s]['time'] = $d;
            $spendd[$s]['count'] = 0;
        }
        if ($type == 1) {
            $data  = array_combine(array_column($data,'time'),array_column($data,'cg'));
            foreach ($spendd as $s => $d) {
                $spendd[$s]['count'] = (int)$data[$d['time']];
            }
            /* foreach ($spendd as $s => $d) {
                foreach ($data as $key => $value) {
                    if ($value['time'] == $d['time']) {

                        $spendd[$s]['count'] = $value['cg'];//(int)$value['cg'];
                    }
                }
            } */
        } else {
            foreach ($spendd as $s => $d) {
                $a = 0;
                foreach ($data as $key => $value) {
                    if ($value['time'] == $d['time']) {
                        $a++;
                        $spendd[$s]['count'] = $a;
                    }
                }
            }
        }

        return $spendd;
    }


    private function daiban()
    {
        $user = M("User", "tab_");
        $game = M("Game", "tab_");
        $spend = M('Spend', "tab_");
        $deposit = M('Deposit', "tab_");
        $apply = M('Apply', "tab_");
        $applyapp = M('app_apply', "tab_");
        $promote = M("Promote", "tab_");

        $pregist = $promote->where(array('status' => 0))->count();//渠道申请待审核数
        $daiban['pcount'] = $pregist;

        $applyandgame = $apply->where(array('sdk_version'=>1,'enable_status' => '0'))->count();//安卓渠道分包待打包数
        $daiban['applyandgame'] = $applyandgame;
        $applyiosgame = $apply->where(array('sdk_version'=>2,'enable_status' => '0'))->count();//苹果渠道分包待打包数
        $daiban['applyiosgame'] = $applyiosgame;

        $withc = M('Withdraw', 'tab_')->where(array('status' => 0, 'promote_id' => array('gt', 0)))->count();//渠道提现待审核数
        $daiban['withc'] = $withc;

        $spenc = $spend->where(array('pay_game_status' => 0, 'pay_status' => 1))->count();//游戏充值待补单数
        $daiban['spenc'] = $spenc;

        $applyandapp = $applyapp->where(['app_version'=>1,'status'=>1,'enable_status'=>0])->count();//安卓APP分包待打包数
        $daiban['applyandapp'] = $applyandapp;
                
        $applyiosapp = $applyapp->where(['app_version'=>0,'status'=>1,'enable_status'=>0])->count();//苹果APP分包待打包数
        $daiban['applyiosapp'] = $applyiosapp;
                

        $msgc = M('Msg', 'tab_')->where(array('user_id' => UID, 'status' => 2))->count();//站内通知
        $daiban['msgc'] = $msgc;
        $this->assign('daiban', $daiban);
    }

    private function cate($name)
    {
        $cate = M("Category");
        $c = $cate->field('id')->where("status=1 and display=1 and name='$name'")->buildSql();
        $ca = $cate->field('id')->where("status=1 and display=1 and pid=$c")->select();
        foreach ($ca as $c) {
            $d[] = $c['id'];
        }
        return "'" . implode("','", $d) . "'";
    }

    private function idata($data, $flag = false, $field)
    {
        $day = array_flip($this->every_day(7));//七天日期
        $data = array_merge($day, $data);
        $d = $c = '';
        $max = 0;
        $min = 0;
        if (!empty($data)) {
            ksort($data);
            // $data = array_reverse($data);
            if ($flag) {
                foreach ($data as $k => $v) {
                    if (!empty($v)) {
                        foreach ($v as $j => $u) {
                            $total += $u[$field];
                        }
                        $toto[] = $total;

                    } else {
                        $toto[] = $total = 0;
                    }
                    if ($min > $total) {
                        $min = $total;
                    }
                    if ($max < $total) {
                        $max = $total;
                    }
                    $c .= '"' . $k . '",';
                    $total = 0;
                }
                $d = implode(',', $toto) . ',';
            } else {
                foreach ($data as $k => $v) {
                    $count = empty($v) ? 0 : count($v);
                    if ($min > $count) {
                        $min = $count;
                    }
                    if ($max < $count) {
                        $max = $count;
                    }
                    $d .= $count . ',';
                    $c .= '"' . $k . '",';
                }
            }
            $d = substr($d, 0, -1);
            $c = substr($c, 0, -1);
        }
        $max++;
        $pay = array(
            'min' => $min,
            'max' => $max,
            'data' => $d,
            'cate' => $c
        );
        return $pay;
    }

    private function linepay()
    {
        $spend = M('Spend', "tab_");
        $deposit = M('Deposit', "tab_");
        $week = $this->total(9);
        $samount = $spend->field("pay_amount,pay_time as time")->where("pay_status=1 and pay_time $week")->select();
        $damount = $deposit->field("pay_amount,create_time as time")->where("pay_status=1 and create_time $week")->select();
        if (!empty($samount) && !empty($damount))
            $data = array_merge($samount, $damount);
        else {
            if (!empty($samount))
                $data = $samount;
            else if (!empty($damount))
                $data = $damount;
            else
                $data = '';
        }

        $result = array();
        $this->jump($data, $result, 8);
        return $result;
    }

    private function lineregister()
    {
        $week = $this->total(9);
        $user = M("User", "tab_")->field("account,register_time as time")->where("lock_status=1 and register_time $week")->select();

        if (!empty($user))
            $data = $user;
        else
            $data = array(0, 0, 0, 0, 0, 0, 0);

        $result = array();
        $this->jump($data, $result, 8);
        return $result;
    }

    protected function jump(&$a, &$b, $m, $n = 0)
    {
        $num = count($a);
        if ($m == 1) {
            return;
        } else {
            $time = time();
            if ($m < 8) {
                $c = 8 - $m;
                $time = $time - ($c * 86400);
            }
            $m -= 1;
            $t = date("Y-m-d", $time);
            if (empty($a) && count($b) < 8) {
                $b[$t] = "";
            } else {
                foreach ($a as $k => $g) {
                    $st = date("Y-m-d", $g['time']);
                    if ($t === $st) {
                        $b[$t][] = $g;
                        unset($a[$k]);
                    }
                    if ($b[$t] == '') {
                        $b[$t] = 0;
                    }
                }
                $a = array_values($a);
            }
            return $this->jump($a, $b, $m, $num);
        }
    }

    private function total($type)
    {
        switch ($type) {
            case 1: { // 今天
                $start = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
                $end = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;
            };
                break;
            case 3: { // 本月
                $start = mktime(0, 0, 0, date('m'), 1, date('Y'));
                $end = mktime(0, 0, 0, date('m') + 1, 1, date('Y')) - 1;
            };
                break;
            case 4: { // 本年
                $start = mktime(0, 0, 0, 1, 1, date('Y'));
                $end = mktime(0, 0, 0, 1, 1, date('Y') + 1) - 1;
            };
                break;
            case 5: { // 昨天
                $start = mktime(0, 0, 0, date('m'), date('d') - 1, date('Y'));
                $end = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
            };
                break;
            case 9: { // 前七天
                $start = mktime(0, 0, 0, date('m'), date('d') - 6, date('Y'));
                $end = mktime(date('H'), date('m'), date('s'), date('m'), date('d'), date('Y'));
            };
                break;
            default:
                $start = '';
                $end = '';
        }

        return " between $start and $end ";
    }

    //以当前日期 默认前七天
    private function every_day($m = 7)
    {
        $time = array();
        for ($i = 0; $i < $m; $i++) {
            $time[] = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - $i, date('Y')));
        }
        return $time;
    }

    private function huanwei($total)
    {
        if (!strstr($total, '.')) {
            $total = $total . '.00';
        }
        $total = empty($total) ? '0' : trim($total . ' ');
        $len = strlen($total);
        if ($len > 8) { // 亿
            $len = $len - 12;
            $total = $len > 0 ? (round(($total / 1e12), 2) . '万亿') : round(($total / 1e8), 2) . '亿';
        } else if ($len > 4) { // 万
            $total = (round(($total / 10000), 2)) . 'w';
        }
        return $total;
    }

    /*
     * 提示事项
     * @author <jszsl001@163.com>
     */
    private function tishi(){

        //【返利设置】
        $fmap['endtime'] = [['neq',''],['lt',time()]];
        $fl_set = M('rebate','tab_')->field('id,game_id,game_name')->where($fmap)->select(false);
        $this->assign('fl_set',$fl_set);

        //【礼包列表】
        $lt_num = 10; //礼包提示数量

        #统一码数量不足礼包
        $gty['is_unicode'] = 1;
        $gty['unicode_num'] = ['lt',$lt_num];
        $ty_gift = M('giftbag','tab_')->where($gty)->field('id,game_name,server_id,server_name,giftbag_name,novice')->select();

        #非统一码数量不足
        $gmap['is_unicode'] = 0;
        $fty_gift_list = M('giftbag','tab_')->where($gmap)->field('id,game_name,server_id,server_name,giftbag_name,novice')->select();
        foreach($fty_gift_list as $v){
            $num = count(explode(',',$v['novice']));
            if($num<$lt_num){
                $new_gift_list[] = $v;
            }
        }

        $gift_list = array_merge($ty_gift,$new_gift_list);
        $this->assign('gift_list',$gift_list);
    }

}