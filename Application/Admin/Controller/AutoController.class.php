<?php
/**
 * 定时自动完成
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/3/22
 * Time: 11:40
 */
namespace Admin\Controller;
use Admin\Model\SpendModel;
use Think\Think;
use Com\WechatAuth;

class AutoController extends Think {


    public function run(){
        //自动补单
        $repair = new SpendModel();
        $repair::auto_repair();
    }

    public function stat(){
        $info = $this->sitestat();
        $stat = $this->statistics();
        //玩家注册
        $data['player_regist_yes'] = $info['yesterday'];
        $data['player_regist_tod'] = $info['today'];
        $data['player_regist_week'] = $stat['realtime_data']['thisweek_user'];
        $data['player_reigst_mon'] = $stat['realtime_data']['thismounth_user'];
        $data['player_regist_all'] = $info['user'];
        //玩家活跃
        $data['act_yes'] = $info['ylogin'];
        $data['act_tod'] = $info['tlogin'];
        $data['act_seven'] = $info['ulogin'];
        $data['act_week'] = $stat['realtime_data']['thisweek_active'];
        $data['act_mon'] = $stat['realtime_data']['thismounth_active'];
        //充值人数
        $data['payer_yes'] = $info['yfufei'];
        $data['payer_tod'] = $info['tfufei'];
        $data['payer_all'] = $info['afufei'];
        //充值
        $data['pay_add_yes'] = $info['ysamount'];
        $data['pay_add_tod'] = $info['tsamount'];
        $data['pay_add_all'] = $info['asamount'];
        //流水
        $data['pay_tod'] = $stat['realtime_data']['today_pay'];
        $data['pay_week'] = $stat['realtime_data']['thisweek_pay'];
        $data['pay_mon'] = $stat['realtime_data']['thismounth_pay'];
        $data['pay_all'] = $stat['platform_data']['all_pay'];
        //游戏接入数量
        $data['game_add_yes'] = $info['yadd'];
        $data['game_add_tod'] = $info['tadd'];
        $data['game_add_all'] = $info['game'];
        $data['game_and_all'] = $stat['platform_data']['all_android'];
        $data['game_ios_all'] = $stat['platform_data']['all_ios'];
        //渠道增加数量
        $data['pro_add_yes'] = $info['ypadd'];
        $data['pro_add_tod'] = $info['tpadd'];
        $data['pro_add_all'] = $info['promote'];
        $data['pro_complete'] = $stat['platform_data']['all_promote'];
        //渠道支付总数
        $data['pro_pay_all'] = $stat['platform_data']['all_tpay'];
        //推广渠道总数
        $data['pro_player_all'] = $stat['platform_data']['all_tuser'];
        //自然注册用户
        $data['user_reg_num'] = $stat['platform_data']['all_zuser'];
        //自然注册总充值
        $data['user_pay_num'] = $stat['platform_data']['all_zpay'];
        $data['create_time'] = time();
        M('Data','tab_')->add($data);
        echo "更新成功";
    }

