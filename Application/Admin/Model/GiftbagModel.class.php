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
class GiftbagModel extends Model{

    

    /* 自动验证规则 */
    protected $_validate = array(
        array('game_id','require','游戏名称不能为空',self::MUST_VALIDATE,'regex',self::MODEL_BOTH),
        array('giftbag_name', 'require', '礼包名称不能为空', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
        array('giftbag_name', '1,30', '礼包名称不能超过30个字符', self::VALUE_VALIDATE, 'length', self::MODEL_BOTH),
        array('start_time', 'require', '开始时间不能为空', self::MUST_VALIDATE, 'regex', self::MODEL_BOTH),
    );

    /* 自动完成规则 */
    protected $_auto = array(
        array('create_time', 'getCreateTime', self::MODEL_BOTH,'callback'),
        array('area_num', 0, self::MODEL_BOTH),
        array('start_time', 'strtotime', self::MODEL_BOTH, 'function'),
        array('end_time', 'strtotime', self::MODEL_BOTH, 'function'),
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


    
    /**
     * 生成不重复的name标识
     * @author huajie <banhuajie@163.com>
     */
    private function generateName(){
        $str = 'abcdefghijklmnopqrstuvwxyz0123456789';	//源字符串
        $min = 10;
        $max = 39;
        $name = false;
        while (true){
            $length = rand($min, $max);	//生成的标识长度
            $name = substr(str_shuffle(substr($str,0,26)), 0, 1);	//第一个字母
            $name .= substr(str_shuffle($str), 0, $length);
            //检查是否已存在
            $res = $this->getFieldByName($name, 'id');
            if(!$res){
                break;
            }
        }
        return $name;
    }
		
		
	
	/*
	 * 礼包数量为零列表
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
	public function checkGiftbagNumber() {
		
		$list = $this->field('tab_giftbag.id,tab_giftbag.giftbag_name,tab_game.game_name,tab_giftbag.novice_num')
		
				->join('tab_game on (tab_game.id = tab_giftbag.game_id)','inner')
		
//				->where('novice=""')->select();
				->where(['novice_num'=>['lt',10]])->select();

		if ($list[0]) {
			
			$list = D('check')->dealWithCheckList(403,$list);
			
			if (empty($list[0])) {return '';}
			
			foreach ($list as $k => $v) {
				$data[$k]['info'] = '['.$v['game_name'].']'.$v['giftbag_name'].'数量显示:'.$v['novice_num'];
				$data[$k]['type'] = 403;
				$data[$k]['url'] = U('Giftbag/edit',array('id'=>$v['id']));
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