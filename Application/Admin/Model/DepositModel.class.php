<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace Admin\Model;
use Think\Model;

/**
 * 用户模型
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */

class DepositModel extends Model {

    protected $_validate = array(
        
    );

    /* 自动完成规则 */
    protected $_auto = array(
        array('create_time',       'getCreateTime',         self::MODEL_INSERT,  'callback'),
        array('pay_status',        0,                       self::MODEL_INSERT),
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


    /*
	 * 平台币充值未到账列表
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
    public function checkPlatformCoin() {

        $list = $this->field('id,user_id,user_account,pay_amount,pay_order_number')->where(array('pay_status'=>0,'pay_way'=>array('egt',0)))->select();

        if ($list[0]) {

            $list = D('check')->dealWithCheckList(103,$list);

            foreach ($list as $k => $v) {
                $data[$k]['info'] = '玩家：'.$v['user_account'].',平台币充值,充值金额：'.$v['pay_amount'].',订单状态：下单未付款';
                $data[$k]['type'] = 103;
                $data[$k]['url'] = U('Deposit/lists',array('pay_order_number'=>$v['pay_order_number']));
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
