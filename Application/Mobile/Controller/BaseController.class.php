<?php
namespace Mobile\Controller;
use Think\Controller;
use Com\Wechat;
use Com\WechatAuth;
use Admin\Model\PointTypeModel;
use Common\Model\ServerModel;
/**
* 首页
*/
class BaseController extends Controller {
	 public function __construct() {
         $config = api('Config/lists');
         C($config);
         $_SESSION['media']="mobile.php";
		$serverhost=$_SERVER['HTTP_HOST'];
		$headerurl = 'http://'.$serverhost.'/media.php';
		if(!is_mobile_request()){
			$http = 'http';
	        if(is_https()){
	            $http = 'https';
	        }
			$url = str_replace('mobile.php','media.php',$_SERVER['REQUEST_URI']);
			$headerurl = $http.'://'.$_SERVER["HTTP_HOST"].$url;
			Header("Location: $headerurl"); exit;
		}
		$config = api('Config/lists');
        C($config); //添加配置
         parent::__construct();
         $pc_show = C('WAP_SHOW');
         if($pc_show!=1){
             $this->display('Public/404');exit;
         }
		$serverhostarr = array('in',$serverhost.",http://".$serverhost.",https://".$serverhost);
//		$host=M('apply_union','tab_')->field('union_id,union_account,status,apply_domain_type,domain_url,union_set')->where(array('domain_url'=>$serverhostarr))->find();
//		if($host==''&&$serverhost!=C(PC_SET_DOMAIM)){
//			die('<h1>404 not found</h1>The requested URL /mobile.php was not found on this server.');
//		}else{
//			if($serverhost!=C(PC_SET_DOMAIM)){
//				session('for_third',C(PC_SET_DOMAIM));
//			}
//		}
//		if($host['status']==1){
//			session('union_host',$host);
//		}else{
//			if($serverhost!=C(PC_SET_DOMAIM)){
//				echo "<script>alert('The site is not audited')</script>";exit;
//			}
//		}
		//排除自动登录方法
		$arr = array('user','verify','logout','login','wechat_login_game','register','iosdownload','isChineseName','isChineseName','isChineseNamereturn','isidcard','isidcardreturn','checkphoneexsite','checkaccount','telsvcode','check_tel_code','iosdownload','ykregister','down_file');
        if (!in_array(strtolower(ACTION_NAME), $arr, true) && (I('get.type')!='qq') ) {
             $this->wechat_login();
		}

		if(session('union_host')){
			$union_set=json_decode(session('union_host')['union_set'],true);
			$this->assign('union_set',$union_set);
		}
		$logindiv = $this->fetch('Public:loginsdk');//登录sdk

		//实名认证
		$name_auth = M('Tips','tab_')->where(['obj'=>1,'status'=>1])->find();
		$zhuceauth = C('REAL_NAME_REGISTER');
		$this->assign('open_name_auth',$zhuceauth);
		$this->assign('open_auth_tip',empty($name_auth)?0:1);
		$this->assign('name_auth_tip',$name_auth['tip']);
        $this->assign('logdiv',$logindiv);
    	$userdata = M('User','tab_')->field('lock_status,real_name,idcard')->find(is_login());
    	if($userdata['lock_status']!=1){
    		//禁止锁定用户登录
    		session('user_auth', null);
            session('user_auth_sign', null);
            //session("wechat_token", null);
    	}
        if(strtolower(ACTION_NAME)=='open_game'&&!empty($name_auth)){
        	if($userdata['real_name']==''&&$userdata['idcard']==''&&$name_auth['end_time']>time()){
        		session('stop_play',0);
        		$this->assign('user_no_auth',1);
        	}elseif($userdata['real_name']==''&&$userdata['idcard']==''&&$name_auth['end_time']<time()){
        		session('stop_play',1);
        		$this->assign('user_no_auth',1);
        		$this->assign('stop_play',1);
        	}else{
        		session('stop_play',0);
        	}
        }else{
        	session('stop_play',0);
        }


        $pointtype = new PointTypeModel();
        //用户消息
        if(is_login()>0){
        	$servermodel = new ServerModel();
        	$send_notice = $servermodel->send_server_notice(is_login());
        	$msg = M('msg','tab_')->where(['user_id'=>is_login(),'status'=>2])->find();
        	if(!empty($msg)){
        		$this->assign('newmsg',1);
        	}
        	//是否签到
        		$lgmap['pt.key'] = 'sign_in';
		        if(is_login()){
		            $lgjoin .= ' and pr.user_id = '.is_login();
		        }
		        $loginpont = $pointtype->getUserLists($lgmap,$lgjoin,'ctime desc',1,1);
		        if(empty($loginpont[0]['user_id'])){
		            $issignin = 1;//是否显示商城红点
		        }elseif(!empty($loginpont[0]['user_id'])&&$loginpont[0]['ct']==date('Y-m-d',time())){
		            $issignin = 0;
		        }else{
		            $issignin = 1;
		        }
        		$this->assign('mallissignin',$issignin);
        }else{
        	$this->assign('mallissignin',1);
        }
    	$list = $pointtype->where(['key'=>'bind_phone'])->find();
    	$bindphoneadd = $list['point']>0?$list['point']:0;
        $this->assign('bindphoneadd',$bindphoneadd);
        //登录按钮
        $thirloginstr = "qq_login,wx_login,wb_login,bd_login";
        $logbutmap['name'] = array('in',$thirloginstr);
        $tool = M('tool',"tab_")->field('name,status')->where($logbutmap)->select();
        foreach ($tool as $key => $val) {
            $this->assign($tool[$key]['name'],$val['status']);
        }

        //微信分享和QQ分享
         $share_decri = C('SHARE_TITLE');
         $APP_ICO = get_cover(C('APP_ICO'),'path');
         if(strpos($APP_ICO,'http') === false){
             $APP_ICO = 'http://'.$_SERVER['HTTP_HOST'].$APP_ICO;
         }
         $share_img = $APP_ICO;
         $this->assign('share_img',$share_img);
         $this->assign('share_decri',$share_decri);
         if(is_weixin() && (strpos($_SERVER['PATH_INFO'],'Game/open_game')===false)){
            $result = auto_get_access_token('access_token_validity.txt');

            $appid = C('wechat.appid');

            $appsecret = C('wechat.appsecret');

            if (!$result['is_validity']) {

                $auth = new WechatAuth($appid, $appsecret);

                $token = $auth->getAccessToken();

                $token['expires_in_validity'] = time() + $token['expires_in'];

                wite_text(json_encode($token), 'access_token_validity.txt');

                $result = auto_get_access_token('access_token_validity.txt');

            }

            $auth = new WechatAuth($appid, $appsecret,$result['access_token']);

            $ticket = auto_get_ticket(dirname(__FILE__) . '/ticket.txt');

            if(!$ticket['is_validity']){

                $jsapiTicketjson = $auth->getticket();

                $jsapiTicketarr = json_decode($jsapiTicketjson,true);

                $jsapiTicketarr['expires_in_validity'] = time() + $jsapiTicketarr['expires_in'];

                wite_text(json_encode($jsapiTicketarr), dirname(__FILE__) . '/ticket.txt');

                $ticket = auto_get_ticket(dirname(__FILE__) . '/ticket.txt');
            }

            $jsapi_ticket = $ticket['ticket'];
            $timestamp = time();
            $noncestr = random_string(16);
            $url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
            $signature = sha1('jsapi_ticket='.$jsapi_ticket.'&noncestr='.$noncestr.'&timestamp='.$timestamp.'&url='.$url);
            $this->assign('appid',$appid);
            $this->assign('timestamp',$timestamp);
            $this->assign('noncestr',$noncestr);
            $this->assign('signature',$signature);
            $this->assign('isweixin',1);
        }
	}