    public function sitestat(){

        $user = M("User","tab_");
        $userlogin = M("UserLoginRecord","tab_");
        $game = M("Game","tab_");
        $spend = M('Spend',"tab_");
        $deposit = M('Deposit',"tab_");
        $promote = M("Promote","tab_");
        $yesterday = $this->total(5);
        $today = $this->total(1);
        $week = $this->total(9);
        $month = $this->total(3);
        $info['user'] = $user->count();//总注册
        $info['yesterday']= $user->where("register_time".$yesterday)->count();//昨日总注册
        $info['today']= $user->where("register_time".$today)->count();//今日总注册

        $info['game'] = $game->where(['apply_status'=>1,'online_status'=>1])->count();//总游戏
        $info['tadd'] = $game->where("create_time".$today)->where(['apply_status'=>1,'online_status'=>1])->count();//今日添加
        $info['yadd'] = $game->where("create_time".$yesterday)->where(['apply_status'=>1,'online_status'=>1])->count();//昨日添加

        //七日活跃
        $seven_day = every_day();
        $first_time = strtotime($seven_day[0]);
        //在时间范围前注册的用户，并在时间范围内又登录
        $lastuser = $user
                    ->field('id as user_id')
                    ->where(['register_time'=>['lt',$first_time]])
                    ->select(false);
        $userssql = $userlogin
                    ->field('user_id')
                    ->where("login_time".$week)
                    ->union($lastuser,true)
                    ->group('user_id')
                    ->select(false);
        $uusers = "select user_id from (".$userssql.' ) as tt group by user_id having count(user_id)>1';

        //在时间范围内 注册 并在注册时间外又登录
        $ulogin = $this->moreDayActive($user,$userlogin,$week);
        //今日活跃
        $tlogin = $this->oneDayActive($user,$userlogin,$today);
        //昨日活跃
        $ylogin = $this->oneDayActive($user,$userlogin,$yesterday);
        $ulogin=count($ulogin);
        $tlogin=count($tlogin);
        $ylogin=count($ylogin);
        $info['ulogin'] = $ulogin;
        $info['tlogin'] = $ylogin;
        $info['ylogin'] = $tlogin;
        //
        // 付费玩家  游戏付费+平台币+绑币充值
        //全部平台币充值
        $abfufei = M('bind_recharge','tab_')->field('user_id')->where(array('pay_status'=>1))->group('user_id')->select(false);//绑币
        $adfufei=$deposit
            ->field('user_id')
            ->where(array('pay_status'=>1))
            ->group('user_id')
            ->select(false);//平台币
        //三表并集
        $afufei=$spend
            ->field('user_id')
            ->union($abfufei)
            ->union($adfufei)
            ->where(array('pay_status'=>1,'pay_way'=>array('gt',0)))
            ->group('user_id')
            ->select();
        //昨日平台币充值
        $ybfufei = M('bind_recharge','tab_')->field('user_id')->where(array("pay_status=1 and create_time".$yesterday))->group('user_id')->select(false);//绑币
        $ydfufei=$deposit
            ->field('user_id')
            ->where("pay_status=1 and create_time".$yesterday)
            ->group('user_id')
            ->select(false);
        //两表并集
        $yfufei=$spend
            ->field('user_id')
            ->union($ybfufei)
            ->union($ydfufei)
            ->where("pay_status=1 and pay_time".$yesterday)
            ->group('user_id')
            ->select();
        //今日平台币充值
        $tbfufei = M('bind_recharge','tab_')->field('user_id')->where(array("pay_status=1 and create_time".$today))->group('user_id')->select(false);//绑币
        $tdfufei=$deposit
            ->field('user_id')
            ->where("pay_status=1 and create_time".$today)
            ->group('user_id')
            ->select(false);
        //两表并集
        $tfufei=$spend
            ->field('user_id')
            ->union($tbfufei)
            ->union($tdfufei)
            ->where("pay_status=1 and pay_time".$today)
            ->group('user_id')
            ->select();
        $info['afufei']=count($afufei);
        $info['yfufei']=count($yfufei);
        $info['tfufei']=count($tfufei);
        // 游戏充值
        $asamount = $spend->field('sum(pay_amount) as amount')->where("pay_status=1 ")->find();//所有支付方式
        $tsamount = $spend->field('sum(pay_amount) as amount')->where("pay_status=1 and pay_time".$today)->find();//所有支付方式
        $ysamount = $spend->field('sum(pay_amount) as amount')->where("pay_status=1 and pay_time".$yesterday)->find();//所有支付方式
        $info['asamount'] = $asamount['amount']?$asamount['amount']:0;
        $info['tsamount'] = $tsamount['amount']?$tsamount['amount']:0;
        $info['ysamount'] = $ysamount['amount']?$ysamount['amount']:0;

        $info['promote'] = $promote->count();
        $info['tpadd'] = $promote->where("create_time".$today)->count();
        $info['ypadd'] = $promote->where("create_time".$yesterday)->count();

        $doc = D("Document");
        $b =$this->cate("blog");
        $m =$this->cate("media");
        $blog = $doc->table("__DOCUMENT__ as d")
            ->where("d.status=1 and d.display=1 and d.category_id in (".$b.")")->count();
        $media = $doc->table("__DOCUMENT__ as d")
            ->where("d.status=1 and d.display=1 and d.category_id in (".$m.")")->count();
        $info['document'] = $this->test($blog + $media);
        $info['blog']=$this->test($blog);
        $info['media']=$this->test($media);

        return $info;
    }


