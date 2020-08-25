<?php
// +----------------------------------------------------------------------
// | 徐州梦创信息科技有限公司—专业的游戏运营，推广解决方案.
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.vlcms.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: kefu@vlcms.com QQ：97471547
// +----------------------------------------------------------------------
namespace Admin\Event;
use Admin\Model\SpendModel;
use Admin\Model\UserLoginRecordModel;
use Admin\Model\UserPlayModel;
use Think\Controller;

/**
 * 后台首页控制器
 * 
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class StatEvent extends Controller
{

    /**
     * 充值统计
     */
    public function spend_statistics()
    {
        $model_name = "spend";
        $serach_field = "pay_time";
        $field = "sum(pay_amount) as amount,pay_way";
        $group = "pay_way";
        $order = "pay_way ASC";
        $last_month_amount = $this->last_month_data($model_name, $serach_field, $field, $group, $order);
        $last_month_total = array_sum(array(
            $last_month_amount[0]["amount"],
            $last_month_amount[1]["amount"],
            $last_month_amount[2]["amount"]
        ));
        foreach ($last_month_amount as $key => $value) {
            switch ($value['pay_way']) {
                case 0:
                    $ptb =$ptb + $value["amount"];
                    break;
                case 1: // 支付宝
                    $alipay = $alipay + $value["amount"];
                    break;
                case 2: // 微信
                    $weixin = $weixin + $value["amount"];
                    break;
            }
        }
        $last_data = array(
            $ptb,
            $alipay,
            $weixin,
            $last_month_total == "" ? 0 : $last_month_total
        );
        $this_month_amount = $this->this_month_data($model_name, $serach_field, $field, $group, $order);
        $this_month_total = array_sum(array(
            $this_month_amount[0]["amount"],
            $this_month_amount[1]["amount"],
            $this_month_amount[2]["amount"]
        ));
        foreach ($this_month_amount as $key => $value) {
            switch ($value['pay_way']) {
                case 0:
                    $ptb2 = $ptb2 + $value["amount"];
                    break;
                case 1:
                    $alipay2 = $alipay2 + $value["amount"];
                    break;
                case 2:
                    $weixin2 = $weixin2 + $value["amount"];
                    break;
            }
        }
        $this_data = array(
            $ptb2,
            $alipay2,
            $weixin2,
            $this_month_total == "" ? 0 : $this_month_total
        );
        $this->assign("spend_last_data", $last_data);
        $this->assign("spend_this_data", $this_data);
    }

    /**
     * 注册统计
     */
    public function register_statistics()
    {
        $model_name = "User";
        $serach_field = "register_time";
        $field = "count(id) as counts,register_way";
        $group = "register_way";
        $order = "register_way ASC";
        $last_month1 = $this->last_month_data($model_name, $serach_field, $field, $group, $order);
        foreach($last_month1 as $k=>$val){
            $value[]=$val['register_way'];
        }
        $kvalue=array_flip($value);
        for ($i=1; $i <5; $i++) { 
            if(!in_array($i,$value)){
                $last_month[$i]=array('counts'=>0,'register_way'=>$i);
            }else{
                $kk=$kvalue[$i];
                $last_month[$i]=$last_month1[$kk];
            }
        }
        for ($i=1; $i <5 ; $i++) { 
            $last_month2[]=$last_month[$i]["counts"];
        }
        $last_month_total = array_sum($last_month2);
        arsort($last_month);
        $last_data=$last_month;
        $last_data[]=$last_month_total;


        $this_month1 = $this->this_month_data($model_name, $serach_field, $field, $group, $order);
        foreach($this_month1 as $k=>$val){
            $this_value[]=$val['register_way'];
        }
        $this_kvalue=array_flip($this_value);
        for ($this_i=1; $this_i <5; $this_i++) { 
            if(!in_array($this_i,$this_value)){
                $this_month[$this_i]=array('counts'=>0,'register_way'=>$this_i);
            }else{
                $this_kk=$this_kvalue[$this_i];
                $this_month[$this_i]=$this_month1[$this_kk];
            }
        }
        for ($this_a=1; $this_a <5 ; $this_a++) { 
            $this_month2[]=$this_month[$this_a]["counts"];
        }
        $this_month_total = array_sum($this_month2);
        arsort($this_month);
        $this_data=$this_month;
        $this_data[]=$this_month_total;
        $this->assign("reg_last_data", $last_data);
        $this->assign("reg_this_data", $this_data);
    }

    /**
     * 本年总充值
     */
    public function spend_statistics_year()
    {
        $model_name = "spend";
        $serach_field = "pay_time";
        $field = "FROM_UNIXTIME(pay_time, '%c') as month,sum(pay_amount) as amount";
        $group = "FROM_UNIXTIME(pay_time,'%Y%m%d')";
        $order = "pay_time ASC";
        $map["pay_status"] = 1;
        $year_total = $this->data_year($model_name, $map, $serach_field, $field, $group, $order);
        $map["promote_id"] = array(
            "neq",
            "0"
        );
        $map2["promote_id"] = array(
            "eq",
            "0"
        );
        $map2["pay_status"] = 1;
        $year_promote = $this->data_year($model_name, $map, $serach_field, $field, $group, $order, $where);
        $ziran_promote = $this->data_year($model_name, $map2, $serach_field, $field, $group, $order, $where);
        $this->assign("ziran_promote", $ziran_promote);
        $this->assign("year_total", $year_total);
        $this->assign("year_promote", $year_promote);
    }

    /**
     * 本年总注册
     */
    public function register_statistics_year()
    {
        $model_name = "User";
        $serach_field = "register_time";
        $field = "FROM_UNIXTIME(register_time, '%c') as month,count(id) as counts";
        $group = "FROM_UNIXTIME(register_time,'%c')";
        $order = "register_time ASC";
        $map["lock_status"] = 1;
        $last_data = $this->user_data_year($model_name, $map, $serach_field, $field, $group, $order);
        $map["promote_id"] = array(
            "neq",
            "0"
        );
        $map2["promote_id"] = array(
            "eq",
            "0"
        );
        $this_data = $this->user_data_year($model_name, $map, $serach_field, $field, $group, $order);
        $ziran_data = $this->user_data_year($model_name, $map2, $serach_field, $field, $group, $order);
        $this->assign("reg_data_year", $last_data);
        $this->assign("prom_data_year", $this_data);
        $this->assign("ziran_data_year", $ziran_data);
    }

    /**
     * 上月数据
     */
    public function last_month_data($model_name, $serach_field, $field = true, $group = "", $order = "")
    {
        $last_month_start = strtotime(date("Y-m-d H:i:s", mktime(0, 0, 0, date("m") - 1, 1, date("Y"))));
        $last_month_end = strtotime(date("Y-m-d H:i:s", mktime(23, 59, 59, date("m"), 0, date("Y"))));
        
        $map[$serach_field] = array(
            "BETWEEN",
            array(
                $last_month_start,
                $last_month_end
            )
        );
        $model = D($model_name);
        $data = $model->field($field)
            ->where($map)
            ->group($group)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 本月数据
     */
    protected function this_month_data($model_name, $serach_field, $field = true, $group = "", $order = "")
    {
        $this_month_start = strtotime(date("Y-m-d H:i:s", mktime(0, 0, 0, date("m"), 1, date("Y"))));
        $this_month_end = strtotime(date("Y-m-d H:i:s", mktime(23, 59, 59, date("m"), date("t"), date("Y"))));
        $map[$serach_field] = array(
            "BETWEEN",
            array(
                $this_month_start,
                $this_month_end
            )
        );
        $model = D($model_name);
        $data = $model->field($field)
            ->where($map)
            ->group($group)
            ->order($order)
            ->select();
        return $data;
    }

    /**
     * 本年数据 根据月份分组
     */
    protected function data_year($model_name, $map, $serach_field, $field = true, $group = "", $order = "")
    {
        $this_year_start = strtotime(date("Y-m-d H:i:s", mktime(0, 0, 0, 1, 1, date("Y"))));
        $this_year_end = strtotime(date("Y-m-d H:i:s", mktime(23, 59, 59, 12, 31, date("Y"))));
        $map[$serach_field] = array(
            "BETWEEN",
            array(
                $this_year_start,
                $this_year_end
            )
        );
        $model = D($model_name);
        $data = $model->field($field)
            ->where($map)
            ->group($group)
            ->order($order)
            ->select();
        $data=$this->month_amount($data,$model_name,12);
        return $data;
    }

    protected function user_data_year($model_name, $map, $serach_field, $field = true, $group = "", $order = "", $where = "")
    {
        $this_year_start = strtotime(date("Y-m-d H:i:s", mktime(0, 0, 0, 1, 1, date("Y"))));
        $this_year_end = strtotime(date("Y-m-d H:i:s", mktime(23, 59, 59, 12, 31, date("Y"))));
        $map[$serach_field] = array(
            "BETWEEN",
            array(
                $this_year_start,
                $this_year_end
            )
        );
        $model = D($model_name);
        $data = $model->field($field)
            ->where($map)
            ->group($group)
            ->order($order)
            ->select();
        $data=$this->month_amount($data,$model_name,12);
        
        return $data;
    }
    public function month_amount($param,$model_name,$num=12){
        $num=$num+1;
        $field=$model_name=='spend'?'amount':'counts';
        for ($i=1; $i <$num ; $i++) { 
            foreach ($param as $key => $value) {
                if($i==$value['month']){
                    $data[$i][]=$value[$field];
                }else{
                    $data[$i][]='';
                }
            }  
            $data[$i]=array($field=>array_sum($data[$i]));
        }
        return $data;
    }

    //流失率分析
    public function loss_pic($para){
        if(isset($para['time_start'])&&isset($para['time_end'])&&$para['time_start']!==null&&$para['time_end']!==null){
            $dd=prDates($para['time_start'],$para['time_end']);
            $day=$dd;
            $this->assign('tt',array_chunk($dd,1));
        }else{
            $defTimeE=date("Y-m-d",time());
            $defTimeS=date("Y-m-d",time()-24*60*60*6);
            $day=every_day(7);
            $dd=prDates($defTimeS,$defTimeE);
        }
        if(isset($para['game_id'])){
            $map['r.game_id']=$para['game_id'];
            $map1['r.game_id']=$para['game_id'];
        }
        if(isset($para['channel_id'])&&$para['channel_id']!=""){
            if($para['channel_id']==2){
                $d=7;
            }else{
                $d=3;
            }
        }else{
            $d=3;
        }
        $limitI=count($dd);
        $Record = new UserLoginRecordModel();
        //玩家流失
        for($i=0;$i<$limitI;$i++){
            $start=$this->get_time($dd[$i],$d);
            $end=$start+24*60*60-1;
            $map['login_time']=array('between',array($start,$end));
            if(isset($para['promote_id'])&&$para['promote_id']!=""){
                $map['promote_id']=$para['promote_id'];
            }
            $logins=$Record->getPlayers($map);//3天前用户
            if($logins==null){
                $loss=null;
            }else{
                $loss=null;
                foreach ($logins as $key => $value) {
                    $start=date("Y-m-d", $value['login_time']+24*60*60);
                    $start=$this->get_time($start,0);
                    $end=$start+24*60*60*$d;
                    $map1['login_time']=array('between',array($start,$end));
                    $map1['user_id']=$value['user_id'];
                    if(isset($para['promote_id'])&&$para['promote_id']!=""){
                        $map1['promote_id']=$para['promote_id'];
                    }
                    $result1=$Record->findPlayer($map1);//三天内有登录过
                    if($result1==null){
                        $loss[]=$logins[$key];
                    }
                }
            }
            if($loss!=null){
                $loser[]=$loss;
            }
            $loss_count[]=count($loss);
            $loss_rate[]=count($logins)?(count($loss)/count($logins)*100?sprintf("%.2f",count($loss)/count($logins)*100):0):0;
        }


        //流失用户id
        foreach ($loser as $key => $value) {
            foreach ($value as $k => $v) {
                $losers[]=$v['user_id'];
            }
        }
        //流失用户消费金额分析
        $data2=$this->loss_pic2($losers);
        //流失次数分析
        $data3=$this->loss_pic3($losers);
        $result['day']=$day;
        $result['loss_count']=$loss_count;
        $result['loss_rate']=$loss_rate;
        $result['loss_money']=$data2;
        $result['loss_times']=$data3;
        return $result;

    }
    /**
     * 流失用户消费金额分析，包括不同等级的人数和所占比例
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    function loss_pic2($data){
        $Spend=new SpendModel();
        foreach ($data as $key => $value) {
            $list[$key]['user_id']=$value;
            $list[$key]['amount']=$Spend->totalSpend($list[$key]);
        }
        foreach ($list as $k => $v) {
            if($v['amount']<2){
                $two[]=$v;
            }elseif($v['amount']<10){
                $ten[]=$v;
            }elseif($v['amount']<20){
                $twenty[]=$v;
            }elseif($v['amount']<40){
                $forty[]=$v;
            }elseif($v['amount']<100){
                $hundred[]=$v;
            }elseif($v['amount']<200){
                $thundred[]=$v;
            }elseif($v['amount']<600){
                $shundred[]=$v;
            }elseif($v['amount']<1000){
                $thousand[]=$v;
            }elseif($v['amount']<2000){
                $tthousand[]=$v;
            }else{
                $thousands[]=$v;
            }
        }
        $result=[count($thousands),count($tthousand),count($thousand),count($shundred),count($thundred),count($hundred),count($forty),count($twenty),count($ten),count($two)];
        return $result;
    }
    /**
     * 流失用户消费次数分析，包括不同次数所占人数和比例
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function loss_pic3($data){
        $Spend=new SpendModel();
        foreach ($data as $key => $value) {
            $list[$key]['user_id']=$value;
            $list[$key]['count']=$Spend->totalSpendTimes($list[$key]);
        }
        foreach ($list as $k => $v) {
            if($v['count']==0){
                $zero[]=$v;
            }elseif($v['count']==1){
                $one[]=$v;
            }elseif($v['count']==2){
                $two[]=$v;
            }elseif($v['count']==3){
                $three[]=$v;
            }elseif($v['count']==4){
                $four[]=$v;
            }elseif($v['count']==5){
                $five[]=$v;
            }elseif($v['count']<11){
                $ten[]=$v;
            }elseif($v['count']<21){
                $twenty[]=$v;
            }elseif($v['count']<31){
                $thirty[]=$v;
            }elseif($v['count']<41){
                $forty[]=$v;
            }elseif($v['count']<51){
                $fifty[]=$v;
            }else{
                $fiftys[]=$v;
            }
        }
        $result=[count($fiftys),count($fifty),count($forty),count($thirty),count($twenty),count($ten),count($five),count($four),count($three),count($two),count($one),count($zero)];
        return $result;
    }
    /**
     * [get_time 通过日期获得时间戳]
     * @param  [type] $date [description]
     * @return [type]       [int]
     */
    private function get_time($date,$d){
        $date= explode("-",$date);
        $year=$date[0];
        $month=$date[1];
        $day=$date[2];
        $start=mktime(0,0,0,$month,$day,$year)-$d*24*60*60;
        return $start;
    }

    public function userarpu($p=0,$allnum=0)
    {
        $request=$_REQUEST;
//        if($request['isbd']==1){
//            $isbdpw['pay_way'] = array('neq',-1);
//        }else{
//            unset($isbdpw['pay_way']);
//        }
        if( empty($request['game_id'])){
            $isbdpw['pay_way'] = array('gt',0);
        }
        $promote_id = I('promote_id');
        if(strtolower(MODULE_NAME) == 'home'){
            if(!$promote_id||$promote_id==PID){
                $promote_id = PID;
            }else{
                $parent = get_fu_id($promote_id);
                if($parent!=PID){
                    $this->error('该渠道不属于您');
                }
            }
        }
        $request1 = $request;
        $this->assign('nobdurl',U('userarpu',$request1,false));
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据arraypage
        $arraypage = $page ? $page : 1; //默认显示第一页数据
        $row  = intval(C('LIST_ROWS')) ? :10;
        $_REQUEST['end'] = $end = empty(I('end')) ? time_format(time(),'Y-m-d') : I('end');
        $_REQUEST['start'] = $start = empty(I('start'))?time_format(strtotime($end)-24*60*60*9,'Y-m-d') : I('start');
        $game_id = I('game_id');
        $map_list = array();
        if(I('game_id') !=0){
            $this->assign('game_name',get_game_name(I('game_id')));
            $this->assign('segame_id',I('game_id'));
        }
        if($promote_id !=0){
            $this->assign('promote_name',get_promote_account($promote_id));
        }
        if(!empty(I('game_id'))) $map_list['game_id'] = I('game_id');
        if(!empty($promote_id)) $map_list['promote_id'] = $promote_id;
        if(!empty($start)) {
            if (is_file(dirname(__FILE__).'/access_data_userarpu.txt')&&I('get.p')>0&&strtolower(MODULE_NAME) != 'home') {
                $filetxt = file_get_contents(dirname(__FILE__).'/access_data_userarpu.txt');
                $res = json_decode($filetxt,true);
            }else{
                //新增玩家
                $data = $this->count_register($start, $end, $game_id, $promote_id);
                //充值 只计算游戏内
                $spenddata = $this->sum_game_pay($start, $end, $game_id, $promote_id,$isbdpw);
                foreach ($data as $key => $value) {
                    $time = $value['time'];
                    //活跃玩家
                    $data[$key]['act_user'] = $this->count_act_user($time,$game_id,$promote_id);
                    //1日留存
                    $map = $map_list;
                    if($map_list['promote_id']!=''){
                        $map['tab_user.promote_id']=$map_list['promote_id'];
                    }
                    unset($map['promote_id']);
                    $map["FROM_UNIXTIME(register_time,'%Y-%m-%d')"] = $time;
                    $login_time = date('Y-m-d', strtotime("+1 day",strtotime($time)));
                    $num = M('user','tab_')
                        ->field('count(DISTINCT tab_user.id) as num')
                        ->join("right join tab_user_login_record as ur on ur.user_id = tab_user.id and FROM_UNIXTIME(ur.login_time,'%Y-%m-%d') = '{$login_time}'")
                        ->where($map)
                        ->group('user_id')
                        ->select();
                    $count = count($num);
                    $data[$key]['keep_num'] = $data[$key]['register_num']?round($count/$data[$key]['register_num'],4)*100:0;

                    //充值 + 付费玩家
                    foreach ($spenddata as $sk => $sv) {
                        if($value['time'] == $sv['time']){
                            $data[$key]['spend'] = $sv['money']?$sv['money']:'0';//充值
                            //付费玩家数
                            $data[$key]['spend_people'] = $sv['people']?$sv['people']:'0';//付费玩家
                        }
                    }
                    //新付费玩家
                    $map = $map_list;
                    $map['pay_status'] = 1;
                    $sql = M('spend','tab_')->field("user_id,min(pay_time) as time")->group('user_id')->where($isbdpw)->where($map)->select(false);
                    $sql = "select IFNULL(count(user_id),0) as num from ({$sql}) as t WHERE  FROM_UNIXTIME(t.time,'%Y-%m-%d') = '{$time}'";
                    $query1 = M()->query($sql);
                    $sql = M('deposit','tab_')->field("user_id,min(create_time) as time")->group('user_id')->where($isbdpw)->where($map)->select(false);
                    $sql = "select IFNULL(count(user_id),0) as num from ({$sql}) as t WHERE  FROM_UNIXTIME(t.time,'%Y-%m-%d') = '{$time}'";
                    $query2 = M()->query($sql);
                    $sql = M('bindRecharge','tab_')->field("user_id,min(create_time) as time")->group('user_id')->where($isbdpw)->where($map)->select(false);
                    $sql = "select IFNULL(count(user_id),0) as num from ({$sql}) as t WHERE  FROM_UNIXTIME(t.time,'%Y-%m-%d') = '{$time}'";
                    $query3 = M()->query($sql);
                    $data[$key]['new_pop'] = $query1[0]['num']+$query2[0]['num']+$query3[0]['num'];
                    //付费率
                    $data[$key]['spend_rate'] = $data[$key]['act_user']?round($data[$key]['spend_people']/$data[$key]['act_user'],4)*100:'0';
                    //ARPU
                    $data[$key]['ARPU'] = $data[$key]['act_user']?round($data[$key]['spend']/$data[$key]['act_user'],2):'0';
                    //ARPPU
                    $data[$key]['ARPPU'] = $data[$key]['spend_people']?round($data[$key]['spend']/$data[$key]['spend_people'],2):'0';

                    //累计付费玩家
                    $map = $map_list;
                    $map1 = $map_list;
                    $map['pay_status'] = 1;
                    $map1['pay_status'] = 1;
                    $map['pay_time'] = array('elt',strtotime($time)+24*3600-1);
                    $map1['create_time'] = array('elt',strtotime($time)+24*3600-1);
                    $pop_num1 = M('spend','tab_')->field('count(distinct user_id) as num')->where($isbdpw)->where($map)->find();
                    $pop_num2 = M('deposit','tab_')->field('count(distinct user_id) as num')->where($isbdpw)->where($map1)->find();
                    $pop_num3 = M('bindRecharge','tab_')->field('count(distinct user_id) as num')->where($isbdpw)->where($map1)->find();
                    $data[$key]['pop_num'] = $pop_num1['num']+$pop_num2['num']+$pop_num3['num'];
                    $time_map['time'] = array('between',array($start,$end));
                    $count = M('date_list')->where($time_map)->count();
                    $res['list_data'] = $data;
                    $res['count'] = $count;
                    file_put_contents(dirname(__FILE__).'/access_data_userarpu.txt',json_encode($res));
                }
            }
            $data = [];
            $data = $res['list_data'];
            $count = $res['count'];
            $this->assign('count',$count);
            $think = A('Think','Controller');
            if($allnum){
                $this->assign('list_data', $data);
            }else{
                $res = $think->array_order_page($count,$arraypage,$data,$row);
                $this->assign('list_data', $res);
            }
        }else{
            unset($_REQUEST['data_order']);
            if(count($_REQUEST)!=0){
                $this->error('时间选择错误，请重新选择！');
            }
        }
        $this->meta_title = 'ARPU统计';
        $this->display();
    }
    /**
     * 留存统计
     * @param int $p
     * 06.12.3
     * xmy
     */
    public function userretention($p = 0,$allnum=0)
    {
        if(strtolower(MODULE_NAME) == 'home'){
            $promote_id = I('promote_id')?I('promote_id'):'0';
            if($promote_id=="0"){
                $ids = get_child_ids(PID);
                $ids = array_column($ids,'id');
                $promote_id = $ids;
            }
            
        }else{
            $promote_id = I('promote_id');
        }
        $request=$_REQUEST;
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $arraypage=$page;
        $row = C('LIST_ROWS') ? C('LIST_ROWS') : 10;
        $end = empty(I('end')) ? time_format(time(),'Y-m-d') : I('end');
        $start = empty(I('start'))?time_format(strtotime($end)-24*60*60*9,'Y-m-d') : I('start');
        if(!empty($start)){
            $game_id = I('game_id');
            $map_list = array();
            if(I('game_id') !=0){
                $map_list['game_id'] = $game_id;
                $map_list['fgame_id'] = $game_id;
                $this->assign('game_name',get_game_name(I('game_id')));
                $this->assign('segame_id',I('game_id'));
            }
            if($promote_id !=0){
                $this->assign('promote_name',get_promote_account($promote_id));
            }
            //统计每日注册数
            $data = $this->count_register($start,$end,$game_id,$promote_id,$page);
            $day = array(1,2,3,4,5,6,7,15,30,60);
            foreach ($data as $k=>$v) {
                //当日注册人帐号
                $map = $map_list;
                $time = $v['time'];
                $map["FROM_UNIXTIME(register_time,'%Y-%m-%d')"] = $time;
                //每日留存
                foreach ($day as $key => $value) {
                    $map = $map_list;
                    if($promote_id!=0){
                        $map['tab_user.promote_id'] = ['in',$promote_id];
                    }else{
                        unset($map['tab_user.promote_id']);
                    }
//                    if(I('game_id') !=0 && !empty($v['user_id'])){
//                        $map_list['user_id'] = array('in',$v['user_id']);
//                    }
                    $map["FROM_UNIXTIME(register_time,'%Y-%m-%d')"] = $time;
                    $login_time = date('Y-m-d', strtotime("+{$value} day",strtotime($time)));
                    $num = M('user','tab_')
                        ->field('count(DISTINCT tab_user.id) as num')
                        ->join("right join tab_user_login_record as ur on ur.user_id = tab_user.id and FROM_UNIXTIME(ur.login_time,'%Y-%m-%d') = '{$login_time}'")
                        ->where($map)
                        ->find();
                    $data[$k][$value] = $v['register_num']?round(($num['num']/$v['register_num'])*100,2) : 0;
                }
            }
            //分页
            $time_map['time'] = array('between',array($start,$end));
            $count = M('date_list')->where($time_map)->count();
            $think = A('Think','Controller');
			$this->assign('count',$count);
            if($allnum){
                $this->assign('list_data', $data);
            }else{
                $res = $think->array_order_page($count,$arraypage,$data,$row);
                $this->assign('list_data', $res);
            }
        }else{
            unset($_REQUEST['data_order']);
            if(count($_REQUEST)!=0){
                $this->error('时间选择错误，请重新选择！');
            }
        }
        $this->meta_title = '留存统计';
        $this->display();
    }



    /**
     * 统计注册数
     * @param $start    开始时间
     * @param $end      结束时间
     * @param string $game_name     游戏名称
     * @param string $promote_id    渠道ID
     * @param int $page
     */
    protected function count_register($start,$end,$game_id="",$promote_id="",$page=0,$row=10){
        $map['time'] = array('between',array($start,$end));
        $join = "left join tab_user u on FROM_UNIXTIME(u.register_time,'%Y-%m-%d') = time";

        if($game_id != ''){
            $join .= " AND u.fgame_id = {$game_id}";
        }
        if($promote_id != ''){
            if(is_array($promote_id)){
                $join .= " AND u.promote_id in (".implode(',',$promote_id).")";
            }else{
                $join .= " AND u.promote_id ='{$promote_id}'";
            }
        }
        //统计每日注册数
        $data = M('date_list')
            ->field("time,COUNT(u.id) as register_num")
            ->join($join)
            ->where($map)
            ->group('time')
            ->select();
        if($game_id){
            $data1 = M('date_list')
                ->field("u.id")
                ->join($join)
                ->where('u.id')
                ->group('time')
                ->select();
            $user_array = array_column($data1,'id');
            foreach ($data as $key=>$v){
                $data[$key]['user_id'] = $user_array;
            }
        }
        return $data;
    }
    /**
     * 获取活跃用户数
     * @param $time
     */
    public function count_act_user($time,$game_id="",$promote_id=""){

        $map["FROM_UNIXTIME(tab_user_login_record.login_time,'%Y-%m-%d')"] = $time;
        // $map2["FROM_UNIXTIME(tab_user.register_time,'%Y-%m-%d')"]=$time;
        $map2["FROM_UNIXTIME(tab_user.register_time,'%Y-%m-%d')"]=['lt',$time];
        $user = M('user','tab_');
        $userlogin = M('user_login_record','tab_');
        if(!empty($game_id)){
            $map["tab_user_login_record.game_id"] = $game_id;
            //$map2["tab_user.fgame_id"]=$game_id;
        }

        if(!empty($promote_id)){
            $map["tab_user_login_record.promote_id"] = $promote_id;
            $map2["tab_user.promote_id"]=$promote_id;
        }

        $turel=$user
            ->field('id as user_id')
            ->where($map2)
            ->select(false);
        $tloginsql=$userlogin
            ->field('user_id')
            ->where($map)
            ->union($turel,true)
            ->group('user_id')
            ->select(false);
        $tlogin_q = "select user_id from (".$tloginsql.' ) as tt group by user_id having count(user_id)>1';
        $tlogin = M()->query($tlogin_q);
        $data = count($tlogin);
        return $data;
    }
    protected function sum_game_pay($start, $end, $game_id='', $promote_id='',$isbdpw=[]){
        if(!empty($game_id)) {
            $map['game_id'] = $game_id;
            $map1['game_id'] = $game_id;
        }
        if(!empty($promote_id)) {
            $map['promote_id'] = $promote_id;
            $map1['promote_id'] = $promote_id;
        }
        $map['pay_status'] = 1;
        $map["FROM_UNIXTIME(pay_time,'%Y-%m-%d')"] = ['between',[$start,$end]];
        $map1["FROM_UNIXTIME(create_time,'%Y-%m-%d')"] = ['between',[$start,$end]];
        $dmap['time'] = ['between',[$start,$end]];
        if($game_id){
            $spend = M('spend','tab_')
                ->field("sum(pay_amount) as money,count(distinct user_id) as people,FROM_UNIXTIME(pay_time,'%Y-%m-%d') as ptime")
                ->where($isbdpw)
                ->where($map)
                ->group('ptime')
                ->select(false);
        }else{
            $union = M('deposit','tab_')
                ->field('sum(pay_amount) as money,count(distinct user_id) as people,FROM_UNIXTIME(create_time,"%Y-%m-%d") as ptime')
                ->where(['pay_status'=>1])
                ->where($map1)
                ->group('ptime')
                ->select(false);
            $union1 = M('bindRecharge','tab_')
                ->field('sum(amount) as money,count(distinct user_id) as people,FROM_UNIXTIME(create_time,"%Y-%m-%d") as ptime')
                ->where(['pay_status'=>1])
                ->where($map1)
                ->group('ptime')
                ->select(false);
            $spend = M('spend','tab_')
                ->field("sum(pay_amount) as money,count(distinct user_id) as people,FROM_UNIXTIME(pay_time,'%Y-%m-%d') as ptime")
                ->union($union,true)
                ->union($union1,true)
                ->where($isbdpw)
                ->where($map)
                ->group('ptime')
                ->select(false);
            $model = M();
            $spend = $model->table('('.$spend.') as b ')->field('sum(money) as money,sum(people) as people,ptime')
                ->group('ptime')
                ->select(false);
        }
        $data = M('date_list')
                ->join('('.$spend.') as pay on pay.ptime = time','left')
                ->where($dmap)
                ->select();
        return $data;
    }
}
