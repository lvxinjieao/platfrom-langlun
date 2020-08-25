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

class GameSourceModel extends Model {

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
	 * 未上传游戏原包列表
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
	public function checkGameSource() {
		
		$list = $this->field('id,game_name,file_url')
		
				->where([])->select();
		if ($list[0]) {
			
			$list = D('check')->dealWithCheckList(402,$list);
			
			if (empty($list[0])) {return '';}
			
			foreach ($list as $k => $v) {
				if ($v['file_url']) {
				$file_url = str_replace(array('./','../'),'/',$v['file_url']);
				if (!is_file($file_url))
					$data[$k]['info'] = '['.$v['game_name'].'],原包未上传';
				} else {
					$data[$k]['info'] = '['.$v['game_name'].'],原包未上传';				
				}
				$data[$k]['type'] = 402;
				$data[$k]['url'] = U('GameSource/edit',array('id'=>$v['id']));
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
