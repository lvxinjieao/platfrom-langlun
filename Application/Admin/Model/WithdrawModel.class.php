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
 * 分类模型
 */
class WithdrawModel extends Model{

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



    /*
	 * 开发者提现未处理列表
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
    public function checkDeveloperWithdraw() {

        $list = $this->field('tab_withdraw.id,tab_withdraw.settlement_number,tab_developers.account')

            ->join('tab_developers on (tab_withdraw.developers = tab_developers.id) ','left')

            ->where(array('tab_withdraw.status'=>0,'tab_withdraw.developers'=>array('gt',0)))->select();

        if ($list[0]) {

            $list = D('check')->dealWithCheckList(200,$list);

            foreach ($list as $k => $v) {
                $data[$k]['info'] = '开发者：'.$v['account'].'申请提现,提现状态：未审核';
                $data[$k]['type'] = 200;
                $data[$k]['url'] = U('Withdraw/cp_withdraw',array('settlement_number'=>$v['settlement_number']));
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
	 * 推广员提现未处理列表
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
    public function checkPromoteWithdraw() {

        $list = $this->field('tab_withdraw.id,tab_withdraw.settlement_number,tab_promote.account')

            ->join('tab_promote on (tab_withdraw.promote_id = tab_promote.id) ','left')

            ->where(array('tab_withdraw.status'=>0,'tab_withdraw.promote_id'=>array('gt',0)))->select();

        if ($list[0]) {

            $list = D('check')->dealWithCheckList(201,$list);

            foreach ($list as $k => $v) {
                $data[$k]['info'] = '推广员：'.$v['account'].'申请提现,提现状态：未审核';
                $data[$k]['type'] = 201;
                $data[$k]['url'] = U('Query/withdraw',array('settlement_number'=>$v['settlement_number']));
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
