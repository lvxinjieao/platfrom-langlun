<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace Home\Controller;

use OT\DataDictionary;
use User\Api\PromoteApi;
use User\Api\UserApi;
use Org\WeiXinSDK\Weixin;

/**
 * 前台首页控制器
 * 主要获取首页聚合数据
 */
class PromoteController extends BaseController
{
    //系统首页
    public function index($p=1){
        $user = D('Promote')->isLogin();
        if(empty($user)) {
            $this->redirect("Union/Index/index");
        }
        //游戏内所有消费
        $this->assign("today",$this->total(1));
        $this->assign("month",$this->total(3));
        $this->assign("total",$this->total(''));
        $this->assign("yesterday",$this->total(5));
        $this->assign("lastmonth", $this->total(6));
        $this->assign("url",$url);
        
        $category = D('Category')->info('tui_gg');
        /* 获取当前分类列表 */
		$Document = D('Document');
		$list = $Document->page($p, $category['list_row'])->lists(47,'level desc,id desc');
		$map['category_id'] = $category['id'];
		$map['status'] = 1;
		$map['display'] = 1;
		$count =  D('Document')->where($map)->count();
		//分页
		if($count > $category['list_row']){
		    $page = new \Think\Page($count, $category['list_row']);
		    $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
		    $this->assign('_page', $page->show());
		}
		$this->assign('meta_title','首页');
		$this->assign('list', $list);
        $this->display();
    } 

    private function total($type)
    {
        if ($_REQUEST['promote_id'] === null || $_REQUEST['promote_id'] === '0') {
            $ids = M('Promote', 'tab_')->where('parent_id=' . get_pid())->getfield("id", true);
            if (empty($ids)) {
                $ids = array(get_pid());
            }
            array_unshift($ids, get_pid());
        } else {
            $ids = array($_REQUEST['promote_id']);
        }
        $where['promote_id'] = array('in', $ids);
        $where['pay_status'] = 1;
//        $where['pay_way'] = array('egt',0);
        $where['is_check'] = array('NEQ', 2);
        switch ($type) {
            case 1: { // 今天
                $start = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
                $end = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;
            };
                break;
            case 3: {
                // 本月
                $start = mktime(0, 0, 0, date('m'), 1, date('Y'));
                $end = mktime(0, 0, 0, date('m') + 1, 1, date('Y')) - 1;
            };
                break;
            case 4: {
                // 本年
                $start = mktime(0, 0, 0, 1, 1, date('Y'));
                $end = mktime(0, 0, 0, 1, 1, date('Y') + 1) - 1;
            };
                break;
            case 5: { // 昨天
                $start = mktime(0, 0, 0, date('m'), date('d') - 1, date('Y'));
                $end = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
            };
                break;
            case 6:{
                $start = mktime(0,0,0,date('m')-1,1,date('Y'));
                $end = mktime(0,0,0,date('m'),1,date('Y'))-1;
            };
                break;
            case 9: { // 前七天
                $start = mktime(0, 0, 0, date('m'), date('d') - 6, date('Y'));
                $end = mktime(date('H'), date('m'), date('s'), date('m'), date('d'), date('Y'));
            };
                break;
            default:
                ;
        }
        if (isset($start) && isset($end)) {
            $where['pay_time'] = array("BETWEEN", array($start, $end));
        }
        $total = M('spend', "tab_")->field("SUM(pay_amount) as amount")->where($where)->select();
        $total = $this->huanwei($total[0]['amount']);
        return $total;
    }

    private function huanwei($total)
    {
        $total = empty($total) ? '0' : trim($total . ' ');
        $len = strlen($total) - 3;
				$uion = 2;
        if ($len > 16) {
            // 兆
            $len = $len - 20;
            $total = $len > 0 ? ($len > 4 ? ($len > 8 ? round(($total / 1e28), $uion) . '万亿兆' : round(($total / 1e24), $uion) . '亿兆') : round(($total / 1e20), $uion) . '万兆') : round(($total / 1e16), $uion) . '兆';
        } else if ($len > 8) {
            // 亿
            $len = $len - 12;
            $total = $len > 0 ? (round(($total / 1e12), $uion) . '万亿') : round(($total / 1e8), $uion) . '亿';
        } else if ($len > 4) {
            // 万
            $total = (round(($total / 10000), $uion)) . '万';
        }
        return $total;
    }


