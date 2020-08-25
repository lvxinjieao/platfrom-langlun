<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;

/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class StatisticsController extends ThinkController {

    public function overview(){
        $shuju = M('Data','tab_')->order('create_time desc')->find();
        $this->assign('shuju',$shuju);


        $this->assign('openegretmain','openegretmain');
        $this->meta_title = '总览';
        //实时数据概况
        $today = $this->total(1);
        $thisweek = $this->total(2);
        $thismounth = $this->total(3);
        $thisyear = $this->total(4);
        $yesterday=$this->total(5);
        $lastweek=$this->total(6);
        $lastmounth=$this->total(7);
        $lastyear=$this->total(8);
        // 游戏排行
        $type=$_REQUEST['type'];
        if($type==1 || $type==''){
            $list_data=$this->data_order($today,$yesterday,1);
        }elseif($type==2){
            $list_data=$this->data_order($thisweek,$lastweek);
        }elseif($type==3){
            $list_data=$this->data_order($thismounth,$lastmounth);
        }elseif($type==4){
            $list_data=$this->data_order($thisyear,$lastyear);
        }
        // 推广员排行
        switch($_REQUEST['category']) {
            case 2:{$promote_data = $this->promote_data_order($thisweek,$lastweek);};break;
            case 3:{$promote_data = $this->promote_data_order($thismounth,$lastmounth);};break;
            case 4:{$promote_data = $this->promote_data_order($thisyear,$lastyear);};break;
            default:
                $promote_data = $this->promote_data_order($today,$yesterday,1);
        }

        $this->assign('zhuce',$list_data['zhuce']);
        $this->assign('active',$list_data['active']);
        $this->assign('pay',$list_data['pay']);
        $this->assign('game_chart',$list_data['chart']);

        $this->assign('promotereg',$promote_data['reg']);
        $this->assign('promoteactive',$promote_data['active']);
        $this->assign('promotepay',$promote_data['pay']);
        $this->assign('promote_chart',$promote_data['chart']);


        // 日历
        R('Index/calendar');

        // 折线图
        $this->foldLineDiagram($_REQUEST['start'],$_REQUEST['end'],$_REQUEST['num']);

        $this->display();

    }



    /**
     * 排行榜（游戏）
     * @param string $nowtime  		现在时间段（between 开始时间戳 and 结束时间戳）
     * @param string $othertime  	过去时间段（between 开始时间戳 and 结束时间戳）
     * @param [type] $[oneday] [<是否单日>]
     * @return array 结果集
     * @author lwx   edit
     */
    private function data_order($nowtime,$othertime,$oneday=0){
        $user = M("User","tab_");
        $spend = M('Spend',"tab_");

        $chart1 = [];
        $chart2 = [];
        $chart3 = [];

        //今日注册排行
        $ri_ug_order=$user->field('fgame_id as game_id,fgame_name as game_name,count(tab_user.id) as cg')
            ->where(array('register_time'.$nowtime,'fgame_id'=>array('gt',0),'fgame_name'=>array('neq','')))
            ->group('fgame_id')->order('cg desc,register_time asc')->limit(10)->select();
        $ri_ug_order=array_order($ri_ug_order);
        $regids = array_column($ri_ug_order,'game_id');
        $allgame = D('Game')->field('id')->count();
        if($regids) {
            $yes_ug_order=$user->field('fgame_id as game_id,fgame_name as game_name,count(tab_user.id) as cg')
                ->where([array('register_time'.$othertime,'fgame_id'=>array('gt',0))])
                ->group('fgame_id')->order('cg desc')->select();
            $yes_ug_order=array_order($yes_ug_order);
            $new_yes = array_column($yes_ug_order,null,'game_id');

            //如果数值相同,排名相同
            foreach($ri_ug_order as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$ri_ug_order[$k-1]['cg']){
                    $ri_ug_order[$k]['rand'] = $ri_ug_order[$k-1]['rand'];
                }
            }
            foreach($yes_ug_order as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$yes_ug_order[$k-1]['cg']){
                    $yes_ug_order[$k]['rand'] = $yes_ug_order[$k-1]['rand'];
                }
            }
            foreach ($ri_ug_order as $key => $value) {
                $ri_ug_order[$key]['change']=0;
                $chart1['g'.$value['game_id']] = $value;
                $value['is_set'] = false;
                foreach ($yes_ug_order as $k => $v) {
                    if($value['game_id']==$v['game_id']){
                        $value['is_set'] = true;    //判断过去时间段是否存在数据
                        $ri_ug_order[$key]['change']=$value['rand']-$v['rand'];break;
                    }else{
                        $value['is_set'] = false; //过去时间段无数据,排行无变化
                    }
                }
                if(!$value['is_set']){
                    $ri_ug_order[$key]['change'] = $value['rand'] - count($ri_ug_order)-1;
                }
            }

        }
        // //今日活跃排行
        $duser = D('User');
        $ri_active_order = $duser->activeRankOnGame($oneday,$nowtime,'cg','',10);
        $ri_active_order=array_order($ri_active_order);
        $activeids = array_column($ri_active_order,'game_id');

        if($activeids) {
            $yes_active = $duser->activeRankOnGame($oneday,$othertime,'cg',$activeids);
            $yes_active=array_order($yes_active);
            $new_yes_act = array_column($yes_active,null,'game_id');

            //如果数值相同,排名相同
            foreach($ri_active_order as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$ri_active_order[$k-1]['cg']){
                    $ri_active_order[$k]['rand'] = $ri_active_order[$k-1]['rand'];
                }
            }
            foreach($yes_active as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$yes_active[$k-1]['cg']){
                    $yes_active[$k]['rand'] = $yes_active[$k-1]['rand'];
                }
            }

            foreach ($ri_active_order as $key => $value) {
                $ri_active_order[$key]['change']=0;
                $chart2['g'.$value['game_id']] = $value;
                $value['is_set'] = false;
                foreach ($yes_active as $k => $v) {
                    if($value['game_id']==$v['game_id']){
                        $value['is_set'] = true;    //判断过去时间段是否存在数据
                        $ri_active_order[$key]['change']=$value['rand']-$v['rand'];break;
                    }else{
                        $value['is_set'] = false; //过去时间段无数据,排行无变化
                    }
                }
                if(!$value['is_set']){
                    $ri_active_order[$key]['change'] = $value['rand'] - count($ri_active_order)-1;
                }
            }
        }

        // //充值排行
        //spend
        $ri_spay_order=$spend->field('game_id,game_name,sum(pay_amount) as cg')
            ->where(array('pay_time'.$nowtime,'game_id'=>array('gt',0),'pay_status'=>1))
            ->group('game_id')->order('cg desc')->limit(10)->select();
        $ri_spay_order=array_order($ri_spay_order);
        $payids = array_column($ri_spay_order,'game_id');
        if ($payids) {
            $yes_spay=$spend->field('game_id,game_name,sum(pay_amount) as cg')
                ->where([array('pay_status'=>1,'pay_time'.$othertime,'game_id'=>array('in',$payids))])
                ->group('game_id')->order('cg desc')->select();
            $yes_spay=array_order($yes_spay);
            $new_yes_spay = array_column($yes_spay,null,'game_id');
            //如果数值相同,排名相同
            foreach($ri_spay_order as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$ri_spay_order[$k-1]['cg']){
                    $ri_spay_order[$k]['rand'] = $ri_spay_order[$k-1]['rand'];
                }
            }
            foreach($ri_spay_order as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$ri_spay_order[$k-1]['cg']){
                    $ri_spay_order[$k]['rand'] = $ri_spay_order[$k-1]['rand'];
                }
            }
            foreach ($ri_spay_order as $key => $value) {
                $ri_spay_order[$key]['change']=0;
                $chart3['g'.$value['game_id']] = $value;
                $value['is_set'] = false;
                foreach ($yes_spay as $k => $v) {
                    if($value['game_id']==$v['game_id']){
                        $value['is_set'] = true;    //判断过去时间段是否存在数据
                        $ri_spay_order[$key]['change']=$value['rand']-$v['rand'];break;
                    }else{
                        $value['is_set'] = false; //过去时间段无数据,排行无变化
                    }
                }
                if(!$value['is_set']){
                    $ri_spay_order[$key]['change'] = $value['rand'] - count($ri_spay_order)-1;
                }
            }

        }
        $data['zhuce']=$ri_ug_order;
        $data['active']=$ri_active_order;
        $data['pay']=$ri_spay_order;
        $chart4 = array_merge($chart1,$chart2,$chart3);
        foreach($chart4 as $k => $v) {
            $chart['game'][$k] = $v['game_name'];
            foreach($chart1 as $c) {
                $chart['reg'][$k] = 0;
                if ($v['game_id'] == $c['game_id']) {
                    $chart['reg'][$k] = (integer)$c['cg'];break;
                }
            }
            foreach($chart2 as $c) {
                $chart['active'][$k] = 0;
                if ($v['game_id'] == $c['game_id']) {
                    $chart['active'][$k] = (integer)$c['cg'];break;
                }
            }
            foreach($chart3 as $c) {
                $chart['pay'][$k] = 0;
                if ($v['game_id'] == $c['game_id']) {
                    $chart['pay'][$k] = $c['cg'];break;
                }
            }
        }

        foreach($chart as $k => $v) {
            if ($k == 'game')
                $data['chart'][$k] = '"'.implode('","',$v).'"';
            else
                $data['chart'][$k] = implode(',',$v);
        }

        return $data;
    }




    /**
     * 排行榜（推广员）
     * @param string $nowtime  		现在时间段（between 开始时间戳 and 结束时间戳）
     * @param string $othertime  	过去时间段（between 开始时间戳 and 结束时间戳）
     * @return array 结果集
     * @author lwx
     */
    private function promote_data_order($nowtime,$othertime,$oneday=0){
        $user = M("User","tab_");
        $spend = M('Spend',"tab_");

        $chart1 = [];
        $chart2 = [];
        $chart3 = [];
        $allpromote = D('Promote')->field('id')->count();
        //今日注册排行
        $ri_ug_order=$user->field('tab_user.promote_id,tab_promote.account as promote_account,count(tab_user.id) as cg')
            ->join('tab_promote on (tab_promote.id = tab_user.promote_id)','left')
            ->where(array('register_time'.$nowtime,'promote_id'=>array('gt',0)))
            ->group('promote_id')->order('cg desc')->limit(10)->select();
        $ri_ug_order=array_order($ri_ug_order);
        $regids = array_column($ri_ug_order,'promote_id');
        if ($regids) {
            $yes_ug_order=$user->field('tab_user.promote_id,tab_promote.account as promote_account,count(tab_user.id) as cg')
                ->join('tab_promote on (tab_promote.id = tab_user.promote_id)')
                ->where([array('register_time'.$othertime,'promote_id'=>array('in',$regids))])
                ->group('promote_id')->order('cg desc')->select();
            $yes_ug_order=array_order($yes_ug_order);
            $all_yes_ug = array_column($yes_ug_order, null,'promote_id');
            //如果数值相同,排名相同
            foreach($ri_ug_order as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$ri_ug_order[$k-1]['cg']){
                    $ri_ug_order[$k]['rand'] = $ri_ug_order[$k-1]['rand'];
                }
            }
            foreach($yes_ug_order as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$yes_ug_order[$k-1]['cg']){
                    $yes_ug_order[$k]['rand'] = $yes_ug_order[$k-1]['rand'];
                }
            }

            foreach ($ri_ug_order as $key => $value) {
                $ri_ug_order[$key]['change']=0;
                $chart1['p'.$value['promote_id']] = $value;
                $value['is_set'] = false;
                foreach ($yes_ug_order as $k => $v) {
                    if($value['promote_id']==$v['promote_id']){
                        $value['is_set'] = true;    //判断过去时间段是否存在数据
                        $ri_ug_order[$key]['change']=$value['rand']-$v['rand'];break;
                    }else{
                        $value['is_set'] = false; //过去时间段无数据,排行无变化
                    }
                }
                if(!$value['is_set']){
                    $ri_ug_order[$key]['change'] = $value['rand'] - count($ri_ug_order)-1;
                }
            }
        }
        // //今日活跃排行
        $duser = D('User');
        $ri_active_order = $duser->activeRankOnPromote($oneday,$nowtime,'cg');
        $ri_active_order=array_order($ri_active_order);
        $activeids = array_column($ri_active_order,'promote_id');

        if ($activeids) {
            $yes_active = $duser->activeRankOnPromote($oneday,$othertime,'cg',$activeids);
            $yes_active=array_order($yes_active);
            $all_yes_act = array_column($yes_active, null,'promote_id');
            //如果数值相同,排名相同
            foreach($ri_active_order as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$ri_active_order[$k-1]['cg']){
                    $ri_active_order[$k]['rand'] = $ri_active_order[$k-1]['rand'];
                }
            }

            foreach($yes_active as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$yes_active[$k-1]['cg']){
                    $yes_active[$k]['rand'] = $yes_active[$k-1]['rand'];
                }
            }
            foreach ($ri_active_order as $key => $value) {
                $ri_active_order[$key]['change']=0;
                $chart2['p'.$value['promote_id']] = $value;
                $value['is_set'] = false;
                foreach ($yes_active as $k => $v) {
                    if($value['promote_id']==$v['promote_id']){
                        $value['is_set'] = true;    //判断过去时间段是否存在数据
                        $ri_active_order[$key]['change']=$value['rand']-$v['rand'];break;
                    }else{
                        $value['is_set'] = false; //过去时间段无数据,排行无变化
                    }
                }
                if(!$value['is_set']){
                    $ri_active_order[$key]['change'] = $value['rand'] - count($ri_active_order)-1;
                }
            }

        }

        // //充值排行
        //spend+deposite
        $ri_spay_sql=$spend->field('tab_spend.promote_id,tab_promote.account as promote_account,sum(pay_amount) as cg')
            ->join('tab_promote on(tab_spend.promote_id = tab_promote.id)')
            //->union('select promote_id,tab_promote.account as promote_account,sum(pay_amount) as cg from tab_deposit inner join tab_promote on(tab_promote.id = tab_deposit.promote_id) where pay_status=1 and promote_id>0 and tab_deposit.create_time '.$nowtime.' group by promote_id ')
            ->where(array('pay_time'.$nowtime,'promote_id'=>array('gt',0),'pay_status'=>1,'is_check'=>array('eq',1)))
            ->group('promote_id')->select(false);
        $ri_spay_order = $spend->field('promote_id,promote_account,sum(cg) as cg')->table('('.$ri_spay_sql.') as a')->group('promote_id')->order('cg desc')->limit(10)->select();
        $ri_spay_order=array_order($ri_spay_order);
        
        $payids = array_column($ri_spay_order,'promote_id');
        if ($payids) {
            $yes_spay_sql=$spend->field('tab_spend.promote_id,tab_promote.account as promote_account,sum(pay_amount) as cg')
                ->join('tab_promote on(tab_spend.promote_id = tab_promote.id)')
                //->union('select promote_id,tab_promote.account as promote_account,sum(pay_amount) as cg from tab_deposit inner join tab_promote on(tab_promote.id = tab_deposit.promote_id) where pay_status=1 and promote_id>0 and tab_deposit.create_time '.$othertime.' group by promote_id ')
                ->where([array('pay_status'=>1,'is_check'=>array('eq',1),'pay_time'.$othertime,'promote_id'=>array('gt',0)),array('promote_id'=>array('in',$payids))])
                ->group('promote_id')->select(false);
            $yes_spay=$spend->field('promote_id,promote_account,sum(cg) as cg')->table('('.$yes_spay_sql.') as a')->group('promote_id')->order('cg desc')->select();
            $yes_spay=array_order($yes_spay);
            $all_yes_spay = array_column($yes_spay, null,'promote_id');

            //如果数值相同,排名相同
            foreach($ri_spay_order as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$ri_spay_order[$k-1]['cg']){
                    $ri_spay_order[$k]['rand'] = $ri_spay_order[$k-1]['rand'];
                }
            }
            foreach($yes_spay as $k=>$v){
                if($k==0){continue;}
                if($v['cg']==$yes_spay[$k-1]['cg']){
                    $yes_spay[$k]['rand'] = $yes_spay[$k-1]['rand'];
                }
            }
            foreach ($ri_spay_order as $key => $value) {
                $ri_spay_order[$key]['change']=0;
                $chart3['p'.$value['promote_id']] = $value;
                $value['is_set'] = false;
                foreach ($yes_spay as $k => $v) {
                    if($value['promote_id']==$v['promote_id']){
                        $value['is_set'] = true;    //判断过去时间段是否存在数据
                        $ri_spay_order[$key]['change']=$value['rand']-$v['rand'];break;
                    }else{
                        $value['is_set'] = false; //过去时间段无数据,排行无变化
                    }
                }
                if(!$value['is_set']){
                    $ri_spay_order[$key]['change'] = $value['rand'] - count($ri_spay_order)-1;
                }
            }
        }
        $data['reg']=$ri_ug_order;
        $data['active']=$ri_active_order;
        $data['pay']=$ri_spay_order;

        $chart4 = array_merge($chart1,$chart2,$chart3);
        foreach($chart4 as $k => $v) {
            $chart['promote'][$k] = $v['promote_account'];
            foreach($chart1 as $c) {
                $chart['reg'][$k] = 0;
                if ($v['promote_id'] == $c['promote_id']) {
                    $chart['reg'][$k] = (integer)$c['cg'];break;
                }
            }
            foreach($chart2 as $c) {
                $chart['active'][$k] = 0;
                if ($v['promote_id'] == $c['promote_id']) {
                    $chart['active'][$k] = (integer)$c['cg'];break;
                }
            }
            foreach($chart3 as $c) {
                $chart['pay'][$k] = 0;
                if ($v['promote_id'] == $c['promote_id']) {
                    $chart['pay'][$k] = $c['cg'];break;
                }
            }
        }

        foreach($chart as $k => $v) {
            if ($k == 'promote')
                $data['chart'][$k] = '"'.implode('","',$v).'"';
            else
                $data['chart'][$k] = implode(',',$v);
        }

        return $data;
    }


    /*
		 * 折线图
		 * @param integer  $start  开始时间
		 * @param integer  $end  	 结束时间
		 * @param boolean  $flag   是否ajax返回
		 * @author 鹿文学
		 */
    public function foldLineDiagram($start='',$end='',$num='',$flag=false) {

        $starttime = $start?strtotime($start):mktime(0,0,0,date('m'),date('d')-1,date('Y'));

        $endtime = $end?strtotime($end)+86399:$starttime+86399;

        $start = date('Y-m-d',$starttime);
        $end = date('Y-m-d',$endtime);

        $user = D('User');
        $spend = D('Spend');
        $access_log = D('AccessLog');

        if ($start == $end) {

            if ($start == date('Y-m-d',strtotime('-1 day'))) {$num = 2;}

            $hours = ['00','01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16','17','18','19','20','21','22','23'];

            $data['date'] = [$start];

            $data['hours'] = 1;

            foreach($hours as $v) {
                $data['news'][$v] = 0;
                $data['active'][$v] = 0;
                $data['player'][$v] = 0;
                $data['money'][$v] = 0;
                $data['access'][$v] = 0;
            }

            // 新增用户
            $hoursnews = $user->newsAdd(['register_time'=>['between',[$starttime,$endtime]]],'news','time',5);

            // 活跃用户
            $hoursactive = $user->totalPlayerByGroup($hours,['tab_user_login_record.login_time'=>['between',[$starttime,$endtime]]],'active','time',5);

            // 付费用户
            $hoursplayer = $spend->totalPlayerByGroup(['pay_time'=>['between',[$starttime,$endtime]]],'player','time',5);

            // 充值金额
            $hoursmoney = $spend->totalAmountByGroup(['pay_way'=>['gt',0],'pay_time'=>['between',[$starttime,$endtime]]],'money','time',5);

            //访问日志
            $access = $access_log->totalAccess(['access_time'=>['between',[$starttime,$endtime]]],'access','time',5,'access_time');

            foreach($hours as $v) {
                foreach($hoursnews as $h) {
                    $time = explode(' ',$h['time']);
                    if ($time[1] == $v){
                        $data['news'][$v] = (integer)$h['news'];break;
                    }
                }

                foreach($hoursactive as $h) {
                    if ($h['time'] == $v){
                        $data['active'][$v] = (integer)$h['active'];break;
                    }
                }

                foreach($hoursplayer as $h) {
                    $time = explode(' ',$h['time']);
                    if ($time[1] == $v){
                        $data['player'][$v] = (integer)$h['player'];break;
                    }
                }

                foreach($hoursmoney as $h) {
                    $time = explode(' ',$h['time']);
                    if ($time[1] == $v){
                        $data['money'][$v] = $h['money'];break;
                    }
                }
                foreach($access as $h) {
                    $time = explode(' ',$h['time']);
                    if ($time[1] == $v){
                        $data['access'][$v] = $h['access'];break;
                    }
                }

            }
        } else {

            $datelist = get_date_list($starttime,$endtime,$num==7?4:1);

            $data['date'] = $datelist;

            $data['hours'] = 0;
            foreach($datelist as $k => $v) {
                $data['news'][$v] = 0;
                $data['active'][$v] = 0;
                $data['player'][$v] = 0;
                $data['money'][$v] = 0;
                $data['access'][$v] = 0;
            }

            // 新增用户
            $news = $user->newsAdd(['register_time'=>['between',[$starttime,$endtime]]],'news','time',$num==7?2:1);

            // 活跃用户
            $active = $user->totalPlayerByGroup($datelist,['tab_user_login_record.login_time'=>['between',[$starttime,$endtime]]],'active','time',$num==7?2:1);

            // 付费用户
            $player = $spend->totalPlayerByGroup(['pay_time'=>['between',[$starttime,$endtime]]],'player','time',$num==7?2:1);

            // 充值金额
            $money = $spend->totalAmountByGroup(['pay_way'=>['gt',0],'pay_time'=>['between',[$starttime,$endtime]]],'money','time',$num==7?2:1);

            //访问日志
            $access = $access_log->totalAccess(['access_time'=>['between',[$starttime,$endtime]]],'access','time',$num==7?2:1,'access_time');

            foreach($datelist as $v) {
                foreach($news as $h) {
                    if ($v == $h['time']) {
                        $data['news'][$v] = (integer)$h['news'];break;
                    }
                }

                foreach($active as $h) {
                    if ($v == $h['time']) {
                        $data['active'][$v] = (integer)$h['active'];break;
                    }
                }

                foreach($player as $h) {
                    if ($v == $h['time']) {
                        $data['player'][$v] = (integer)$h['player'];break;
                    }
                }

                foreach($money as $h) {
                    if ($v == $h['time']) {
                        $data['money'][$v] = $h['money'];break;
                    }
                }

                foreach($access as $h) {
                    if ($v == $h['time']) {
                        $data['access'][$v] = $h['access'];break;
                    }
                }

            }

        }

        foreach($data as $k => $v) {

            if (is_array($v)) {
                if($data['hours']!=1){$table[$k] = $v;}
                if ($k == 'date'){
                    $data[$k] = '"'.implode('","',$v).'"';
                }else{
                    $sum = 0;$x='';$y=0;$tempexport=[];
                    foreach($v as $t => $s){
                        $sum += $s;
                        if($data['hours']==1){
                            if ($t%2==1) {$tab[$x.'~'.$t] = $y+$s;$x='';$y=0;}else{$x .= $t;$y += $s;}
                            $tempexport[]=['time'=>((integer)substr($t,0,2)).':00','count'=>$s];
                        }else{
                            $tempexport[]=['time'=>$t,'count'=>$s];
                        }
                    }
                    $table['sum'][$k]=$sum;
                    if($data['hours']==1)$table[$k]=$tab;
                    $export[$k]=$tempexport;
                    $export['sum'][$k]=$sum;
                    $data[$k] = implode(',',$v);
                }
            }
        }

        file_put_contents(dirname(__FILE__).'/access_data_foldline.txt',json_encode($export));
        if ($flag) {

            $data['table'] = $table;

            echo json_encode($data);

        } else {

            $this->assign('foldline',$data);

            $this->assign('table',$table);

            $this->assign('num',$num);

        }

    }



    //数据概况
    public function data_profile(){
        $keytype=$_REQUEST['key']==""?1:$_REQUEST['key'];
        $isbdpw['pay_way'] = array('not in',[-1,0]);
        $user=M('User','tab_');
        $spend=M('Spend','tab_');
        $deposit= M('Deposit','tab_');
        $bindrecharge= M('bindRecharge','tab_');
        if($keytype==1){
            $time=$this->time2other();
            $tt=$this->total(1);
            //注册数据
            $udata=$user->field('date_format(FROM_UNIXTIME( register_time),"%H") AS time,count(id) as count')->where('register_time'.$tt)->group('time')->select();
            $xtime=$this->for_every_time_point($time,$udata,'time','count');

            //充值数据
            //deposit
            $ddata = $deposit->field('date_format(FROM_UNIXTIME( create_time),"%H") AS time,sum(pay_amount) as sum')->where('create_time'.$tt)->where(array('pay_status'=>1))->group('time')->select();
            //bindrecharge
            $bdata = $bindrecharge->field('date_format(FROM_UNIXTIME( create_time),"%H") AS time,sum(real_amount) as sum')->where('create_time'.$tt)->where(array('pay_status'=>1))->group('time')->select();
            //spend
            $sdata=$spend->field('date_format(FROM_UNIXTIME( pay_time),"%H") AS time,sum(pay_amount) as sum')->where('pay_time'.$tt)->where(array('pay_status'=>1))->where($isbdpw)->group('time')->select();
            
            $xstime=$this->for_every_time_point($time,$sdata,'time','sum');
            $xdtime=$this->for_every_time_point($time,$ddata,'time','sum');
            $xbtime=$this->for_every_time_point($time,$ddata,'time','sum');
            foreach ($xstime as $key => $value) {
                $stime[$key]['sum']=$value['sum']+$xdtime[$key]['sum']+$xbtime[$key]['sum'];
            }
        }elseif($keytype==2){
        	//7天
            $time=$this->time2other('7day');
            $tt=$this->total(9);
            //注册数据
            $udata=$user->field('date_format(FROM_UNIXTIME( `register_time`),"%Y-%m-%d") AS time,count(id) as count')->where(array('register_time'.$tt))->where(array('fgame_id'=>array('gt',0)))->group('time')->order('time asc')->select();
            $xtime=$this->for_every_time_point($time,$udata,'time','count');

            //充值数据
            //deposit
            $ddata = $deposit->field('date_format(FROM_UNIXTIME( create_time),"%Y-%m-%d") AS time,sum(pay_amount) as sum')->where('create_time'.$tt)->where(array('pay_status'=>1))->group('time')->select();
            //bindrecharge
            $bdata = $bindrecharge->field('date_format(FROM_UNIXTIME( create_time),"%Y-%m-%d") AS time,sum(real_amount) as sum')->where('create_time'.$tt)->where(array('pay_status'=>1))->group('time')->select();
            //spend
            $sdata=$spend->field('date_format(FROM_UNIXTIME( pay_time),"%Y-%m-%d") AS time,sum(pay_amount) as sum')->where(array('pay_time'.$tt))->where(array('game_id'=>array('gt',0)))->where(array('pay_status'=>1))->where($isbdpw)->group('time')->order('time asc')->select();
            $xstime=$this->for_every_time_point($time,$sdata,'time','sum');
            $xdtime=$this->for_every_time_point($time,$ddata,'time','sum');
            $xbtime=$this->for_every_time_point($time,$bdata,'time','sum');
            foreach ($xstime as $key => $value) {
                $stime[$key]['sum']=$value['sum']+$xdtime[$key]['sum']+$xbtime[$key]['sum'];
            }        
        }elseif($keytype==3){//30天
            $time=$this->time2other('30day');
            $tt=$this->total(10);
            //注册数据
            $udata=$user->field('date_format(FROM_UNIXTIME( `register_time`),"%Y-%m-%d") AS time,count(id) as count')->where(array('register_time'.$tt))->where(array('fgame_id'=>array('gt',0)))->group('time')->order('time asc')->select();
            $xtime=$this->for_every_time_point($time,$udata,'time','count');

            //充值数据
            $ddata = $deposit->field('date_format(FROM_UNIXTIME( create_time),"%Y-%m-%d") AS time,sum(pay_amount) as sum')->where('create_time'.$tt)->where(array('pay_status'=>1))->group('time')->select();
            //bindrecharge
            $bdata = $bindrecharge->field('date_format(FROM_UNIXTIME( create_time),"%Y-%m-%d") AS time,sum(real_amount) as sum')->where('create_time'.$tt)->where(array('pay_status'=>1))->group('time')->select();
            //spend
            $sdata=$spend->field('date_format(FROM_UNIXTIME( pay_time),"%Y-%m-%d") AS time,sum(pay_amount) as sum')->where(array('pay_time'.$tt))->where(array('game_id'=>array('gt',0)))->where(array('pay_status'=>1))->where($isbdpw)->group('time')->order('time asc')->select();
            $xstime=$this->for_every_time_point($time,$sdata,'time','sum');
            $xdtime=$this->for_every_time_point($time,$ddata,'time','sum');
            $xbtime=$this->for_every_time_point($time,$dbata,'time','sum');
            foreach ($xstime as $key => $value) {
                $stime[$key]['sum']=$value['sum']+$xdtime[$key]['sum']+$xbtime[$key]['sum'];
            }        
        }elseif($keytype==4){//1年
            $time=$this->time2other('12mounth');
            $first = strtotime(reset($time).'-01');
            $tt=' between '.$first.' and '.time();
            //注册数据
            $udata=$user->field('date_format(FROM_UNIXTIME( `register_time`),"%Y-%m") AS time,count(id) as count')->where(array('register_time'.$tt))->where(array('fgame_id'=>array('gt',0)))->group('time')->order('time asc')->select();
            $xtime=$this->for_every_time_point($time,$udata,'time','count');

            //充值数据
            $ddata = $deposit->field('date_format(FROM_UNIXTIME( create_time),"%Y-%m") AS time,sum(pay_amount) as sum')->where('create_time'.$tt)->where(array('pay_status'=>1))->group('time')->select();
            //bindrecharge
            $bbata = $bindrecharge->field('date_format(FROM_UNIXTIME( create_time),"%Y-%m") AS time,sum(real_amount) as sum')->where('create_time'.$tt)->where(array('pay_status'=>1))->group('time')->select();

            //spend
            $sdata=$spend->field('date_format(FROM_UNIXTIME( pay_time),"%Y-%m") AS time,sum(pay_amount) as sum')->where(array('pay_time'.$tt))->where(array('game_id'=>array('gt',0)))->where(array('pay_status'=>1))->where($isbdpw)->group('time')->order('time asc')->select();
            
            $xstime=$this->for_every_time_point($time,$sdata,'time','sum');
            $xdtime=$this->for_every_time_point($time,$ddata,'time','sum');
            $xbtime=$this->for_every_time_point($time,$bbata,'time','sum');
            foreach ($xstime as $key => $value) {
                $stime[$key]['sum']=$value['sum']+$xdtime[$key]['sum']+$xbtime[$key]['sum'];
            }
        }
        // 前台显示
        // X轴日期
        if($keytype==1){
            $xAxis="[";
            foreach ($time as $tk => $tv) {
                $xAxis.="'".$tk.":00',";
            }
            $xAxis.="]";
        }elseif($keytype==2){
            sort($time);
            $xAxis="[";
            foreach ($time as $tk => $tv) {
                $xAxis.="'".$tv."',";
            }
            $xAxis.="]";
        }elseif($keytype==3){
            sort($time);
            $xAxis="[";
            foreach ($time as $tk => $tv) {
                $xAxis.="'".$tv."',";
            }
            $xAxis.="]";
        }elseif($keytype==4){
            sort($time);
            $xAxis="[";
            foreach ($time as $tk => $tv) {
                $xAxis.="'".$tv."',";
            }
            $xAxis.="]";
        }
        //x轴注册数据
        $xzdate="[";
        foreach ($xtime as $key => $value) {
            $xzdate.="'".$value['count']."',";
        }
        $xzdate.="]";
        //x轴充值数据
        $xsdate="[";
        foreach ($stime as $key => $value) {
            $xsdate.="'".$value['sum']."',";
        }
        $xsdate.="]";
        $this->assign('xzdate',$xzdate);
        $this->assign('xsdate',$xsdate);
        $this->assign('xAxis',$xAxis);
        $this->assign('qingxie',count($time));
        $this->display();
    }
    /**
     * [数据折线 分配每个时间段]
     * @param  [type] $time [时间点]
     * @return [type]       [description]
     */
    private function for_every_time_point($time,$data,$key1,$key2){
        foreach ($time as $key => $value) {
            $newdata[$key][$key2]=0;
            foreach ($data as $k => $v) {
                if($v[$key1]==$key){
                    $newdata[$key][$key2]=$v[$key2];
                }
            }
        }
        return $newdata;
    }
    //把时间戳 当前时间一天分成24小时  前七天 前30天  前12个月
    function time2other($type='day'){
        if($type=='day'){//一天分成24小时
            $start = mktime(0,0,0,date("m"),date("d"),date("y"));
            for($i = 0; $i < 24; $i++){
                static $x=0;
                $xx=$x++;
                if($xx<10){
                    $xxx='0'.$xx;
                }else{
                    $xxx=$xx;
                }
                
                $b = $start + ($i * 3600);
                $e = $start + (($i+1) * 3600)-1;
                $time[$xxx]="between $b and $e";
            }
        }
        if($type=='7day'){
            $ttime=array_reverse($this->every_day());
            foreach ($ttime as $key => $value) {
                $time[$value]=$value;
            }
        }
        if($type=='30day'){
            $ttime=array_reverse($this->every_day(30));
            foreach ($ttime as $key => $value) {
                $time[$value]=$value;
            }
        }
        if($type=='12mounth'){
            $ttime=array_reverse(before_mounth());
            foreach ($ttime as $key => $value) {
                $time[$value]=$value;
            }
        }
        
        return $time;
    }
    //以当前日期 默认前七天 
    private function every_day($m=7){
        $time=array();
        for ($i=0; $i <$m ; $i++) { 
            $time[]=date('Y-m-d',mktime(0,0,0,date('m'),date('d')-$i,date('Y')));
        }
        return $time;
    }
    private function total($type) {
        switch ($type) {
            case 1: { // 今天
                $start=mktime(0,0,0,date('m'),date('d'),date('Y'));
                $end=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
            };break;
             case 2: { // 本周
            //当前日期
            $sdefaultDate = date("Y-m-d");
            //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期
            $first=1;
            //获取当前周的第几天 周日是 0 周一到周六是 1 - 6
            $w=date('w',strtotime($sdefaultDate));
            //获取本周开始日期，如果$w是0，则表示周日，减去 6 天
            $week_start=date('Y-m-d',strtotime("$sdefaultDate -".($w ? $w - $first : 6).' days'));
            //本周结束日期
            $week_end=date('Y-m-d',strtotime("$week_start +6 days"));
                        //当前日期
            $sdefaultDate = date("Y-m-d");
            //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期
            $first=1;
            //获取当前周的第几天 周日是 0 周一到周六是 1 - 6
            $w=date('w',strtotime($sdefaultDate));
            //获取本周开始日期，如果$w是0，则表示周日，减去 6 天
            $start=strtotime("$sdefaultDate -".($w ? $w - $first : 6).' days');
            //本周结束日期
            $end=$start+7*24*60*60-1;
        };break;
            case 3: { // 本月
                $start=mktime(0,0,0,date('m'),1,date('Y'));
                $end=mktime(0,0,0,date('m')+1,1,date('Y'))-1;
            };break;
            case 4: { // 本年
                $start=mktime(0,0,0,1,1,date('Y'));
                $end=mktime(0,0,0,1,1,date('Y')+1)-1;
            };break;
            case 5: { // 昨天
                $start=mktime(0,0,0,date('m'),date('d')-1,date('Y'));
                $end=mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
            };break;
            case 6: { // 上周
                $start=mktime(0, 0 , 0,date("m"),date("d")-date("w")+1-7,date("Y"));
                $end=mktime(23,59,59,date("m"),date("d")-date("w")+7-7,date("Y"));
            };break;
            case 7: { // 上月
                $start=mktime(0, 0 , 0,date("m")-1,1,date("Y"));
                $end=mktime(23,59,59,date("m") ,0,date("Y"));
            };break;
            case 8: { // 上一年
//                $start=mktime(0,0,0,date('m')-11,1,date('Y'));
                $start=mktime(0,0,0,1,1,date('Y')-1);
//                $end=mktime(0,0,0,date('m')+1,1,date('Y'))-1;
                $end=mktime(23,59,59,12,31,date('Y')-1);
            };break;
            case 9: { // 前七天
                $start = mktime(0,0,0,date('m'),date('d')-6,date('Y'));
                $end=mktime(23,59,59,date('m'),date('d'),date('Y'));
            };break;
            case 10: { // 前30天
                $start = mktime(0,0,0,date('m'),date('d')-29,date('Y'));
                $end=mktime(23,59,59,date('m'),date('d'),date('Y'));
            };break;
            default:
                $start='';$end='';
        }
        return " between $start and $end ";
    }
    private function huanwei($total) {
        $total=$total?$total:0;
        if(!strstr($total,'.')){
            $total=$total.'.00';
        }
        $total = empty($total)?'0':trim($total.' ');
        $len = strlen($total);
        if ($len>7) { // 万
            $total = (round(($total/10000),2)).'w';
        }
        return $total;
    }

    /*
    * LTV统计
    * @param integer $p  当前页
    * @author 郭家屯
    */
    public function new_ltv($p=1) {
        set_time_limit(0);
        $page = intval($p);

        $page = $page ? $page : 1; //默认显示第一页数据

        //        $row = get_list_row();//10;
        $row = C('LIST_ROWS') ? C('LIST_ROWS') : 10;//10;

        $start = $_GET['start']= I('start',date('Y-m-d',strtotime('-30 day')));

        $end =  I('end',date('Y-m-d',strtotime('-1 day')));

        $end = strtotime($end)>=strtotime(date('Y-m-d'))?date('Y-m-d',strtotime('-1 day')):$end;

        $_GET['end'] = $end;

        $list = D('user')->new_ltv(strtotime($start),strtotime($end));

        $count = count($list);

        $data = array_slice($list,($page-1)*$row,$row,true);

        file_put_contents(dirname(__FILE__).'/access_data_new_ltv.txt',json_encode($list));

        if($count > $row){

            $page = new \Think\Page($count, $row);

            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');

            $this->assign('_page', $page->show());

        }

        $this->meta_title = 'LTV统计';

        $this->assign('data',$data);

        $this->display();

    }



    /*
	 * LTV统计
	 * @param integer $p  当前页
	 * @author 鹿文学
	 */
    public function ltv($p=1) {
        // $stime=microtime(true); //获取程序开始执行的时间
        $page = intval($p);

        $page = $page ? $page : 1; //默认显示第一页数据

        $row = C('LIST_ROWS') ? C('LIST_ROWS') : 10;//10;
        if (is_file(dirname(__FILE__).'/access_data_ltv.txt')&&I('get.p')>0) {
            $filetxt = file_get_contents(dirname(__FILE__).'/access_data_ltv.txt');
            $list_data = json_decode($filetxt,true);
            
        }else{
            $start = $_GET['start']= I('start',date('Y-m-d',strtotime('-30 day')));

            $end =  I('end',date('Y-m-d',strtotime('-1 day')));

            $end = strtotime($end)>=strtotime(date('Y-m-d'))?date('Y-m-d',strtotime('-1 day')):$end;

            $_GET['end'] = $end;

            $list = D('user')->ltv(strtotime($start),strtotime($end));

            $count = count($list);
            $list_data['data'] = $list;
            $list_data['count'] = $count;
            file_put_contents(dirname(__FILE__).'/access_data_ltv.txt',json_encode($list_data));
        }
        $count = $list_data['count'];
        $data = $this->array_order_page($list_data['count'],$page,$list_data['data'],$row);
        $this->meta_title = 'LTV统计';

        $this->assign('data',$data);
        $this->display();
        // $etime=microtime(true);//获取程序执行结束的时间
        // $total=$etime-$stime;   //计算差值
        // echo "<br />[页面执行时间：{$total} ]秒";exit;

    }


}
