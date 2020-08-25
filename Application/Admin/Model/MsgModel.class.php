<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/9/28
 * Time: 19:16
 */
namespace Admin\Model;

use Think\Model;

class MsgModel extends Model{

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
     * 发送站内信
     * @param array $user_id    用户id
     * @param string $content   内容
     * @param int $type         类型 1：后台
     * @param int $status       状态 -1：删除 1：已读 2：未读
     * @return bool|string
     */
    public function sendMsg($user_id=array(),$content="",$type=1,$status=2){
        foreach($user_id as $value){
            $data[] = array(
                'user_id'   =>  $value['id'],
                'content'   =>  $content,
                'type'  =>  $type,
                'status'    =>  $status,
                'create_time'   =>  time(),
            );
        }
        return $this->addAll($data);
    }
}