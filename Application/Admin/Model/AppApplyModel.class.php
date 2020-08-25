<?php
namespace Admin\Model;
use Think\Model;

/**
 * 文档基础模型
 */
class AppApplyModel extends Model{

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
	 * 未审核联运APP分包列表
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
	public function checkPackageAppApply() {
		
		$list = $this->field('tab_app_apply.id,tab_app_apply.app_id,tab_app_apply.promote_id,tab_promote.account,tab_app_apply.app_version,tab_app.name')
				
				->join('tab_app on (tab_app.id = tab_app_apply.app_id)','left')
				
				->join('tab_promote on (tab_promote.id = tab_app_apply.promote_id)','left')
			
				->where(array('tab_app_apply.status'=>0))->select();
		
		if ($list[0]) {
			
			$list = D('check')->dealWithCheckList(304,$list);
			
			if (empty($list[0])) {return '';}
			
			foreach ($list as $k => $v) {
				$name = str_replace(array('-Android','-iOS'),array('(安卓版)','(苹果版)'),$v['name']);
				$data[$k]['info'] = '推广员账号：'.$v['account'].'['.$name.']分包申请,审核状态：未审核';
				$data[$k]['type'] = 304;
				$data[$k]['url'] = U('Apply/app_lists',array('type'=>$v['app_version'],'promote_id'=>$v['promote_id'],'status'=>0));
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
	 * 未打包联运APP列表
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
	public function checkPackAppApply() {
		
		$list = $this->field('tab_app_apply.id,tab_app_apply.app_id,tab_app_apply.promote_id,tab_promote.account,tab_app_apply.app_version,tab_app.name')
				
				->join('tab_app on (tab_app.id = tab_app_apply.app_id)','left')
				
				->join('tab_promote on (tab_promote.id = tab_app_apply.promote_id)','left')
			
				->where(array('tab_app_apply.status'=>1,'tab_app_apply.enable_status'=>0))->select();
		
		if ($list[0]) {
			
			$list = D('check')->dealWithCheckList(305,$list);
			
			if (empty($list[0])) {return '';}
			
			foreach ($list as $k => $v) {
				$name = str_replace(array('-Android','-iOS'),array('(安卓版)','(苹果版)'),$v['name']);
				$data[$k]['info'] = '推广员账号：'.$v['account'].'['.$name.']分包申请,打包状态：未打包';
				$data[$k]['type'] = 305;
				$data[$k]['url'] = U('Apply/app_lists',array('type'=>$v['app_version'],'promote_id'=>$v['promote_id'],'enable_status'=>0));
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