    public function statistics(){

        //定义表名
        $user = M("User","tab_");
        $userlogin = M("user_login_record","tab_");
        $spend = M('Spend',"tab_");
        $bindrecharge = M('bind_recharge','tab_');
        $deposit = M('Deposit',"tab_");
        $promote = M("Promote","tab_");
        $game = M("Game","tab_");
        $gamesource = M("Game_source","tab_");

        //平台数据概况
        $platform_data['all_user']=$user->count();//累计注册玩家人数


        //游戏+平台币+绑币充值人数
        $abfufei=$bindrecharge
            ->field('user_id')
            ->where(array('pay_status'=>1))
            ->group('user_id')
            ->select(false);
        $adfufei=$deposit
            ->field('user_id')
            ->where(array('pay_status'=>1))
            ->group('user_id')
            ->select(false);
        //三表并集
        $afufei=$spend
            ->field('user_id')
            ->union($abfufei)
            ->union($adfufei)
            ->where(array('pay_status'=>1))
            ->group('user_id')
            ->select();
        $platform_data['all_pay_user']=count($afufei);
        //累计流水
        $spendmap['pay_status']=1;
        $bindRecharge_data = $bindrecharge->where($spendmap)->sum('real_amount');//绑币充值 实付金额
        $deposit_data = $deposit->where($spendmap)->sum('pay_amount');//平台币充值
        $spendmap['pay_way']=array('gt',0);
        $spend_data = $spend->where($spendmap)->sum('pay_amount');//游戏消费
        $spay = $bindRecharge_data + $deposit_data + $spend_data;
        $platform_data['all_pay']=$this->test($spay);//累计流水

        $platform_data['all_promote']=$promote->where(array('status'=>1))->count();//累计渠道

        $platform_data['all_game']=$game->where(['apply_status'=>1,'online_status'=>1])->count();//累计游戏

        $platform_data['all_android']=$gamesource->where(array('file_type'=>1))->count();//累计安卓包

        $platform_data['all_ios']=$gamesource->where(array('file_type'=>2))->count();//累计苹果包

        $platform_data['all_tuser']=$user->where(array('promote_id'=>array('gt',0)))->count();//累计渠道注册玩家
        $platform_data['all_zuser']= $user->where(array('promote_id'=>0))->count();//累计自然注册玩家
        //累计渠道总流水
        //$tspay=$spend->where(array('promote_id'=>array('gt',0)))->where(array('pay_status'=>1))->sum('pay_amount');
        //$dspay =$deposit->where(array('promote_id'=>array('gt',0)))->where(array('pay_status'=>1))->sum('pay_amount');
        $pspendmap['pay_status']=1;
        $pspendmap['promote_id']=array('neq',0);
        $pbindRecharge_data = M('bind_recharge','tab_')->where($pspendmap)->sum('real_amount');
        $pdeposit_data = $deposit->where($pspendmap)->sum('pay_amount');
        $pspendmap['pay_way']=array('gt',0);
        $pspend_data = $spend->where($pspendmap)->sum('pay_amount');
        $tspay = $pbindRecharge_data + $pdeposit_data + $pspend_data;
        $platform_data['all_tpay']=$this->test($tspay);
        //累计自然注册充值
        $zpspendmap['pay_status']=1;
        $zpspendmap['promote_id']=0;
        $zpbindRecharge_data = M('bind_recharge','tab_')->where($zpspendmap)->sum('real_amount');
        $zpdeposit_data = $deposit->where($zpspendmap)->sum('pay_amount');
        $zpspendmap['pay_way']=array('gt',0);
        $zpspend_data = $spend->where($zpspendmap)->sum('pay_amount');
        $zspay = $zpbindRecharge_data + $zpdeposit_data + $zpspend_data;
        $platform_data['all_zpay']=$this->test($zspay);
        $result['platform_data'] = $platform_data;

        //实时数据概况
        $today = $this->total(1);
        $thisweek = $this->total(2);
        $thismounth = $this->total(3);
        //注册
        $realtime_data['today_user']=$user->where(array('register_time'.$today))->count();//今日注册

        $realtime_data['thisweek_user']=$user->where(array('register_time'.$thisweek))->count();//本周注册

        $realtime_data['thismounth_user']=$user->where(array('register_time'.$thismounth))->count();//本月注册

        //活跃

        //今日活跃
        $turel=$user
            ->field('id as user_id')
            ->where('register_time'.$today)
            ->select(false);
        $tlogin=$userlogin
            ->field('user_id')
            ->where("login_time".$today)
            ->union($turel)
            ->group('user_id')
            ->select(false);
        $tlogin = $this->oneDayActive($user,$userlogin,$today);
        //本周活跃
        $wlogin = $this->moreDayActive($user,$userlogin,$thisweek);
        //本月活跃
        $mlogin = $this->moreDayActive($user,$userlogin,$thismounth);
        $realtime_data['today_active']=count($tlogin);
        $realtime_data['thisweek_active']=count($wlogin);
        $realtime_data['thismounth_active']=count($mlogin);

        //今日流水
        $todayspay=$spend->where(array('pay_time'.$today))->where(array('pay_status'=>1))->sum('pay_amount');
        $todaydpay=$deposit->where(array('create_time'.$today))->where(array('pay_status'=>1))->sum('pay_amount');//平台币
        $todaybpay=$bindrecharge->where(array('create_time'.$today))->where(array('pay_status'=>1))->sum('real_amount');//绑币
        $realtime_data['today_pay']=$this->test($todayspay+$todaydpay+$todaybpay);
        //本周流水
        $weekspay=$spend->where(array('pay_time'.$thisweek))->where(array('pay_status'=>1))->sum('pay_amount');
        $weekdpay=$deposit->where(array('create_time'.$thisweek))->where(array('pay_status'=>1))->sum('pay_amount');//平台币
        $weekbpay=$bindrecharge->where(array('create_time'.$thisweek))->where(array('pay_status'=>1))->sum('real_amount');//绑币
        $realtime_data['thisweek_pay']=$this->test($weekspay+$weekdpay+$weekbpay);
        //本月流水
        $mounthspay=$spend->where(array('pay_time'.$thismounth))->where(array('pay_status'=>1))->sum('pay_amount');
        $mounthdpay=$deposit->where(array('create_time'.$thismounth))->where(array('pay_status'=>1))->sum('pay_amount');//平台币
        $mounthbpay=$bindrecharge->where(array('create_time'.$thismounth))->where(array('pay_status'=>1))->sum('real_amount');//绑币
        $realtime_data['thismounth_pay']=$this->test($mounthspay+$mounthdpay+$mounthbpay);
        $result['realtime_data'] = $realtime_data;
                
        return $result;

    }

