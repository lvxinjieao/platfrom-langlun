<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: huajie <banhuajie@163.com>
// +----------------------------------------------------------------------

namespace Admin\Model;
use Think\Model;

/**
 * 文档基础模型
 */
class AccessLogModel extends Model{

    /* 自动验证规则 */
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
     *
     * @param  array    $map        条件数组
     * @param  string   $fieldname  字段别名
     * @param  string   $group      分组字段名
     * @param  integer  $flag   		时间类别（1：天，2：月，3：周）
     * @return array       详细数据
     * @author 鹿文学
     */
    public function totalAccess($map=array(),$fieldname='count',$group='time',$flag=1,$order='time') {

        switch($flag) {
            case 2:{$dateform = '%Y-%m';};break;
            case 3:{$dateform = '%Y-%u';};break;
            case 4:{$dateform = '%Y';};break;
            case 5:{$dateform = '%Y-%m-%d %H';};break;
            default:$dateform = '%Y-%m-%d';
        }



        $data = $this->field('FROM_UNIXTIME(access_time,"'.$dateform.'") as '.$group.',count(id) as '.$fieldname)

            ->where($map)->order($order)->select();

        $data = $this->field('FROM_UNIXTIME(access_time,"'.$dateform.'") as '.$group.',count(id) as '.$fieldname)->where($map)->group($group)->select();

        return $data;

    }

}