    /**
     * 我的基本信息
     */
    public function base_info()
    {
        if (IS_POST) {
            $type = $_REQUEST['type'];
            $map['id'] = get_pid();
            $se = array();
						$_REQUEST = array_trim($_REQUEST);
            switch ($type) {
                case 0:
                    if (empty(trim($_REQUEST['nickname']))) {
                        $this->error('昵称不能为空', U('Promote/base_info'));
                        exit();
                    }
                    if (empty($_REQUEST['real_name'])) {
                        $this->error('联系人不能为空', U('Promote/base_info'));
                        exit();
                    }
                    if (!preg_match('/^([\xe4-\xe9][\x80-\xbf]{2}){2,4}$/', $_REQUEST['real_name'])) {  
                        $this->error('您输入的联系人姓名格式不正确', U('Promote/base_info'));
                        exit();
                    }
                    if (empty($_REQUEST['email'])) {
                        $this->error('电子邮箱不能为空', U('Promote/base_info'));
                        exit();
                    }
                    if (!filter_var($_REQUEST['email'], FILTER_VALIDATE_EMAIL)) {
                        $this->error('您输入的电子邮箱地址不合法', U('Promote/base_info'));
                        exit();
                    }
                    if (empty($_REQUEST['alipay_account'])) {
                        $this->error('支付宝账号不能为空', U('Promote/base_info'));
                        exit();
                    }
                    $se['nickname'] = $_REQUEST['nickname'];
                    $se['real_name'] = $_REQUEST['real_name'];
                    $se['email'] = $_REQUEST['email'];
                    $se['alipay_account'] = $_REQUEST['alipay_account'];
                    break;
                case 1:
                    if (empty($_REQUEST['mobile_phone'])) {
                        $this->error('结算手机号不能为空', U('Promote/base_info'));
                        exit();
                    }
                    if(!preg_match("/^1[34578]\d{9}$/", $_REQUEST['mobile_phone'])) {
                        $this->error('您输入的手机号码格式不合法', U('Promote/base_info'));
                        exit();
                    }
                    if ($_REQUEST['s_county'] === "市、县级市") {
                        $this->error('开户城市填写不完整', U('Promote/base_info'));
                        return false;
                        exit();
                    }
                    if (empty(trim($_REQUEST['account_openin']))) {
                        $this->error('开户网点不能为空', U('Promote/base_info'));
                        exit();
                    }
                    if (empty(trim($_REQUEST['bank_name']))) {
                        $this->error('收款银行不能为空', U('Promote/base_info'));
                        exit();
                    }
                    if (empty(trim($_REQUEST['bank_card']))) {
                        $this->error('银行卡号不能为空', U('Promote/base_info'));
                        exit();
                    }
						if (!preg_match('/^\d{10,20}$/', $_REQUEST['bank_card'])) {  
                        $this->error('您输入的银行卡号格式不正确', U('Promote/base_info'));
                        exit();
                    }
                    if (empty(trim($_REQUEST['bank_account']))) {
                        $this->error('银行户名不能为空', U('Promote/base_info'));
                        exit();
                    }
                    $se['mobile_phone'] = $_REQUEST['mobile_phone'];
                    $se['bank_name'] = $_REQUEST['bank_name'];
                    $se['bank_card'] = $_REQUEST['bank_card'];
                    $se['bank_account'] = $_REQUEST['bank_account'];
                    $se['account_openin'] = $_REQUEST['account_openin'];
                    $se['bank_area'] = $_REQUEST['s_province'] . ',' . $_REQUEST['s_city'] . ',' . $_REQUEST['s_county'];
                    break;
                case 2:
                    $prp = M("promote", "tab_")->where($map)->find();
                    $ue = new UserApi();
                    if (empty(trim($_REQUEST['old_password']))) {
                        $this->error('旧密码不能为空', U('Promote/base_info'));
                        exit();
                    }
                    if (empty(trim($_REQUEST['password']))) {
                        $this->error('新密码不能为空', U('Promote/base_info'));
                        exit();
                    }
                    if (strlen(trim($_REQUEST['password']))<6) {
                        $this->error('登录密码不能小于6位字符', U('Promote/base_info'));
                        exit();
                    }
                    if (empty(trim($_REQUEST['confirm_password']))) {
                        $this->error('确认密码不能为空', U('Promote/base_info'));
                        exit();
                    }
                    if ($_REQUEST['password'] !== $_REQUEST['confirm_password']) {
                        $this->error('新密码和确认密码不一致', U('Promote/base_info'));
                        return false;
                    }
                    if ($this->think_ucenter_md5($_REQUEST['old_password'], UC_AUTH_KEY) !== $prp['password']) {
                        $this->error('旧密码不正确', U('Promote/base_info'));
                        return false;
                        exit();

                    } else if ($_REQUEST['password'] !== $_REQUEST['confirm_password']) {
                        $this->error('新密码和确认密码不一致', U('Promote/base_info'));
                        return false;
                    } else {
                        $se['password'] = $this->think_ucenter_md5($_REQUEST['confirm_password'], UC_AUTH_KEY);
                    }
                    break;
                case 3:
                    $prp = M("promote", "tab_")->where($map)->find();
                    $ue = new UserApi();

                    //更改旧二级密码
                    if (!empty($prp['second_pwd'])){
                        if (empty(trim($_REQUEST['old_second_pwd']))) {
                            $this->error('旧二级密码不能为空', U('Promote/base_info'));
                            exit();
                        }

                        if (strlen(trim($_REQUEST['old_second_pwd']))<6) {
                            $this->error('二级密码不能小于6位字符', U('Promote/base_info'));
                            exit();
                        }
                    }


                    if (empty(trim($_REQUEST['second_pwd']))) {
                        $this->error('新二级密码不能为空', U('Promote/base_info'));
                        exit();
                    }

                    if (strlen(trim($_REQUEST['second_pwd']))<6) {
                        $this->error('新二级密码不能小于6位字符', U('Promote/base_info'));
                        exit();
                    }

                    if (empty(trim($_REQUEST['confirm_second_pwd']))) {
                        $this->error('确认密码不能为空', U('Promote/base_info'));
                        exit();
                    }

                    if ($_REQUEST['second_pwd'] !== $_REQUEST['confirm_second_pwd']) {
                        $this->error('新二级密码和确认密码不一致', U('Promote/base_info'));
                        return false;
                    }

                    if ($this->think_ucenter_md5($_REQUEST['old_second_pwd'], UC_AUTH_KEY) != $prp['second_pwd'] && !empty($prp['second_pwd'])) {
                        $this->error('旧二级密码错误', U('Promote/base_info'));
                        return false;
                        exit();

                    } else {
                        $se['second_pwd'] = $this->think_ucenter_md5($_REQUEST['confirm_second_pwd'], UC_AUTH_KEY);
                    }
                    break;
                default:
                    $se['nickname'] = $_REQUEST['nickname'];
                    $se['real_name'] = $_REQUEST['real_name'];
                    $se['email'] = $_REQUEST['email'];
                    break;
            }
            $res = M("promote", "tab_")->where($map)->save($se);
            if ($res !== false) {
                $this->success('修改成功', U('Promote/base_info?type=' . $type));
            } else {
                $this->error('修改失败', U('Promote/base_info'));
            }
        } else {
            $model = M('Promote', 'tab_');
            $data = $model->find(session("promote_auth.pid"));
            $data['bank_area'] = explode(',', $data['bank_area']);
            $this->meta_title = "账户信息";
            $this->assign("data", $data);
            $this->display();
        }
    }
/**
 * 扫码二维码图片生成
* @param int $level
* @param int $size
*/
    public function qrcode($level=3,$size=4,$url=""){
        Vendor('phpqrcode.phpqrcode');
        $errorCorrectionLevel =intval($level) ;//容错级别
        $matrixPointSize = intval($size);//生成图片大小
        //生成二维码图片
        ob_clean();
        $object = new \QRcode();
        echo $object->png(base64_decode(base64_decode($url)), false, $errorCorrectionLevel, $matrixPointSize, 2);
    }
    public function checkAccount($account){
        $user = get_promote_entity($account, true);
        if($user){
            $this->ajaxReturn(array('status'=>1));
        }else{
            $this->ajaxReturn(array('status'=>0));
        }
    }

