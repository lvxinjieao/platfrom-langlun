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
class PromoteWelfareModel extends Model{

    

    /* 自动验证规则 */
    protected $_validate = array(
        array('game_id', 'check_game', '该渠道游戏已被添加', self::MUST_VALIDATE, 'callback'),
        array('promote_discount', [0,10], '渠道折扣区间为1-10', self::EXISTS_VALIDATE, 'between', self::MODEL_BOTH),
        array('first_discount', [0,10], '首冲折扣区间为1-10', self::EXISTS_VALIDATE, 'between', self::MODEL_BOTH),
        array('continue_discount', [0,10], '续冲折扣区间为1-10', self::EXISTS_VALIDATE, 'between', self::MODEL_BOTH),
    );

    /* 自动完成规则 */
    protected $_auto = array(
        array('create_time', 'getCreateTime', self::MODEL_INSERT,'callback'),
        array('op_id',UID,self::MODEL_INSERT),
        array('promote_status'  , 1,self::MODEL_INSERT),
        array('recharge_status' , 1,self::MODEL_INSERT),
        array('cont_status'     , 1,self::MODEL_INSERT),
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

    public function set_game_discount($set_field=null){
        $data = array('game_discount'=>$set_field['discount']);
        $map['status']  = $set_field['status'];
        $map['game_id'] = $set_field['game_id'];
        $this->where($map)->setField($data);
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

    /**
     * 检查渠道游戏是否唯一
     * @return bool
     */
    public function check_game($game_id){
        $map['game_id'] = $game_id;
        $map['promote_id'] = "-1";
        $data = $this->where($map)->find();
        if(!empty($data)){
            return false;
        }else{
            $map['promote_id'] = I('promote_id');
            $find_data=$this->where($map)->find();
            if(!empty($find_data)){
                return false;
            }
            return true;
        }
    }

    /**
     * 检查返利对象是否合法
     * @return bool
     */
    public function check_object(){
        $promote_id = I('promote_id');
        $game_id = I('game_id');
        $map = "game_id = {$game_id} ";
        switch ($promote_id){
            case 0 ://官方
                $map .= " and (promote_id = -1)";
                $error = "该游戏已设置全站对象,无需设置官方渠道";
                break;
            case -1://全站
                $map .= " and (promote_id = -2 or promote_id = 0)";
                $error = "已设置其他渠道,无需设置全站玩家";
                break;
            case -2://推广
                $map .= " and (promote_id = -1 )";
                $error = "已设置全站玩家,无需设置推广渠道";
                break;
        }
        $data = $this->where($map)->find();
        //var_dump($rebate);exit;
        if(!empty($data)){
            switch ($data['promote_id']) {
                case 0://官方
                    switch ($promote_id) {
                        case -1:
                            $error = "请先删除该游戏的官方渠道，在设置全站渠道";
                            break;
                        case 0:
                            $error = "该游戏已设置官方渠道对象";
                            break;
                    }
                    break;
                case -1://全站
                    switch ($promote_id) {
                        case -1:
                            $error = "该游戏已设置全站玩家对象";
                            break;
                        case 0:
                            $error = "已设置全站玩家，无需设置官方渠道";
                            break;
                        case -2:
                            $error = "已设置全站玩家，无需设置推广渠道";
                            break;
                    }
                    break;
                case -2:
                    switch ($promote_id) {
                        case -1:
                            $error = "请先删除该游戏的推广渠道，在设置全站玩家";
                            break;
                        case -2:
                            $error = "该游戏已设置推广渠道对象";
                            break;
                    }
                    break;
            }
            $this->error = $error;
            return false;
        }else{
            return true;
        }
    }

}