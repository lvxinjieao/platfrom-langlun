<?php
namespace Admin\Model;

use Think\Model;

/**
 * 系统检查文档基础模型
 * @author 鹿文学
 */
class CheckModel extends Model{
	
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
	 * 处理检查
	 * @param  array 	$type  异常类型
	 * @param  array  $deal  处理数组
	 * @param  string $field  字段(编号字段名)
	 * @return array 
	 * @author 鹿文学
	 */
	public function dealWithCheckList($type,$deal,$field='id') {
		
		$deal_id = array_column($deal,$field);
		
		$data = $this->field('position')->where(['position'=>array('in',$deal_id),'type'=>$type])->select();
		
		if (empty($data[0])) {return $deal;}
		
		$data_id = array_column($data,'position');
		
		$diff = array_diff($deal_id,$data_id);
		
		if (count($diff) == count($deal_id)) {return $deal;}
		
		$res = $this->where(['position'=>array('in',$data_id),'status'=>['neq'=>1]])->save(['status'=>0]);
		
		if (false === $res) {return $deal;}
		
		foreach ($deal as $v) {
			foreach($diff as $k) {
				if ($k == $v['id']) {
					$list[] = $v;break;
				}
			}
		}
		
		return $list;
		
	}
	
	/*
	 * 检测用户
	 * @return  integer  0:未检测到数据, 1:检测用户成功, -1:检测用户失败
	 * @author 鹿文学
	 */
	public function checkUser() {
		
		$data = $this->user();
		
		if(empty($data[0])) {$this->error='未检测到数据';return 0;}
		
		if($this->addAll($data)>0){$this->error='检测用户成功';return 1;}else{$this->error='检测用户失败';return -1;}
		
	}
	
	/*
	 * 检测提现
	 * @return  integer  0:未检测到数据, 1:检测提现成功, -1:检测提现失败
	 * @author 鹿文学
	 */
	public function checkWithdraw() {
		
		$data = $this->widthdraw();
		
		if(empty($data[0])) {$this->error='未检测到数据';return 0;}
		
		if($this->addAll($data)>0){$this->error='检测结算成功';return 1;}else{$this->error='检测结算失败';return -1;}
		
		
	}
	
	/*
	 * 检测推广员
	 * @return  integer  0:未检测到数据, 1:检测推广员成功, -1:检测推广员失败
	 * @author 鹿文学
	 */
	public function checkPromote() {
		
		$data = $this->promote();

		if(empty($data[0])) {$this->error='未检测到数据';return 0;}
		
		if($this->addAll($data)>0){$this->error='检测推广员成功';return 1;}else{$this->error='检测推广员失败';return -1;}	
		
	}
	
	/*
	 * 一键检测
	 * @return  integer  0:未检测到数据, 1:一键检测成功, -1:一键检测失败
	 * @author 鹿文学
	 */
	public function checkOne() {
		$data = $this->one();

		if(empty($data[0])) {$this->error='未检测到数据';return 0;}
		
		if($this->addAll($data)>0){$this->error='一键检测成功';return 1;}else{$this->error='一键检测失败';return -1;}
		
	}
	
	/*
	 * 检测用户相关数据
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
	public function user() {
		
		$data = array();
		
		// 开发者
//		$developers = D('developers')->checkDeveloper();
//
//		if($developers[0]) {$data = array_merge($developers,$data);}
		
		// 游戏充值
		$spend = D('Spend')->checkSpend();
		
		if($spend[0]) {$data = array_merge($spend,$data);}
		
	  // 补单
		$supplement = D('Spend')->checkSupplement();
		
		if($supplement[0]) {$data = array_merge($supplement,$data);}
		
		// 平台币充值
		$platformcoin = D('Deposit')->checkPlatformCoin();
		
		if($platformcoin[0]) {$data = array_merge($platformcoin,$data);}
		
		// 绑币充值
		$bindcoin = D('BindRecharge')->checkBindCoin();
		
		if($bindcoin[0]) {$data = array_merge($bindcoin,$data);}
		
		return $data;
		
	}
	
	/*
	 * 检测提现相关数据
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
	public function widthdraw() {
		
		$data = array();
		
		$widthdraw = D('Withdraw');
		
		// 开发者申请提现
//		$developerwithdraw = $widthdraw->checkDeveloperWithdraw();
//
//		if($developerwithdraw[0]) {$data = array_merge($developerwithdraw,$data);}
		
		// 推广员申请提现
		$promotewithdraw = $widthdraw->checkPromoteWithdraw();
		
		if($promotewithdraw[0]) {$data = array_merge($promotewithdraw,$data);}
		
		return $data;
	}
	
	/*
	 * 检测推广员相关数据
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
	public function promote() {
		
		$data = array();
		
		// 推广员
		$promote = D('Promote')->checkPromote();

		if($promote[0]) {$data = array_merge($promote,$data);}

		// // 混服申请
		// $siteapply = D('SiteApply')->checkSiteApply();

		// if($siteapply[0]) {$data = array_merge($siteapply,$data);}
		
		// 分包申请
		$apply = D('Apply');
		
		$packageapply = $apply->checkPackageApply();

		if($packageapply[0]) {$data = array_merge($packageapply,$data);}
		
		// 打包申请
		$packapply = $apply->checkPackApply();
		
		if($packapply[0]) {$data = array_merge($packapply,$data);}
		
		// APP分包申请
		$appapply = D('AppApply');
		
		$packageappapply = $appapply->checkPackageAppApply();
		
		if($packageappapply[0]) {$data = array_merge($packageappapply,$data);}
		
		// APP打包申请
		$packappapply = $appapply->checkPackAppApply();
		
		if($packappapply[0]) {$data = array_merge($packappapply,$data);}
		
		return $data;
	}
	
	/*
	 * 一键检测相关数据
	 * @return array   检测结果数据集
	 * @author 鹿文学
	 */
	public function one() {

		$data = array();

		// 用户相关
		$user = $this->user();

		if ($user[0]) {$data = array_merge($user,$data);}

		// 提现相关
		$widthdraw = $this->widthdraw();

		if ($widthdraw[0]) {$data = array_merge($widthdraw,$data);}

		// 推广员相关
		$promote = $this->promote();

		if ($promote[0]) {$data = array_merge($promote,$data);}

		// 游戏分成比例
		$game = D('Game');

		$gamepromotesharing = $game->checkGamePromoteSharing();

		if($gamepromotesharing[0]) {$data = array_merge($gamepromotesharing,$data);}

		// // 开发者游戏
		// $gamedevelopers = $game->checkGameDevelopers();

		// if($gamedevelopers[0]) {$data = array_merge($gamedevelopers,$data);}

		// 游戏原包上传
		$gamesource = D('GameSource')->checkGameSource();

		if($gamesource[0]) {$data = array_merge($gamesource,$data);}

		// 礼包数量
		$giftbagnumber = D('Giftbag')->checkGiftbagNumber();

		if($giftbagnumber[0]) {$data = array_merge($giftbagnumber,$data);}

		// 评论
//		$comment = D('Comment')->checkComment();
//
//		if($comment[0]) {$data = array_merge($comment,$data);}

		// 发放平台币
//		$provideuserisget = D('ProvideUser')->checkProvideUserIsGet();
//
//		if($provideuserisget[0]) {$data = array_merge($provideuserisget,$data);}

        // 发放绑币
		$provideisget = D('Provide')->checkProvideIsGet();

		if($provideisget[0]) {$data = array_merge($provideisget,$data);}

		return $data;

	}

}