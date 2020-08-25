<?php
namespace Media\Controller;
use Think\Controller;
use Com\Wechat;
use Com\WechatAuth;
use Admin\Model\PointTypeModel;
use Common\Model\ServerModel;
use Common\Model\MsgModel;
use Common\Api\TjApi;
/**
* 首页
*/
class BaseController extends Controller {
	protected function _initialize(){
        if(session('tuiguangurl')){
        	session('tuiguangurl',null);
        }else{
    		$this->wechat_login();
        }
    }
	public function __construct() {
		$_SESSION['media']="media.php";
		$serverhost=$_SERVER['HTTP_HOST'];
        $headerurl = 'http://'.$serverhost.'/media.php';
        if(is_mobile_request()){
            $http = 'http';
            if(is_https()){
                $http = 'https';
            }
            if($_SERVER['REQUEST_URI']!='/'){
                $url = str_replace('media.php','mobile.php',$_SERVER['REQUEST_URI']);
                $headerurl = $http.'://'.$_SERVER["HTTP_HOST"].$url;
            }
            Header("Location: $headerurl"); exit;
        }
        $config = api('Config/lists');
        C($config); //添加配置
//		$httpefrer = strtolower($_SERVER['HTTP_REFERER']);
//		if(is_mobile_request()&&strtolower(ACTION_NAME)!='mall_sign_mobile'&&strtolower(ACTION_NAME)!='detail'&&strpos($httpefrer,'game/open_game')===false&&ACTION_NAME!='download'&&ACTION_NAME!='open_game'&&ACTION_NAME!='callback'&&ACTION_NAME!='wechat_login'&&ACTION_NAME!='user_argeement'&&ACTION_NAME!='user_sign_in'){
//			redirect(U('index/download'));
//		}
		parent::__construct();
        $pc_show = C('PC_SHOW');
        if($pc_show!=1){
            $this->display('Public/404');exit;
        }
		if(!C('WEB_SITE_CLOSE')){
			$this->error('站点已经关闭，请稍后访问~');
		}
		$this->assign("headpic",session("wechat_token.headimgurl"));
		if(session('union_host')){
			$union_set=json_decode(session('union_host')['union_set'],true);
			$this->assign('union_set',$union_set);
		}
		$logindiv = $this->fetch('Public:loginsdk');//登录sdk

		//实名认证
		// $name_auth = M('Tips','tab_')->where(['obj'=>1,'status'=>1,'end_time'=>['gt',time()]])->find();
		$name_auth = C('h5_age');
		if($name_auth['status']!=1){
			$name_auth = [];
		}
		$zhuceauth = C('REAL_NAME_REGISTER');
		$this->assign('open_name_auth',$zhuceauth);
		$this->assign('open_auth_tip',empty($name_auth)?0:1);
		$this->assign('name_auth_tip',$name_auth['tip']);
        $this->assign('logdiv',$logindiv);
        if(I('token')){
            $this->app_write_session(I('token'));//app使用aa
        }
        $userdata = M('User','tab_')->field('lock_status,real_name,idcard')->find(is_login());
    	if($userdata['lock_status']!=1){
    		session('user_auth', null);
            session('user_auth_sign', null);
            session("wechat_token", null);
    	}
        if(strtolower(ACTION_NAME)=='open_game'&&!empty($name_auth)){
        	if($userdata['real_name']==''&&$userdata['idcard']==''&& strtotime($name_auth['end_time'])>time()){
        	    session('stop_play',0);
        		$this->assign('user_no_auth',1);
        	}elseif($userdata['real_name']==''&&$userdata['idcard']==''&&strtotime($name_auth['end_time'])<time()){
        	    session('stop_play',1);
        		$this->assign('user_no_auth',1);
        		$this->assign('stop_play',1);
        	}else{
        		session('stop_play',0);
        	}
        }else{
        	session('stop_play',0);
        }
        //用户消息
    	$pointtype = new PointTypeModel();
        if(is_login()>0){
        	$servermodel = new ServerModel();
        	$send_notice = $servermodel->send_server_notice(is_login());
        	
        	$model = new MsgModel();
	        $map['user_id'] = is_login();
	        $map['status'] = array('gt',0);
	        $map['tab_msg.type'] = ['in','3,4'];
	        $map1['user_id'] = is_login();
	        $map1['status'] = 2;
	        $map1['tab_msg.type'] = ['in','3,4'];
	        $msg = $model->getMsglist($map,'create_time desc,id desc',1,100000);
	        $this->assign('msg',$msg);
        }
        $newmsg = M('msg','tab_')->where(['user_id'=>is_login(),'status'=>2])->find();
    	if(!empty($newmsg)){
    		$this->assign('newmsg',1);
    	}
    	$list = $pointtype->where(['key'=>'bind_phone'])->find();
    	$bindphoneadd = $list['point']>0?$list['point']:0;
        $this->assign('bindphoneadd',$bindphoneadd);
        //登录按钮
        $thirloginstr = "qq_login,wx_login";
        $logbutmap['name'] = array('in',$thirloginstr);
        $tool = M('tool',"tab_")->field('name,status')->where($logbutmap)->select();
        foreach ($tool as $key => $val) {
            $this->assign($tool[$key]['name'],$val['status']);
        }
	}