	public function is_login(){
		$user = session('user_auth');
	    if (empty($user)) {
	        return 0;
	    } else {
	        return session('user_auth_sign') == data_auth_sign($user) ? $user['user_id'] : 0;
	    }
	}

	/** * 第三方微信公众号登陆 * */
	public function wechat_login($state=0,$gid=0,$pid=0,$game_id=0){
		if(!session("wechat_token") && is_weixin() && get_tool_status('wechat')){
		    $appid = C('wechat.appid');
			$appsecret = C('wechat.appsecret');
			$auth  = new WechatAuth($appid, $appsecret);
            $gid = $gid ? $gid:$game_id;
			//if(session('for_third')==C('PC_SET_DOMAIM')){
			//	$state=$_SERVER['HTTP_HOST'];
			//	$redirect_uri = "http://".session('for_third')."/mobile.php/ThirdLogin/wechat_login/gid/".$game_id."/pid/".$pid;
			//}else{
				$redirect_uri = "http://".$_SERVER['HTTP_HOST']."/mobile.php/ThirdLogin/wechat_login/gid/".$game_id."/pid/".$pid;
			//}
            redirect($auth->getRequestCodeURL($redirect_uri,$state));
		}
	}

	/** * 第三方微信扫码登陆 * */
	public function wechat_qrcode_login($state=1){
		if(empty(is_login()) && !is_weixin()){
			$appid     = C('wx_login.appid');
            $appsecret = C('wx_login.appsecret');
            $auth = new WechatAuth($appid, $appsecret);
            $_REQUEST['gid'] = $_REQUEST['gid']?$_REQUEST['gid']:$_REQUEST['game_id'];

            if(session('for_third')==C(PC_SET_DOMAIM)){
				$state=$_SERVER['HTTP_HOST'];
				$redirect_uri = "http://".session('for_third')."/mobile.php/ThirdLogin/wechat_login/gid/".$_REQUEST['gid']."/pid/".$_REQUEST['pid'];
			}else{
				$redirect_uri = "http://".$_SERVER['HTTP_HOST']."/mobile.php/ThirdLogin/wechat_login/gid/".$_REQUEST['gid']."/pid/".$_REQUEST['pid'];
			}
            redirect($auth->getQrconnectURL($redirect_uri,$state));
		}
	}

