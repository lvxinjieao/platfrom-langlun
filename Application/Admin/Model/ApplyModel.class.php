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
class ApplyModel extends Model{

    /* 自动验证规则 */
    protected $_validate = array(
    //     array('game_name', 'require', '游戏名称不能为空', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
    //     array('game_name', '1,30', '游戏名称不能超过30个字符', self::VALUE_VALIDATE, 'length', self::MODEL_BOTH),
    //     array('game_appid', 'require', '游戏APPID不能为空', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
        // array('ratio',         '/^(?!0+(?:\.0+)?$)\d+(?:\.\d{1,2})?$/',           '游戏折扣输入不正确',             self::VALUE_VALIDATE,  'regex',  self::MODEL_BOTH),    
            array('ratio',         '/^(\d{1,2}(\.\d{1,3})?|100)$/',           '代充折扣错误',             self::VALUE_VALIDATE,  'regex',  self::MODEL_BOTH),
    );

    /* 自动完成规则 */
    protected $_auto = array(
        array('apply_time', 'getCreateTime', self::MODEL_BOTH,'callback'),
        array('password', 'think_ucenter_md5', self::MODEL_BOTH, 'function', UC_AUTH_KEY),
        //array('ratio', 0, self::MODEL_BOTH),
        //array('status', 0, self::MODEL_BOTH),
        //array('enable_status', 1, self::MODEL_BOTH),
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
     * 设置分成比例
     */
    public function setRatio($data){
        $map['game_id'] = $data["game_id"];
        $map['promote_id'] = $data["promote_id"];
        $result = $this->where($map)->setField($data['field'],$data['value']);
        if($result !== false){
            return true;
        }else{
            return false;
        }
    }


    /*
	 * 未审核游戏分包列表
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
    public function checkPackageApply() {

        $list = $this->field('tab_apply.id,tab_apply.game_id,tab_apply.promote_id,tab_promote.account,tab_game.game_name')

            ->join('tab_game on (tab_game.id = tab_apply.game_id)','left')

            ->join('tab_promote on (tab_promote.id = tab_apply.promote_id)','left')

            ->where(array('tab_apply.status'=>0))->select();

        if ($list[0]) {

            $list = D('check')->dealWithCheckList(302,$list);

            foreach ($list as $k => $v) {
                $data[$k]['info'] = '推广员账号：'.$v['account'].'['.$v['game_name'].']推广链接,审核状态：未审核';
                $data[$k]['type'] = 302;
                $data[$k]['url'] = U('Apply/lists',array('promote_id'=>$v['promote_id'],'status'=>0));
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
	 * 未打包游戏分包列表
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
    public function checkPackApply() {

        $list = $this->field('tab_apply.id,tab_apply.game_id,tab_apply.promote_id,tab_promote.account,tab_game.game_name')

            ->join('tab_game on (tab_game.id = tab_apply.game_id)','left')

            ->join('tab_promote on (tab_promote.id = tab_apply.promote_id)','left')

            ->where(array('tab_apply.status'=>1,'tab_apply.enable_status'=>0))->select();

        if ($list[0]) {

            $list = D('check')->dealWithCheckList(303,$list);

            foreach ($list as $k => $v) {
                $data[$k]['info'] = '推广员账号：'.$v['account'].'['.$v['game_name'].']分包申请,打包状态：未打包';
                $data[$k]['type'] = 303;
                $data[$k]['url'] = U('Apply/and_lists',array('game_id'=>$v['game_id'],'promote_id'=>$v['promote_id'],'enable_status'=>0));
                $data[$k]['create_time'] = time();
                $data[$k]['status']=0;
                $data[$k]['position'] = $v['id'];
            }
            return $data;
        }else {
            return '';
        }

    }

    public function joinList($fields=true,$join='',$map=[],$order=''){
        $page = intval($_GET['p']);
        $page = $page ? $page : 1; //默认显示第一页数据
        $row    = C('LIST_ROWS')?C('LIST_ROWS'):10;
        $listData = [];
        $data = $this
                ->field($fields)
                ->join($join)
                ->where($map)
                ->order($order)
                ->page($page, $row)
                ->select();
        $sql = $this->getLastSql();
        /* 查询记录数 */
        $count = $this->join($join)->where($map)->count();
        $listData['data'] = $data;
        //分页
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $listData['page'] = $page->show();
        }
        return $listData;
    }
    /**
    *查找推广员申请成功的游戏信息
    *@param int $promote_id 推广员id
    *@param strng $fields 显示字段
    *@return bool|array
    *@author 小纯洁 change yyh 
    */
    public function getPromoteGame($promote_id = 0,$fields=""){
        $data = $this->FIELD($fields)
                     ->JOIN('tab_game on tab_apply.game_id = tab_game.id AND tab_apply.status = 1 AND tab_apply.promote_id = '.$promote_id)  
                     ->SELECT();
        if(empty($data)){
            return false;
        }else{
            return $data;
        }
    }
}