	public function savedesk($url,$title=''){

		$Shortcut = "[InternetShortcut] 

		URL=$url

		IconIndex=0 

		HotKey=1613 

		IDList= 

		[{000214A0-0000-0000-C000-000000000046}] 

		Prop3=19,2"; 

		header("Content-Type: application/octet-stream"); 

		header("Content-Disposition: attachment; filename=$title.url"); 

		echo $Shortcut; 

	}

	public function is_login(){

		$user = session('user_auth');

	    if (empty($user)) {

	        return 0;

	    } else {

	        return session('user_auth_sign') == data_auth_sign($user) ? $user['user_id'] : 0;

	    }

	}



	/** * 第三方微信公众号登录 * */

	public function wechat_login($state=0){

		if(empty(session("user_auth.user_id")) && is_weixin()){

			$appid = C('wechat.appid');

			$appsecret = C('wechat.appsecret');

			$auth  = new WechatAuth($appid, $appsecret);

			$redirect_uri = "http://".$_SERVER['HTTP_HOST']."/media.php/ThirdLogin/wechat_login/gid/".$_REQUEST['game_id']."/pid/".$_REQUEST['pid'];
            redirect($auth->getRequestCodeURL($redirect_uri,$state));

		}

	}



	/** * 第三方微信扫码登录 * */

	public function wechat_qrcode_login($state=1,$game_id=0,$pid=0){

		if(empty(session("user_auth.user_id")) && !is_weixin()){

			$appid     = C('wx_login.appid');

            $appsecret = C('wx_login.appsecret');

            $auth = new WechatAuth($appid, $appsecret);

           	$redirect_uri = "http://".$_SERVER['HTTP_HOST']."/media.php/ThirdLogin/wechat_login/gid/".$game_id."/pid/".$pid;

            redirect($auth->getQrconnectURL($redirect_uri,$state));

		}

	}





	

    public function lists($model,$p){

        $page = intval($p);

        $page = $page ? $page : 1; //默认显示第一页数据

		$fields = array($model['search_key'],$model['search_type']);

        //获取模型信息

        $model = M('Model')->getByName($model["model"]);

        $model || $this->error('模型不存在！');

        // 关键字搜索

        $map	=	array();

        $key	=	$model['search_key']?$model['search_key']:'title';

        if(isset($_REQUEST[$key])){

            $map[$key]	=	array('like','%'.$_GET[$key].'%');

            unset($_REQUEST[$key]);

        }

        // 条件搜索

        foreach($_REQUEST as $name=>$val){

            if(in_array($name,$fields)){

                $map[$name]	=	$val;

            }

        }

        $row  = empty($model['list_row']) ? 10 : $model['list_row'];

        //读取模型数据列表

        in_array('id', $fields) || array_push($fields, 'id');

        $name = parse_name(get_table_name($model['id']), true);

        $data = M($name)

        /* 查询指定字段，不指定则查询所有字段 */

        ->field(true)

        // 查询条件

        ->where($map)

        /* 默认通过id逆序排列 */

        ->order('id DESC')

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

        $this->assign('list_data', $data);

        $this->meta_title = $model['title'].'列表';

	}

	