	public function pagelists($model,$p){
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据	
 
		if (empty($model['where']))			
			$map = " ";
		else $map = $model['where'];
		
		if (empty($model['order']))			
			$mapo = " id DESC ";
		else $mapo = $model['order'];
        //获取模型信息
        $model = M('Model')->getByName($model["model"]);
        $model || $this->error('模型不存在！');
        $row    = empty($model['list_row']) ? 10 : $model['list_row'];
        //读取模型数据列表
        $name = parse_name(get_table_name($model['id']), true);
        $data = M($name)
        /* 查询指定字段，不指定则查询所有字段 */
        ->field(true)
        // 查询条件
        ->where($map)
        /* 默认通过id逆序排列 */
        ->order($mapo)
        /* 数据分页 */
        ->page($page, $row)
        /* 执行查询 */
        ->select();
        /* 查询记录总数 */
        $count = M($name)->where($map)->count();
        //分页
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $this->assign('_page', $page->show());
        }
		
        $this->assign('model', $model);
        $this->assign('plist_data', $data);
        $this->meta_title = $model['title'].'列表';
	}
	
	public function plists($model,$p){
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据	
		$map=array();
		$map[] = " 1 ";
		$tablename = C('DB_PREFIX').strtolower($model["model"]);
		$m = M($name)->query("SHOW COLUMNS FROM ".$tablename);
		foreach($m as $n) {
			$fields[]=$tablename.'.'.$n['Field'];
		}
		// 条件搜索
        foreach($_REQUEST as $name=>$val){
            if(in_array($tablename.'.'.$name,$fields)){
                $map[$tablename.'.'.$name]	=	$val;
            }
        }

		if (empty($model['order']))			
			$mapo = $tablename.".id DESC ";
		else $mapo = $tablename.'.'.$model['order'];
		if (empty($model['join']))			
			$mapj = null;
		else {
			$mapj = $model['join'];
			$mapd=$model['direction'];
		}
		if (!empty($model['field'])) {
			$f = explode(',',$model['field']);
			foreach($f as $i) {
				$fields[]=$i;
			}
		}
        //获取模型信息
        $model = M('Model')->getByName($model["model"]);
        $model || $this->error('模型不存在！');
        $row    = empty($model['list_row']) ? 10 : $model['list_row'];
        //读取模型数据列表
        $name = parse_name(get_table_name($model['id']), true);
		if (empty($mapj)) {
			$data = M($name)
			/* 查询指定字段，不指定则查询所有字段 */
			->field(true)
			// 查询条件
			->where($map)
			/* 默认通过id逆序排列 */
			->order($mapo)
			/* 数据分页 */
			->page($page, $row)
			/* 执行查询 */
			->select();
		
		} else {
			$data = M($name)
			->field($fields)
			->join($mapj,$mapd)
			->where($map)
			->order($mapo)
			->page($page, $row)
			->select();
		}
			/* 查询记录总数 */
        $count = M($name)->where($map)->count();
        //分页
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $this->assign('_page', $page->show());
        }
		
        $this->assign('model', $model);
        $this->assign('plist_data', $data);
        $this->meta_title = $model['title'].'列表';
	}
	
	public function getlists($model) {
		if (isset($model['join'])) {
			$join = $model['join'];
			$joinnum = isset($model['joinnum'])? $model['joinnum'] : 'INNER';
		} else {
			$join=$joinnum="";
		}
		if (isset($model['join1'])) {
			$join1 = $model['join1'];
			$joinnum1 = isset($model['joinnum1'])? $model['joinnum1'] : 'INNER';
		} else {
			$join1=$joinnum1="";
		}
		$m = substr($model['model'],0,1);
		$field = isset($model['field'])?$model['field']:true;
		$table = isset($model['table'])?$model['table']:"__".strtoupper($model['model'])."__ as ".strtolower($m)." ";
		$order = isset($model['order'])?$model['order']:" ".strtolower($m).".id DESC ";
		$mo = D($model['model']);
		$list = $mo->field($field)->table($table)
		->join($join,$joinnum)
		->join($join1,$joinnum1)
		->where($model['where'])
		->order($order)->limit($model['limit'])
		->page($model['page'])->select();
		$count = $mo->table($table)->join($join,$joinnum)
		->join($join1,$joinnum1)->where($model['where'])->count();
		$totalpage = intval(($count-1)/$model['limit']+1);	
		return array('list'=>$list,'total'=>$totalpage);
	}
	
	public function getlist($model) {
		if (isset($model['join'])) {
			$join = $model['join'];
			$joinnum = isset($model['joinnum'])? $model['joinnum'] : 'INNER';
		} else {
			$join=$joinnum="";
		}
		if (isset($model['join1'])) {
			$join1 = $model['join1'];
			$joinnum1 = isset($model['joinnum1'])? $model['joinnum1'] : 'INNER';
		} else {
			$join1=$joinnum1="";
		}
		$m = substr($model['model'],0,1);
		$field = isset($model['field'])?$model['field']:true;
		$table = isset($model['table'])?$model['table']:"__".strtoupper($model['model'])."__ as ".strtolower($m)." ";
		$order = isset($model['order'])?$model['order']:" ".strtolower($m).".id DESC ";
		$mo = D($model['model']);
		$list = $mo->field($field)->table($table)
		->join($join,$joinnum)
		->join($join1,$joinnum1)
		->where($model['where'])
		->order($order)->limit($model['limit'])
		->page($model['page'])->select();
		$count = $mo->table($table)->join($join,$joinnum)
		->join($join1,$joinnum1)->where($model['where'])->count();
		return array('list'=>$list,'count'=>$count);
	}
    
    
    public function showgame($model,$map){
        $data=M($model['model'],'tab_')
            ->field($model['field'])
            ->where($map)
            ->order($model['order'])
            ->join($model['join'],'left')
            ->join($model['join2'],'left')
            ->limit($model['limit'])
            ->group('tab_game.id')
            ->select();
        return $data;
    }
    public function downQrcode($url,$level=3,$size=4){
        Vendor('phpqrcode.phpqrcode');
        $errorCorrectionLevel =intval($level) ;//容错级别
        $matrixPointSize = intval($size);//生成图片大小
        //生成二维码图片
        $url = "http://".$_SERVER['HTTP_HOST'].base64_decode(base64_decode($url));
        $object = new \QRcode();
        ob_clean();
        echo $object->png($url, false, $errorCorrectionLevel, $matrixPointSize, 2);
    }
    public function downapp()
    {
        if (get_device_type() == "ios") {
                $iosapp = M("App", "tab_");
                $map['app_version'] = 0;
                $data = $iosapp->where($map)->find();
                if(null==$data){
                    $this->error('暂无苹果app下载~');exit;
                }else{
                    $file = "https://" . $_SERVER['HTTP_HOST'] . "/Uploads/AppPlist/ios-app.plist";
                }
                    $file = "itms-services://?action=download-manifest&url=$file";
        } else {
                $iosapp = M("App", "tab_");
                $map['app_version'] = 1;
                $data = $iosapp->where($map)->find();
                if(null==$data){
                    $this->error('暂无安卓app下载~');exit;
                }else{
                    $file = "https://" . $_SERVER['HTTP_HOST'] . "/Uploads/App/".$data['file_name'];
                }
        }
        Header("Location:$file");//大文件下载
    }

    /*
    *平台币充值记录
    */
    protected function add_deposit($data)
    {
        $deposit_data = $this->deposit_param($data);
        $deposit = M("deposit", "tab_")->add($deposit_data);
        return $deposit;
    }

    /**
     *平台币充值记录表 参数
     */
    private function deposit_param($param = array())
    {
        $user_entity = get_user_entity($param['user_id']);
        $data_deposit['order_number'] = $param["order_number"];
        $data_deposit['pay_order_number'] = $param["pay_order_number"];
        $data_deposit['user_id'] = $param["user_id"];
        $data_deposit['user_account'] = $user_entity["account"];
        $data_deposit['user_nickname'] = $user_entity["nickname"];
        $data_deposit['promote_id'] = $param["promote_id"];
        $data_deposit['promote_account'] = get_promote_name($param['promote_id']);
        $data_deposit['pay_amount'] = $param["pay_amount"];
        $data_deposit['reality_amount'] = $param["real_pay_amount"];
        $data_deposit['pay_status'] = 0;
        $data_deposit['pay_source'] = 2;
        $data_deposit['pay_way'] = $param["pay_way"];
        $data_deposit['pay_ip'] = $param["spend_ip"];
        $data_deposit['create_time'] = NOW_TIME;
        return $data_deposit;
    }

    /**
     * 增加绑币充值记录
     * @param $param
     * author: xmy 280564871@qq.com
     */
    protected function add_bind_recharge($param){
        $user = get_user_entity($param['user_id']);
        $data['order_number']     = "";
        $data['pay_order_number'] = $param['pay_order_number'];
        $data['game_id']          = $param['game_id'];
        $data['game_appid']       = $param['game_appid'];
        $data['game_name']        = $param['game_name'];
        $data['promote_id']       = $param['promote_id'];
        $data['promote_account']  = $user['promote_account'];
        $data['user_id']          = $param['user_id'];
        $data['user_account']     = $user['account'];
        $data['user_nickname']    = $user['nickname'];
        $data['pay_type']         = $param['pay_type'];
        $data['amount']           = $param['pay_amount'];
        $data['real_amount']      = $param['real_pay_amount'];
        $data['pay_status']       = 0;
        $data['pay_way']          = $param['pay_way'];
        $data['create_time']      = time();
        $data['recharge_ip']      = $param["spend_ip"];
        $data['zhekou']           = $param['discount'];
        return M("bind_recharge","tab_")->add($data);
    }
    /*
     * 用户访问统计
     */
    public function access_log(){
        access_log(3);
    }

}