    public function first_ltv($start='',$end=''){
        if(empty($start) || empty($end))exit('时间周期不正确');
        $datelist = get_date_list(strtotime($start),strtotime($end));
        foreach ($datelist as $k => $v) {
            $ltv = M('ltv','tab_')->where(['time'=>$v])->field('id')->find();
            if(!$ltv){
                $data['time'] = $v;
                $between = array('between',array(strtotime($v),strtotime($v)+86399));
                //新增注册
                $user = M('user','tab_')->field('id')->where(['register_time'=>$between])->select();
                $data['new_count'] = count($user);
                $data['new_id'] = implode(',',array_column($user,'id'));
                //充值金额
                $deposit = M('deposit','tab_')->field('sum(pay_amount) as money')
                    ->where(['create_time'=>$between,'pay_status'=>1])
                    ->find();
                $spend = M('spend','tab_')->field('sum(pay_amount) as money')
                    ->where(['pay_time'=>$between,'pay_status'=>1,'pay_way'=>['gt',0]])->find();
                $data['money'] = $deposit['money']+$spend['money'];
                //活跃玩家
                $map['tab_user_login_record.login_time'] = $between;
                $map['register_time'] = array('lt',strtotime($v));
                $active1 = M('user','tab_')->distinct(true)->field('tab_user.id')
                    ->join('tab_user_login_record on tab_user_login_record.user_id=tab_user.id','inner')
                    ->where($map)->order('tab_user.id asc')->select();
                $map1['register_time'] = $between;
                $active2 = M('user','tab_')->field('tab_user.id')->field('id')->where($map1)->select();
                $active = array_merge($active1,$active2);
                $data['active_count'] = count($active);
                $data['active_id'] = implode(',',array_column($active,'id'));
                $data['create_time'] = time();
                M('ltv','tab_')->add($data);
                echo '执行时间：'.$v."<br/>";
            }
        }
    }

    public function ltv(){
        $datelist = get_date_list(strtotime('-7 day'),strtotime('-1 day'));
        foreach ($datelist as $k => $v) {
            $ltv = M('ltv','tab_')->where(['time'=>$v])->field('id')->find();
            if(!$ltv){
                $data['time'] = $v;
                $between = array('between',array(strtotime($v),strtotime($v)+86399));
                //新增注册
                $user = M('user','tab_')->field('id')->where(['register_time'=>$between])->select();
                $data['new_count'] = count($user);
                $data['new_id'] = implode(',',array_column($user,'id'));
                //充值金额
                $deposit = M('deposit','tab_')->field('sum(pay_amount) as money')
                    ->where(['create_time'=>$between,'pay_status'=>1])
                    ->find();
                $spend = M('spend','tab_')->field('sum(pay_amount) as money')
                    ->where(['pay_time'=>$between,'pay_status'=>1,'pay_way'=>['gt',0]])->find();
                $data['money'] = $deposit['money']+$spend['money'];
                //活跃玩家
                $map['tab_user_login_record.login_time'] = $between;
                $map['register_time'] = array('lt',strtotime($v));
                $active1 = M('user','tab_')->distinct(true)->field('tab_user.id')
                    ->join('tab_user_login_record on tab_user_login_record.user_id=tab_user.id','inner')
                    ->where($map)->order('tab_user.id asc')->select();
                $map1['register_time'] = $between;
                $active2 = M('user','tab_')->field('tab_user.id')->field('id')->where($map1)->select();
                $active = array_merge($active1,$active2);
                $data['active_count'] = count($active);
                $data['active_id'] = implode(',',array_column($active,'id'));
                $data['create_time'] = time();
                M('ltv','tab_')->add($data);
                echo '执行时间：'.$v."<br/>";
            }
        }
    }