	/**

	* search 

	* lwx

	*/

	public function search($model,$p){

        $page = intval($p);

        $page = $page ? $page : 1; //默认显示第一页数据

        // 关键字搜索

        $map	=	array();

		$sk = $model['search_key']?$model['search_key']:'title';

		$sn = explode(",",$model['search_isnum']);

        $fields = $key	= explode(",",$sk);	

        if(isset($model['search_value'])){

			foreach($key as $k) {

				if (in_array($k,$sn)) {

					$str = $model['search_value'];

					eval("\$result = get_".$k."_code(\"".$str."\",'like');");

					if ($result)

						$map[$k] = "$result";

				} else 

					$map[$k]	=	array('like','%'.$model['search_value'].'%');

			}

			$map['_logic']=$model['search_logic'];

			unset($model['search_value']);

        }

		

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

		if (isset($model['where'])) {

			$where['_complex']=$map;

			$where['_string']=$model['where'];

		} else {

			$where['_complex']=$map;

		}

        //获取模型信息

        $model = D('Model')->getByName($model["model"]);

        $model || $this->error('模型不存在！');

        $row = empty($model['list_row']) ? 10 : $model['list_row'];

        //读取模型数据列表

        in_array('id', $fields) || array_push($fields, 'id');

        $name = parse_name(get_table_name($model['id']), true);

        $data = D($name)->table($table)

        /* 查询指定字段，不指定则查询所有字段 */

        ->field($field)

		->join($join,$joinnum)

		->join($join1,$joinnum1)

        // 查询条件

        ->where($where)

        /* 默认通过id逆序排列 */

        ->order($order)

        /* 数据分页 */

        /* ->page($page, $row) */

        /* 执行查询 */

        ->select();

        /* 查询记录总数 */

        $count = D($name)->table($table)->join($join,$joinnum)

		->join($join1,$joinnum1)

		->where($where)->count();

        //分页

        if($count > $row){

            $page = new \Think\Page($count, $row);

            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');

            $this->assign('_page', $page->show());

        }

		

        $this->assign('model', $model);        

        $this->meta_title = $model['title'].'列表';

		if ($count) {

			$total = intval(($count-1)/$row+1);

			return array('list'=>$data,'total'=>$total);

		} else 

			return false;

		

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

	

	public function getdata($model) {

		if(isset($model['field'])) $field = $model['field'];

		else $field = true;

		$mo = D($model['model']);

		$list = $mo->field($field) ->join($model['joins'])->where($model['where'])

		->order($model['order'])->limit($model['limit'])

		->page($model['page'])->select();

		return $list;

	}

	

	public function showlist($model,$num=10) {

		if ($num==-1) $num="";

		if (isset($model['join'])) {

			$join = $model['join'];

			$joinnum = isset($model['joinnum'])? $model['joinnum'] : 'INNER';

		} else

			$join=$joinnum="";

		$m = substr($model['model'],0,1);

		$field = isset($model['field'])?$model['field']:true;

		$table = isset($model['table'])?$model['table']:"__".strtoupper($model['model'])."__ as ".strtolower($m)." ";

		$mo = D($model['model']);

		$list = $mo->field($field)->table($table)->join($join,$joinnum)->where($model['where'])->order($model['order'])->limit($num)->select();

		return $list;

	}

	

	public function detail($model) {

		if (isset($model['djoin'])) {

			$join = $model['djoin'];

			$joinnum = isset($model['djoinnum'])? $model['djoinnum'] : 'INNER';

		} else

			$join=$joinnum="";

		$m = substr($model['model'],0,1);

		$field = isset($model['dfield'])?$model['dfield']:true;

		$table = isset($model['dtable'])?$model['dtable']:"__".strtoupper($model['model'])."__ as ".strtolower($m)." ";

		$mo = D($model['model']);

		$data = $mo->field($field)->table($table)->join($join,$joinnum)->where($model['dwhere'])->order($model['dorder'])->find();

		return $data;

	}

    

    public function pdetail($model) {

		if (isset($model['join'])) {

			$join = $model['join'];

			$joinnum = isset($model['joinnum'])? $model['joinnum'] : 'INNER';

		} else{

			$join=$joinnum="";

		}

		$m = substr($model['model'],0,1);

		$field = isset($model['field'])?$model['field']:true;

		$table = isset($model['table'])?$model['table']:"__".strtoupper($model['model'])."__ as ".strtolower($m)." ";

		$mo = D($model['model']);

		$data = $mo->field($field)->table($table)->join($join,$joinnum)->where($model['where'])->order($model['order'])->find();

		return $data;

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

        foreach ($data as $key => $val){
            $data[$key]['play_url']=get_http_url().$_SERVER['HTTP_HOST']."/mobile.php?s=Game/open_game/game_id/".$val['id'];
        }
        return $data;

    }

    public function downQrcode($url,$level=3,$size=4){

        Vendor('phpqrcode.phpqrcode');

        $errorCorrectionLevel =intval($level) ;//容错级别

        $matrixPointSize = intval($size);//生成图片大小

        //生成二维码图片

        //echo $_SERVER['REQUEST_URI'];

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
                $map['name'] = array('like','%联运平台%');
                $data = $iosapp->where($map)->find();

                if(null==$data){

                    $this->error('暂无苹果app下载~');exit;

                }else{

                    $file = "https://" . $_SERVER['HTTP_HOST'] . substr($data['plist_url'],1);

                }

                    $file = "itms-services://?action=download-manifest&url=$file";

        } else {

                $iosapp = M("App", "tab_");

                $map['app_version'] = 1;
                $map['name'] = array('like','%联运平台%');
                $data = $iosapp->where($map)->find();

                if(null==$data){

                    $this->error('暂无安卓app下载~');exit;

                }else{

                    $file = "http://" . $_SERVER['HTTP_HOST'] . "/Uploads/App/".$data['file_name'];

                }

        }
        Header("Location:$file");//大文件下载

    }
    protected function set_page($count,$row,$name="_page"){
    	if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $page->setConfig('prev','上一页');
            $page->setConfig('next','下一页');
           	$this->assign($name, $page->show());
        }
    }
    public function user_message(){
        $model = new MsgModel();
        
        $map1['user_id'] = is_login();
        $map1['status'] = 2;
        $res=$model->optionMsg($map1);
        if($res){
        	$this->ajaxReturn(['status'=>1]);
        }else{
        	$this->ajaxReturn(['status'=>0]);
        }
    }

    /*
     * 用户访问统计
     */
    public function access_log(){
        access_log(1);
    }

    //首页查询
    function pc_search_game($key){
    	header("Content-type: text/html; charset=utf-8");
    	if($key==''){

    	}else{
	    	$gammap['relation_game_name'] = ['like','%'.$key.'%'];
	    	$game = M('Game','tab_')->field('relation_game_id as id,relation_game_name as game_name,sdk_version')->where($gammap)->group('relation_game_id')->select();
	    	foreach ($game as $key => &$value) {
	    		if($value['sdk_version']!=3){
	    			$value['game_name'] = $value['game_name']."(手游)";
                    $value['play_detail_url'] = U('Game/detail',['game_id'=>$value['id']]);
	    		}else{
	    			$value['game_name'] = $value['game_name']."(H5)";
                    $value['play_detail_url'] = U('Game/detail',['game_id'=>$value['id'],'ish5'=>1]);
	    		}

	    	}
    	}
    	$json['status'] = empty($game)?0:1;
    	$json['data'] = $game;
    	$this->ajaxReturn($json);
    }

    //app写session

    public function app_write_session($token){

        $token = think_decrypt($token);

        if(empty($token)){

            $this->error("信息过期，请重新登录");

        }

        $info = json_decode($token,true);

        $user = get_user_entity($info['user_id'],false);

        if(empty($user)){$this->error("用户不存在");exit();}

        /* 记录登录SESSION和COOKIES */

        $auth = array(

            'user_id'   => $user['id'],

            'account'   => $user['account'],

            'nickname'  => $user['nickname'],

        );

        session('user_auth', $auth);

        session('user_auth_sign', data_auth_sign($auth));

    }
}