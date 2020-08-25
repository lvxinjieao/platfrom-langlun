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
use Admin\Logic\SetLogic;

/**
 * 文档基础模型
 */
class SmallGameModel extends Model{

    

    /* 自动验证规则 */
    protected $_validate = array(
        array('game_name',  'require', '小程序名称不能为空',         self::MUST_VALIDATE,  'regex',  self::MODEL_BOTH),
        array('game_name',  '1,30',    '小程序名称不能超过30个字符', self::VALUE_VALIDATE, 'length', self::MODEL_BOTH),
        array('icon',  'require', '小程序头像不能为空',         self::MUST_VALIDATE,  'regex',  self::MODEL_BOTH),
        //array('thumbnail',  'require', '小程序卡片图不能为空',         self::MUST_VALIDATE,  'regex',  self::MODEL_BOTH),
        //array('appid',  'require', 'appid不能为空',         self::MUST_VALIDATE,  'regex',  self::MODEL_BOTH),
        // array('page_path',  'require', '小程序路径不能为空',         self::MUST_VALIDATE,  'regex',  self::MODEL_BOTH),
    );

    /* 自动完成规则 */
    protected $_auto = array(
        array('create_time',       'getCreateTime',       self::MODEL_INSERT, 'callback'),
        array('theme',       'checktheme',       self::MODEL_BOTH, 'callback'),
    );

    //protected $this->$tablePrefix = 'tab_'; 
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

    /**
     * 首字母转化大写
     * @return mixed
     * @author 幽灵[syt]
     */
    protected function checktheme(){
        $theme    =   I('post.theme');
        return $theme?strtoupper($theme):'';
    }
    /*
    *获取操作人昵称
    */
    protected function get_op_nickname(){
       return session("user_auth.username");
    }
    public function chgculumn(){
        if(I('game_id')==''||I('column')==''){
            return -1;
        }
        $game = $this->find(I('game_id'));
        if(empty($game)){
            return 0;
        }
        $column = I('column');
        if($column=='play_count'){
            $data['set_play_count_time'] = time();
        }
        $data[$column] = I('newval');
        $map['id'] = I('game_id');
        $res = $this->where($map)->save($data);
        if($res!==false){
            return 1;
        }else{
            return 0;
        }
    }
}