    //小程序更新
    public function smallGameStat(){
        $model = M("small_game","tab_");
        $data = $model->field('id,thumbnail,wechat_id')->where(['type'=>0])->select();
        foreach ($data as $key=>$vo){
            $wechat = M('wechat','tab_')->field('id,appid,secret')->where(['id'=>$vo['wechat_id']])->find();
            $appid = $wechat['appid'];
            $appsecret = $wechat['secret'];
            if($appid == C('wechat.appid')){
                $filename = 'access_token_validity.txt';
            }else{
                $filename = 'access_token_validity_'.$data['wechat_id'].'.txt';
            }
            $result = auto_get_access_token($filename);
            if (!$result['is_validity']) {
                //获取access_token
                $auth = new WechatAuth($appid, $appsecret);
                $token = $auth->getAccessToken();
                $token['expires_in_validity'] = time() + $token['expires_in'];
                wite_text(json_encode($token), $filename);
                $result = auto_get_access_token($filename);
            }
            //获取access_token去生成临时二维码存入数据库
            $auth = new WechatAuth($appid, $appsecret,$result['access_token']);
            $res  = $auth->qrcodeCreate("SM_".$vo['id'],259200);
            $qrcodeurl = $auth->showqrcode($res['ticket']);
            $result = $auth->materialAddMaterial(substr(get_cover($vo['thumbnail'],'path'),1),'image');
            $model->where(array('id'=>$vo['id']))->save(array('qrcodeurl'=>$qrcodeurl,'qrcode_time'=>time(),'media_id'=>$result['media_id'],'update_time'=>time()));
        }
        return true;
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
                $start=mktime(0,0,0,date('m')-11,1,date('Y'));
                $end=mktime(0,0,0,date('m')+1,1,date('Y'))-1;
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

    public function test($test){
        return $test?$test:0;
    }

    private function huanwei($total) {
        $total = empty($total)?'0':trim($total.' ');
        if(!strstr($total,'.')){
            $total=$total.'.00';
        }
        $len = strlen($total);
        if ($len>7) { // 万
            $total = (round(($total/10000),2)).'w';
        }
        return $total;
    }

    private function cate($name) {
        $cate = M("Category");
        $c = $cate->field('id')->where("status=1 and display=1 and name='$name'")->buildSql();
        $ca = $cate->field('id')->where("status=1 and display=1 and pid=$c")->select();
        foreach($ca as $c) {
            $d[]=$c['id'];
        }
        return "'".implode("','",$d)."'";
    }

    //单日活跃
    private function oneDayActive($user,$userlogin,$time){
        $lasttimearr = explode(' ',trim($time));
        $lasttime = $lasttimearr[1];
        $turel=$user
            ->field('id as user_id')
            ->where(['register_time'=>['lt',$lasttime]])
            ->select(false);
        $tloginsql=$userlogin
            ->field('user_id')
            ->where("login_time".$time)
            ->union($turel,true)
            ->group('user_id')
            ->select(false);
        $tlogin_q = "select user_id from (".$tloginsql.' ) as tt group by user_id having count(user_id)>1';
        $tlogin = M()->query($tlogin_q);
        return $tlogin;
    }
    //在时间范围内 注册 并在注册时间外又登录
    private function moreDayActive($user,$userlogin,$time){
        $moredayuser = $user
                        ->field('id as user_id,register_time')
                        ->where('register_time '.$time)
                        ->select(false);
        $moredaylogin = $userlogin
                        ->alias('ul')
                        ->field("ul.user_id,FROM_UNIXTIME(rg.register_time,'%Y-%m-%d') as register_date,FROM_UNIXTIME(login_time,'%Y-%m-%d') as login_date")
                        ->where('login_time '.$time)
                        ->join('('.$moredayuser.') as rg on rg.user_id = ul.user_id')
                        ->having('register_date <> login_date')
                        ->select(false);
        $uloginsql = 'select * from ('.$moredaylogin.') as ll group by user_id';
        $ulogin = M()->query($uloginsql);
        return $ulogin;
    }
}