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

class ProvideModel extends Model {

    protected $_validate = array(
        
    );

    /* 自动完成规则 */
    protected $_auto = array(
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


    /*
	 * 未到帐绑币列表
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
    public function checkProvideIsGet() {

        $list = $this->field('id,user_account,game_name,op_account,amount,coin_type,pay_order_number')

            ->where(array('status'=>0))->select();

        if ($list[0]) {

            $list = D('check')->dealWithCheckList(406,$list);

            foreach ($list as $k => $v) {
                if($v['coin_type']=='-1'){
                    $data[$k]['info'] = '管理员：'.$v['op_account'].'给玩家('.$v['user_account'].')发放绑币,发放：'.$v['amount'].',实际到帐：0';
                    $data[$k]['type'] = 406;
                    $data[$k]['url'] = U('Provide/lists',['pay_order_number'=>$v['pay_order_number']]);
                }else{
                    $data[$k]['info'] = '管理员：'.$v['op_account'].'给玩家('.$v['user_account'].')发放平台币,发放：'.$v['amount'].',实际到帐：0';
                    $data[$k]['type'] = 405;
                    $data[$k]['url'] = U('Provide/lists',array('group'=>2,'model'=>'Provide','user_account'=>$v['user_account']));
                }
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
