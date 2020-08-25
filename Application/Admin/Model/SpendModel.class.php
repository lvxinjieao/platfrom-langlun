<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: huajie <banhuajie@163.com>
// +----------------------------------------------------------------------

namespace Admin\Model;
use Org\WeiXinSDK\Weixin;
use Think\Model;

/**
 * 文档基础模型
 */
class SpendModel extends Model{

    

    /* 自动验证规则 */
    protected $_validate = array();

    /* 自动完成规则 */
    protected $_auto = array(
        array('pay_time', 'getCreateTime', self::MODEL_INSERT,'callback'),
        array('pay_status',  0, self::MODEL_INSERT),
        array('order_number','',self::MODEL_INSERT),
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
    

    /**
     * 创建时间不写则取当前时间
     * @return int 时间戳
     * @author huajie <banhuajie@163.com>
     */
    protected function getCreateTime(){
        $create_time    =   I('post.create_time');
        return $create_time?strtotime($create_time):NOW_TIME;
    }

    public function amdin_account()
    {
       return session('user_auth.username');
    }

    public function summary($map,$page=1,$row=10){
        $user = M('User','tab_')
                ->field('register_time as end_time,FROM_UNIXTIME(register_time,"%Y-%m-%d") as end_date')
                ->where(['register_time'=>['gt',0]])
                ->order('register_time asc')
                ->find();
        $game = M('Game','tab_')
                ->field('create_time as end_time,FROM_UNIXTIME(create_time,"%Y-%m-%d")  as end_date')
                ->where(['create_time'=>['gt',0]])
                ->order('create_time asc')
                ->find();
        $promote = M('Promote','tab_')
                ->field('create_time as end_time,FROM_UNIXTIME(create_time,"%Y-%m-%d") as end_date')
                ->where(['create_time'=>['gt',0]])
                ->order('create_time asc')
                ->find();
        $min=min($user['end_time'],$game['end_time'],$promote['end_time']);
        $mindate = date('Y-m-d',$min);
        $today = date('Y-m-d',time());
        $dates = M('dateList')
                ->where(['time'=>['between',[$mindate,$today]]])
                ->where($map)
                ->order('time desc')
                ->page($page,$row)
                ->select();
        $count = M('dateList')
                ->where(['time'=>['between',[$mindate,$today]]])
                ->where($map)
                ->order('time desc')
                ->count();
        $date = array_column($dates, 'time');
        return ['date'=>$date,'count'=>$count];
    }


















    /**
     * 自动补单
     */
    public static function auto_repair(){
        $game = new GameApi();
        $map['pay_status'] =  1;
        $map['pay_game_status'] = 0;
        $order = M("spend",'tab_')->field('pay_order_number,1 as code')->where($map)->select();//普通消费
        $bind_spend = M("bind_spend",'tab_')->field('pay_order_number,2 as code')->where($map)->select();//绑币消费
        if(!empty($bind_spend)){
            array_push($order,$bind_spend);
        }
        $success_num = $error_num = 0;
        foreach ($order as $key=>$val) {
            $param['out_trade_no'] = $val['pay_order_number'];
            $result = $game->game_pay_notify($param, $val['code']);
            if($val['code'] == 1){
                M('spend','tab_')->where(['pay_order_number'=>$val['pay_order_number']])->setInc('auto_compensation');
            }else{
                M('bind_spend','tab_')->where(['pay_order_number'=>$val['pay_order_number']])->setInc('auto_compensation');
            }
            if($result == "success"){
                $success_num++;
            }else{
                $error_num++;
            }
        }
        $time = time_format0();
        \Think\Log::record("自动补单（{$time}）:成功 {$success_num} 个，失败：{$error_num}个");
    }

    
    /**
     * 退款接口
     * @param $map
     */
    public function Refund($map,$order,$sign)
    {
        if(md5("mcaseqwezdsi".$order)!==$sign){
                return false;
        }
        $RefundRecord = M('RefundRecord', 'tab_')->where($map)->find();
        if (null == $RefundRecord) {
            $find = $this->where($map)->find();
            $order_number = $find['pay_way'] == 1 ? date("YmdHis") : "TK_" . date('Ymd') . date('His') . sp_random_string(4);
            $BatchNo=date("YmdHis");
        } else {
            $order_number = $RefundRecord['order_number'];
            $BatchNo=$RefundRecord['batch_no'];
            $find = $RefundRecord;
        }

        if ($find['pay_way'] == 1) {
            //页面上通过表单选择在线支付类型，支付宝为alipay 财付通为tenpay
            $pay = new \Think\Pay('alipay', C('alipay'));
            $vo = new \Think\Pay\PayVo();
            $detail_data = $find['order_number'] . "^" . $find['pay_amount'] . "^掉单";

            $find['batch_no']=$BatchNo;
            $vo->setOrderNo($find['order_number'])
                ->setService("refund_fastpay_by_platform_pwd")
                ->setSignType("MD5")
                ->setPayMethod("refund")
                ->setTable("RefundRecord")
                ->setBatchNo($BatchNo)
                ->setDetailData($detail_data);
            $this->add_refund_record($find, $find['order_number']);
            $this->where($map)->delete();
            return $pay->buildRequestForm($vo);
        } elseif ($find['pay_way'] == 2) {
            $weixn = new Weixin();
            $res = json_decode($weixn->weixin_Refund_pub($find['pay_order_number'], $order_number, $find['pay_amount'], $find['pay_amount'], C('wei_xin.partner')), true);
            $this->add_refund_record($find, $order_number);
            $this->where($map)->delete();
            if ($res['status'] == 1) {
                return $res['status'];
            } else {
                return $res;
            }

        } elseif ($find['pay_way'] == 4) {
            $config = array("partner" => trim(C("weixin.partner")), "email" => "", "key" => trim(C("weixin.key")));
            $pay = new \Think\Pay('swiftpass', $config);
            $vo = new \Think\Pay\PayVo();
             $vo->setService('unified.trade.refund')
                ->setSignType("MD5")
                ->setPayMethod("refund")
                ->setTable("RefundRecord")
                ->setOrderNo($find['pay_order_number'])
                ->setBatchNo($order_number)
                ->setFee($find['pay_amount']);
            $this->add_refund_record($find, $order_number);
             $this->where($map)->delete();
            $res=$pay->buildRequestForm($vo);
            if ($res['status'] == 0) {
                return $res['status'];
            } else {
                return false;
            }
        } elseif ($find['pay_way'] == 0) {
            $user_map['id'] = $find['user_id'];
            M('user', 'tab_')->where($user_map)->setInc('balance', $find['pay_amount']);
             $this->add_refund_record($find, $order_number);
             $this->where($map)->delete();
            return true;
        }

    }

    /**
     * 添加退款记录
     * @param $data
     * @return mixed
     */
    public function add_refund_record($data, $order_number)
    {
        $RefundRecord = M('RefundRecord', 'tab_');
        unset($data['id']);
        $map['pay_order_number'] = $data['pay_order_number'];
        $find = $RefundRecord->where($map)->find();
        if (null !== $find) {
            if($data['pay_way']==4||$data['pay_way']==2){
                $RefundRecord->where($map)->delete();
                $data['tui_status'] = 2;
                $data['create_time'] = time();
                $data['tui_amount'] = $data['pay_amount'];
                $data['order_number'] = $order_number;
                return $RefundRecord->add($data);
            }else{
                return true;
            }
        } else {
            if ($data['pay_way'] == 0) {
                $data['tui_status'] = 1;
                $data['tui_time'] = time();
                $savv['sub_status']=1;
                $savv['settle_check']=1;
                $this->where($map)->save($savv);
            }elseif($data['pay_way'] == 4||$data['pay_way']==3){
                $data['tui_status'] = 2;
            }

            $data['create_time'] = time();
            $data['tui_amount'] = $data['pay_amount'];
            $data['order_number'] = $order_number;
            return $RefundRecord->add($data);

        }
    }




    /**
     * 微信退款查询接口
     * @param  [type] $orderNo [description]
     * @return [type]          [description]
     */
    public function weixin_refundquery($orderNo){
          $weixn = new Weixin();
          $res = $weixn->weixin_refundquery($orderNo);

           if($res=="SUCCESS"){
            M('RefundRecord', 'tab_')->where(array('pay_order_number'=>$orderNo))->setField('tui_status', 1);
                return json_encode(array('status'=>1,'msg'=>'退款成功'));
            }elseif($res=="FAIL"){
                return json_encode(array('status'=>0,'msg'=>'退款失败'));
            }elseif($res=="PROCESSING"){
                return json_encode(array('status'=>0,'msg'=>'退款处理中'));
            }
    }



    /**
     * 威富通查询退款接口
     * @param  [type] $map [description]
     * @return [type]      [description]
     */
    public function swiftpass_refund($orderNo){
        $config = array("partner" => trim(C("weixin.partner")), "email" => "", "key" => trim(C("weixin.key")));
        $pay = new \Think\Pay('swiftpass', $config);
        $vo = new \Think\Pay\PayVo();
        $vo->setOrderNo($orderNo)
            ->setService('unified.trade.refundquery')
            ->setSignType("MD5")
            ->setPayMethod("find")
            ->setTable("RefundRecord");
        $res=$pay->buildRequestForm($vo);
        if($res['refund_status']=="SUCCESS"){
            M('RefundRecord', 'tab_')->where(array('pay_order_number'=>$orderNo))->setField('tui_status', 1);
            return json_encode(array('status'=>1,'msg'=>'退款成功'));
        }elseif($res['refund_status']=="FAIL"){
            return json_encode(array('status'=>0,'msg'=>'退款失败'));
        }elseif($res['refund_status']=="PROCESSING"){
            return json_encode(array('status'=>0,'msg'=>'退款处理中'));
        }
    }

    /**
     * 累计付费
     * @param string $map
     * @return mixed
     * author: xmy 280564871@qq.com
     */
    public function totalSpend($map=""){
        $map['s.pay_status'] = 1;
        $data = $this->alias("s")->field("sum(pay_amount) as num")
            ->join("right join tab_game g on g.id = s.game_id")
            ->where($map)
            ->find();
        return $data['num']?:0;
    }
    public function totalSpendTimes($map=""){
        $map['s.pay_status'] = 1;
        $data = $this->alias("s")->field("count(s.id) as count")
            ->join("left join tab_game g on g.id = s.game_id")
            ->where($map)
            ->find();
        return $data['count']?:0;
    }


    /*
	 * 付费用户总数
	 * @param  array    $map      条件数组
	 * @author 鹿文学
	 */
    public function player($map=array()) {

        $map['pay_status']=1;

        $data = $this->field('count( DISTINCT user_id) as count')

            ->where($map)->select();

        return $data[0]?$data[0]['count']:0;

    }


    /**
     * 统计总流水
     * @param  array    $where      条件数组
     * @return integer  数量
     * @author 鹿文学
     */
    public function totalAmount($map=array()) {

        $map['pay_status'] = 1;

        $sum = $this->where($map)->sum('pay_amount');

        return $sum?$sum:0;

    }

    /*
	 * 付费用户
	 * @param  array    $map        条件数组
	 * @param  string   $fieldname  字段别名
	 * @param  string   $group      分组字段名
	 * @param  integer  $flag   		时间类别（1：天，2：月，3：周）
	 * @return array       详细数据
	 * @author 鹿文学
	 */
    public function totalPlayerByGroup($map=array(),$fieldname='count',$group='time',$flag=1,$order='time') {

        switch($flag) {
            case 2:{$dateform = '%Y-%m';};break;
            case 3:{$dateform = '%Y-%u';};break;
            case 4:{$dateform = '%Y';};break;
            case 5:{$dateform = '%Y-%m-%d %H';};break;
            default:$dateform = '%Y-%m-%d';
        }

        $map['pay_status']=1;
        $map['pay_way']=['gt',0];

        $union = M('deposit','tab_')
            ->field('FROM_UNIXTIME(create_time,"'.$dateform.'") as '.$group.',count( DISTINCT user_id) as '.$fieldname.',group_concat(user_id) as uid')
            ->where(['create_time'=>$map['pay_time'],'pay_status'=>1])
            ->group($group)
            ->select(false);
        $union1 = M('bindRecharge','tab_')
            ->field('FROM_UNIXTIME(create_time,"'.$dateform.'") as '.$group.',count( DISTINCT user_id) as '.$fieldname.',group_concat(user_id) as uid')
            ->where(['create_time'=>$map['pay_time'],'pay_status'=>1])
            ->group($group)
            ->select(false);
        $sql = $this
            ->field('FROM_UNIXTIME(pay_time,"'.$dateform.'") as '.$group.',count( DISTINCT user_id) as '.$fieldname.',group_concat(user_id) as uid')
            ->union($union)
            ->union($union1)
            ->where($map)
            ->group($group)
            ->select(false);
        $data = $this
            ->table('('.$sql.') as a')
            ->field('a.'.$group.','.$fieldname .',group_concat(a.uid) as uid')
            ->group('a.'.$group)
            ->order($order)
            ->select();

        foreach($data as &$v){
            $v['uid'] = array_unique(explode(',',$v['uid']));
            $v[$fieldname] = count($v['uid']);
            unset($v['uid']);
        }

        unset($v);

        return $data;

    }

    /**
     * 分组统计所有流水
     * @param  array    $map        条件数组
     * @param  string   $fieldname  字段别名
     * @param  string   $group      分组字段名
     * @param  integer  $flag   		时间类别（1：天，2：月，3：周）
     * @return array       详细数据
     * @author 鹿文学
     */
    public function allAmountByGroup($map=array(),$fieldname='amount',$group='time',$flag=1,$order='time') {

        switch($flag) {
            case 2:{$dateform = '%Y-%m';};break;
            case 3:{$dateform = '%Y-%u';};break;
            case 4:{$dateform = '%Y';};break;
            case 5:{$dateform = '%Y-%m-%d %H';};break;
            default:$dateform = '%Y-%m-%d';
        }

        $map['pay_status']=1;

        $union = D('deposit')->field('FROM_UNIXTIME(create_time,"'.$dateform.'") as '.$group.',sum(pay_amount) as '.$fieldname)

            ->where(['create_time'=>$map['pay_time'],'pay_status'=>1])

            ->group($group)->select(false);
        $union1 = M('bind_recharge','tab_')->field('FROM_UNIXTIME(create_time,"'.$dateform.'") as '.$group.',sum(amount) as '.$fieldname)

            ->where(['create_time'=>$map['pay_time'],'pay_status'=>1])

            ->group($group)->select(false);

        $sql = $this->field('FROM_UNIXTIME(pay_time,"'.$dateform.'") as '.$group.',sum(pay_amount) as '.$fieldname)

            ->union('('.$union.')')

            ->union('('.$union1.')')
            ->where($map)->group($group)->select(false);

        $data = $this->table('('.$sql.') as a')->field('a.'.$group.',sum(a.'.$fieldname.') as '.$fieldname)->group('a.'.$group)->order($order)->select();
        return $data;
    }


    /**
     * 分组统计流水
     * @param  array    $map        条件数组
     * @param  string   $fieldname  字段别名
     * @param  string   $group      分组字段名
     * @param  integer  $flag   		时间类别（1：天，2：月，3：周）
     * @return array       详细数据
     * @author 鹿文学
     */
    public function totalAmountByGroup($map=array(),$fieldname='amount',$group='time',$flag=1,$order='time') {

        switch($flag) {
            case 2:{$dateform = '%Y-%m';};break;
            case 3:{$dateform = '%Y-%u';};break;
            case 4:{$dateform = '%Y';};break;
            case 5:{$dateform = '%Y-%m-%d %H';};break;
            default:$dateform = '%Y-%m-%d';
        }
        $map['pay_status']=1;

        $union = M('deposit','tab_')
            ->field('FROM_UNIXTIME(create_time,"'.$dateform.'") as '.$group.',sum(pay_amount) as '.$fieldname)
            ->where(['create_time'=>$map['pay_time'],'pay_status'=>1])
            ->group($group)
            ->select(false);
        $union1 = M('bindRecharge','tab_')
            ->field('FROM_UNIXTIME(create_time,"'.$dateform.'") as '.$group.',sum(real_amount) as '.$fieldname)
            ->where(['create_time'=>$map['pay_time'],'pay_status'=>1])
            ->group($group)
            ->select(false);
        $sql1 = $this
            ->field('FROM_UNIXTIME(pay_time,"'.$dateform.'") as '.$group.',sum(pay_amount) as '.$fieldname)
            ->where($map)
            ->union($union,true)
            ->union($union1,true)
            ->group($group)
            ->select(false);
        $sql = 'select time,sum(money) as '.$fieldname.' from ('.$sql1.') as aa group by '.$group.' order by '.$order;
        $data = M()->query($sql);
        return $data;
    }


    /*
	 * 游戏充值未到账列表
	 * @return array  结果集
	 * @author 鹿文学
	 */
    public function checkSpend() {

        $list = $this->field('id,user_id,user_account,game_id,game_name,pay_amount,pay_order_number')->where(array('pay_status'=>0))->select();

        if ($list[0]) {

            $list = D('check')->dealWithCheckList(101,$list);

            foreach ($list as $k => $v) {
                $data[$k]['info'] = '玩家：'.$v['user_account'].',游戏['.$v['game_name'].']充值金额：'.$v['pay_amount'].',订单状态：下单未支付';
                $data[$k]['type'] = 101;
                $data[$k]['url'] = U('Spend/lists',array('pay_order_number'=>$v['pay_order_number']));
                $data[$k]['create_time'] = time();
                $data[$k]['status']=0;
                $data[$k]['position'] = $v['id'];
            }
            return $data;
        }else {
            return '';
        }

    }

    /*
	 * 游戏补单列表
	 * @return array  结果集
	 * @author 鹿文学
	 */
    public function checkSupplement() {

        $list = $this->field('id,user_id,user_account,pay_order_number')->where(array('pay_status'=>1,'pay_game_status'=>0))->limit(5000)->select();

        if ($list[0]) {

            $list = D('check')->dealWithCheckList(102,$list);

            foreach ($list as $k => $v) {
                $data[$k]['info'] = '玩家：'.$v['user_account'].',订单：'.$v['pay_order_number'].',操作：补单失败';
                $data[$k]['type'] = 102;
                $data[$k]['url'] = U('Spend/lists',array('pay_order_number'=>$v['pay_order_number']));
                $data[$k]['create_time'] = time();
                $data[$k]['status']=0;
                $data[$k]['position'] = $v['id'];
            }
            return $data;
        }else {
            return '';
        }

    }

}