    //余额充值
    public function balance() {
        if (IS_POST) {
            //$real_amount = $amount = I('amount');
            $real_amount = $amount = 0.01;
            if(!is_numeric($amount)){
                $res_msg = '充值金额不是数字';
                $this->show_balance();
                echo "<script>layer.msg('{$res_msg}',{time:1000});setTimeout(function () {
                      window.history.back(-1);},1000)</script>;
										return false;";
                $this->redirect('balance', array('status'=>1), 3, '');
            }

            if (empty($_REQUEST['account'])) {
                $user = get_promote_entity(PID);
            } else {
                $user = get_promote_entity($_REQUEST['account'], true);
            }

            $order_no = "TB_" . date('Ymd') . date('His') . sp_random_string(4);
            $create['amount'] = $amount;
            $create['pay_order_number'] = $order_no;

            $vo = new \Think\Pay\PayVo();
            $vo->setBody("账户余额")
                ->setFee($amount)//支付金额
                ->setTitle("余额充值")
                ->setOrderNo($order_no)
                ->setSignType("MD5")
                ->setPayMethod("direct")
                ->setTable("balance")
                ->setUserId($user['id'])
                ->setAccount($user['account'])
                ->setPromoteId(PID)
                ->setPromoteName(PROMOTE_ACCOUNT)
                ->setMoney($user['balance_coin']);
            switch (I('pay_type')) {
                case 'swiftpass':
                    //判断是否开启微信充值
                    if (pay_set_status('wei_xin') == 0) {
                        $this->error("网站未开启微信充值", '', 1);
                        exit();
                    }
                    $weixn = new Weixin();
                    $is_pay = json_decode($weixn->weixin_pay("余额充值", $order_no, $real_amount), true);
                    if ($is_pay['status'] === 1) {
                        $json_['out_trade_no'] = $order_no;
                        $json_['amount'] = $amount;
                        $json_['pay_money'] = $real_amount;
                        $json_['code_img_url'] = U('qrcode', array('level' => 3, 'size' => 4, 'url' => base64_encode(base64_encode($is_pay['url']))));
                    }
                    $create['pay_way'] = 2;
                    $this->add_balance($user, $create);

                    //$this->show_balance();
                    $this->assign('data',$json_);
                    $this->display('weixin_pay');
                    //echo "<script> img_qrcode(" . json_encode($json_) . ") </script>";
                    break;
                default:
                    //判断是否开启支付宝充值
                    if (pay_set_status('alipay') == 0) {
                        $this->error("网站未开启支付宝充值", '', 1);
                        exit();
                    }
                    $vo->setService("create_direct_pay_by_user")
                        ->setPayWay(1);
                    $pay = new \Think\Pay('alipay', C('alipay'));
                    echo $pay->buildRequestForm($vo);
                    break;
            }



        } else {
            $model = M('Promote', 'tab_');
            $data = $model->find(session("promote_auth.pid"));
            $data['bank_area'] = explode(',', $data['bank_area']);
            $this->assign('data',$data);
            $this->meta_title = '账户余额';
            $this->display();
        }

    }

    /**
     * 监控微信是否支付完成
     */
    public function checkOrder($order_number){
        $map['pay_order_number'] = $order_number;
        $data = M('Balance','tab_')
            ->where($map)
            ->field('pay_status')
            ->find();
        if ($data['pay_status'] == 1){
            $this->success('支付成功');
        }else{
            $this->error('暂未支付');
        }
    }

    /**
     * []加入余额订单
     * @author gjt[syt]
     */
    public function add_balance($promote,$data){
        $balance = M("Balance","tab_");
        $balance_data['order_number']         = "";
        $balance_data['pay_order_number']     = $data['pay_order_number'];
        $balance_data['money']                = $data['amount'];
        $balance_data['balance']              = $promote['balance_coin'];
        $balance_data['pay_status']           = 0;
        $balance_data['recharge_type']        = $data['pay_way'];
        $balance_data['create_time']          = time();
        $balance_data['recharge_id']          = $promote['id'];
        $balance_data['recharge_account']     = $promote['account'];
        $balance_data['promote_id']           = PID;
        $balance_data['promote_account']      = PROMOTE_ACCOUNT;
        $balance->create($balance_data);
        $result = $balance->add();
        return $result;
    }

    public function show_balance() {
        $model = M('Promote', 'tab_');
        $data = $model->find(session("promote_auth.pid"));
        $data['bank_area'] = explode(',', $data['bank_area']);
        $this->assign('data',$data);
        $this->meta_title = '账户余额';
        $this->display('balance');
    }

    public function think_ucenter_md5($str, $key = 'ThinkUCenter')
    {
        return '' === $str ? '' : md5(sha1($str) . $key);
    }
    /**
    *申请域名
    */
    public function apply_domain($p=0){
        $apply_domain=M('ApplyUnion','tab_');
        $applydata=$apply_domain->where(array('union_id'=>get_pid()))->find();
        if($applydata){
            $apply['domain_url']=$applydata['domain_url'];
            $apply['status']=$applydata['status'];
            $apply['apply_domain_type']=$applydata['apply_domain_type'];
        }else{
            $apply['status']=-1;
            $apply['domain_url']=session("promote_auth.account").'.'.C(PRIMARY_SITE);
        }
        $union_domain = M('Apply_union','tab_')->where(array('union_id'=>get_pid()))->find();
        if($union_domain){
            $url=$union_domain['domain_url'];
            $status=$union_domain['status'];
        }else{
            $url='';
            $status=-1;
        }
        $this->assign("status",$status);
        $this->assign('apply',$apply);
        parent::lists("Promote",$p,$map,'站点申请');
    }
    /**
     * [站点配置]
     * @param  integer $p [description]
     * @return [type]     [description]
     */
    public function domain_set($p=0){
        $model=M('Apply_union','tab_');
        $map['union_id']=get_pid();
        if(IS_POST){
            $union_set=json_encode($_POST);
            $resule=$model->where($map)->setField('union_set',$union_set);
            if($resule!==false){
                $this->ajaxReturn(array('status'=>1,'msg'=>'保存成功'));
            }else{
                $this->ajaxReturn(array('status'=>0,'msg'=>'保存失败'));
            }
        }else{
            $data=$model->where($map)->find();
            if(!$data['status']){
                redirect('http://'.$_SERVER['HTTP_HOST'].U('apply_domain'));
            }
            $this->meta_title = "站点配置";
            $this->assign('data',json_decode($data['union_set'],true));
            $this->display('SiteBase/index');
        }
    }
    public function apply_domain_url($p=0){
        $apply_domain=M('ApplyUnion','tab_');
        $applydata=$apply_domain->where(array('union_id'=>get_pid()))->find();
        if($applydata){
            $this->error('您已申请过~');
        }
        if(isset($_REQUEST['apply_domain_type'])){
            if($_REQUEST['apply_domain_type']==1){
                if($_REQUEST['site_url']==''){
                    $this->error("请填写需要绑定的域名！");
                }
                $chec=$apply_domain->where(array('domain_url'=>$_REQUEST['site_url']))->find();
                if($chec){
                    $this->error("该域名已被绑定~");
                }
                $data['domain_url']=$_REQUEST['site_url'];
            }else{
                $data['domain_url']='http://'.$_REQUEST['site_url'];
            }
            $data['union_id']=get_pid();
            $data['union_account'] = session("promote_auth.account");
            $data['apply_time'] = NOW_TIME;
            C('PROMOTE_URL_AUTO_AUDIT')==1?$data['status'] = 1:$data['status'] = 0;
            C('PROMOTE_URL_AUTO_AUDIT')==1?$data['dispose_time'] = time():$data['dispose_time'] = '';
            $data['apply_domain_type'] = $_REQUEST['apply_domain_type'];
            $data['enable_status'] = 1;
            $add= $apply_domain->add($data);
            if($add){
                $this->success("申请成功，请耐心等待审核",U('Promote/apply_domain'));
            }else{
                $this->error("申请失败！",U('Promote/apply_domain'));
            }
        }else{
            $this->error("参数错误");
        }
    }
    /**
     *子账号
     */
    public function mychlid($p = 0)
    {
        if ($_REQUEST['account'] != null) {
            $map['account'] = array('like', '%' . str_replace('%','\%',$_REQUEST['account']) . '%');
        }
        $map['parent_id'] = session("promote_auth.pid");
				
        parent::lists("Promote", $p, $map,'子渠道列表');
    }

    public function add_chlid()
    {
        if (IS_POST) {
            $user = new PromoteApi();
            $businer = M('Promote','tab_')->field('busier_id')->where(array('id'=>get_pid()))->find();
            $_POST['busier_id'] = $businer['busier_id'];
						$_POST = array_trim($_POST);
            $res = $user->promote_add($_POST);
            if (is_numeric($res)) {
                $this->ajaxReturn(array('status'=>1,'msg'=>"子渠道账号添加成功"));
            } else {
                $this->ajaxReturn(array('status'=>-1,'msg'=>$res));
            }
        } else {
						$this->meta_title = "添加子渠道";
            $this->display();
        }

    }

    public function edit_chlid($id = 0)
    {
        if (IS_POST) {
            if (empty($_POST['password'])) {
                unset($_POST['password']);
            }
						
						$type = $_REQUEST['type'];
						$_POST = array_trim($_POST);
						switch($type) {
							case 1:{
								if ($_POST['bank_area'] === "") {
										$this->error('开户城市填写不完整');
										return false;
										exit();
								}
								if (empty(trim($_POST['account_openin']))) {
										$this->error('开户网点不能为空');
										exit();
								}
								if (!preg_match('/^([a-zA-z]{3,})|(([\xe4-\xe9][\x80-\xbf]{2}){2,})$/', $_POST['account_openin'])) {  
										$this->error('您输入的开户网点不正确');
										exit();
								}
								if (empty(trim($_POST['bank_name']))) {
										$this->error('收款银行不能为空');
										exit();
								}
								if (!preg_match('/^([a-zA-z]{3,})|(([\xe4-\xe9][\x80-\xbf]{2}){2,})$/', $_POST['bank_name'])) {  
										$this->error('您输入的收款银行不正确');
										exit();
								}
								if (empty(trim($_POST['bank_account']))) {
										$this->error('银行户名不能为空');
										exit();
								}
								if (empty(trim($_POST['bank_card']))) {
										$this->error('银行卡号不能为空');
										exit();
								}
								if (!preg_match('/^\d{10,20}$/', $_POST['bank_card'])) {  
										$this->error('您输入的银行卡号格式不正确');
										exit();
								}
							};break;
							default:{
								if (empty($_POST['real_name'])) {
										$this->error('联系人不能为空');
										exit();
								}
								if (!preg_match('/^([\xe4-\xe9][\x80-\xbf]{2}){2,4}$/', $_POST['real_name'])) {  
										$this->error('您输入的联系人姓名格式不正确');
										exit();
								}
								if (empty($_POST['mobile_phone'])) {
										$this->error('手机号不能为空');
										exit();
								}
								if(!preg_match("/^1[34578]\d{9}$/", $_POST['mobile_phone'])) {
										$this->error('您输入的手机号码格式不合法');
										exit();
								}
								if (empty($_POST['email'])) {
										$this->error('电子邮箱不能为空');
										exit();
								}
								if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
										$this->error('您输入的电子邮箱地址不合法');
										exit();
								}
								
							}	
						}
						
						
            $businer = M('Promote','tab_')->field('busier_id')->where(array('id'=>get_pid()))->find();
            $_POST['busier_id'] = $businer['busier_id'];
            $user = new PromoteApi();
						
            $res = $user->edit($_POST);
            if ($res !== false) {
                $this->ajaxReturn(array('status'=>1,'info'=>"子账号修改成功"));
            } else {
                $this->ajaxReturn(array('status'=>-1,'info'=>"修改子账号失败"));
            }
        } else {
            $promote = A('Promote', 'Event');
            $this->meta_title = '修改子渠道';
            $promote->baseinfo('edit_chlid', $id);
        }

    }
    public function logout(){
    	$user = D('Promote')->isLogin();
    	if($user){
    		session('[destroy]');
    		$this->redirect('Index/index');
    	} else {
    		$this->redirect('Index/index');
    	}
    }
}









