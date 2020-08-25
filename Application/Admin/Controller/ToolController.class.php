<?php

namespace Admin\Controller;
/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class ToolController extends ThinkController {
    
    /**
    *保存设置
    */
    public function saveTool($value='')
    {
        $name   = $_POST['name'];
        $config = I('config');
        $data   = array('config'=>json_encode($config),'template'=>$_POST['template'],'status'=>$_POST['status']);
        $map['name']=$name;
        if($_POST['status']==1&&$name=="weixin"){
            $map_['name']=array("in",'wei_xin,wei_xin_app,weixin_gf');
            M('tool','tab_')->where($map_)->setField('status','0');
        }
        if($_POST['status']==1&&$name=="wei_xin"||$name=="wei_xin_app"){
            $map_['name']=['in','weixin,weixin_gf'];
            M('tool','tab_')->where($map_)->setField('status','0');
        }
        if($_POST['status']==1&&$name=="weixin_gf"){
            $map_['name']=array("in",'wei_xin,wei_xin_app,weixin');
            M('tool','tab_')->where($map_)->setField('status','0');
        }
        $flag   = M('Tool','tab_')->where($map)->setField($data);
        
        if($flag !== false){
            action_log('save_tool','tool',UID,UID);
            $this->set_config($name,$config);
            $this->success('保存成功');
        }else{
            $this->error('保存失败');
        }

    }

    public function save($config){
        $name   = $_POST['name'];
        $a = $this->set_config($name,$config);
        if ($a){
            return true;
        }else{
            return false;
        }
    }

    /**
    *显示扩展设置信息
    */
    protected function BaseConfig($name='')
    {   
        $map['name'] = array('in',$name);
        $tool = M('tool',"tab_")->where($map)->select();
        if(empty($tool)){$this->error('没有此设置');}
        foreach ($tool as $key => $val) {
            $this->assign($tool[$key]['name'],json_decode($tool[$key]['config'],true));
            unset($tool[$key]['config']);
            $this->assign($tool[$key]['name']."_data",$tool[$key]);
        }
    }
    /**
    *短信设置
    */
    public function smsset($value='')
    {
        $this->BaseConfig("sms_set,alidayu,jiguang,alidayunew,alidayumsg");
        $this->meta_title = '短信设置';
        $this->display();
    }

    /**
     *防沉迷设置
     */
    public function age($value='')
    {
        $this->BaseConfig("age");
        $this->meta_title = '实名认证';
        $this->display();
    }

    /**
    *文件存储
    */
    public function storage($value='')
    {
        $str = "oss_storage,qiniu_storage,cos_storage,bos_storage,ks3_storage";
        $this->BaseConfig($str);
        $this->meta_title = '文件存储';
        $this->display();
    }

    /**
    *动态密保
    */
    public function cooperate($value='')
    {
        $str = "set_cooperate";
        $this->BaseConfig($str);
        $this->meta_title = '动态密保';
        $this->display();
    }

    /**
    *支付设置
    */
    public function payset($value='')
    {
        $str = "alipay,weixin,wei_xin,wei_xin_app,jubaobar,weixin_gf,jft,goldpig";
        $this->BaseConfig($str);
        $this->meta_title = '支付设置';
        $this->display();
    }

    /**
    *邮件设置
    */
    public function email($value='')
    {
        $str = "email_set";
        $this->BaseConfig($str);
        $this->meta_title = '邮件设置';
        $this->display();
    }

    /**
 *第三方登陆设置
 */
    public function qq_thirdparty($value='')
    {
        $str = "qq_login,wx_login";
        $this->BaseConfig($str);
        $this->meta_title = '第三方登录';
        $this->display();
    }

    /**
    *畅言设置
    */
    public function changyan($value=''){
        $str = "changyan";
        $this->BaseConfig($str);
        $this->meta_title = '畅言设置';
        $this->display();
    }
    /**
    *聚宝云
    */
    public function saveTool_jubaobar(){
        $name   = $_POST['name'];
        $config = I('config');
        $data   = array('config'=>json_encode($config),'template'=>$_POST['template'],'status'=>$_POST['status']);
        $map['name']=$name;  
        $flag   = M('Tool','tab_')->where($map)->setField($data);
        if($flag !== false){
            $this->set_config($name,$config);
            $this->update_xml($config['key']);
            action_log('save_jubaobar','jubao',UID,UID);
            $this->success('保存成功');
        }else{
            $this->error('保存失败');
        }
    }

    /**
    *修改 聚宝云 安全口令
    */
    private function update_xml($key=""){
        // $file = $_SERVER['DOCUMENT_ROOT'].'Application/Sdk/SecretKey/jubaopay/jubaopay.ini';
        $file = './Application/Sdk/SecretKey/jubaopay/jubaopay.ini';
        //创建DOMDocument的对象
        $dom = new \DOMDocument('1.0');
        //载入mainchart.xml文件
        $dom->load($file);
        $dom->getElementsByTagName('psw')->item(0)->nodeValue = $key;
        $dom->save($file);
    }

    /**
    *设置config
    */
    private function set_config($name="",$config=""){
        $config_file ="./Application/Common/Conf/pay_config.php";
        if(file_exists($config_file)){
            $configs=include $config_file;
        }else {
            $configs=array();
        }
        #定义一个数组
        $data = array();
        #给数组赋值
        $data[$name] = $config;
        $configs=array_merge($configs,$data);
        $result = file_put_contents($config_file, "<?php\treturn " . var_export($configs, true) . ";");
    }

    public function ios_game(){
            if($_GET['t']==1){
                $url="qq";
            }elseif($_GET['t']==2){
                $url="wx";
            }elseif($_GET['t']==3){
                $url="wb";
            }else{
                $url="bd";
            }
        $this->meta_title = '第三方登录';
        $this->assign('thirdparty',$url."_thirdparty");
        $this->assign('param_lists',$url."_param_lists");
        $this->display();
    }

    public function qq_param_lists(){
        if(isset($_GET['game_id'])){
            $map['game_id']=$_GET['game_id'];
        }
        $map['type']=1;
        $model = array(
            'm_name' => 'param',
            'map'    => $map,
            'fields'=>'id,game_id,openid,key,callback,status,create_time',
            'order'  =>'id desc',
            'title'  => "第三方登录",
            'template_list' =>'qq_param_lists',
        );
        $this->meta_title = '第三方登录';
        $user = A('User','Event');
        $user->user_join_($model,$_GET['p']);

    }

    public function wx_param_lists(){
        if(isset($_GET['game_id'])){
            $map['game_id']=$_GET['game_id'];
        }
        $map['type']=2;
        $model = array(
            'm_name' => 'param',
            'map'    => $map,
            'fields'=>'id,game_id,wx_appid,appsecret,callback,status,create_time',
            'order'  =>'id desc',
            'title'  => "第三方登录",
            'template_list' =>'wx_param_lists',
        );
        $this->meta_title = '第三方登录';
        $user = A('User','Event');
        $user->user_join_($model,$_GET['p']);

    }
    public function wb_param_lists(){
        if(isset($_GET['game_id'])){
            $map['game_id']=$_GET['game_id'];
        }
        $map['type']=3;
        $model = array(
            'm_name' => 'param',
            'map'    => $map,
            'field'=>'id,game_id,appkey,scope,callback,status,create_time',
            'order'  =>'id desc',
            'title'  => "第三方登录",
            'template_list' =>'wb_param_lists',
        );
        $this->meta_title = '第三方登录';
        $user = A('User','Event');
        $user->user_join_($model,$_GET['p']);

    }

    public function bd_param_lists(){
        if(isset($_GET['game_id'])){
            $map['game_id']=$_GET['game_id'];
        }
        $map['type']=4;
        $model = array(
            'm_name' => 'param',
            'map'    => $map,
            'field'=>'id,game_id,openid,key,callback,status,create_time',
            'order'  =>'id desc',
            'title'  => "第三方登录",
            'template_list' =>'bd_param_lists',
        );
        $this->meta_title = '第三方登录';
        $user = A('User','Event');
        $user->user_join_($model,$_GET['p']);

    }


    public function add_thirdparty(){
        if(IS_POST){
            if($_POST['type']==1){
                if(empty($_POST['openid'])){
                    $this->error('openid不能为空');exit;
                }
								if(empty($_POST['key'])){
                    $this->error('安全校验码不能为空');exit;
                }
                $url="qq_param_lists";
            }elseif($_POST['type']==2){
                if(empty($_POST['wx_appid'])){
                    $this->error('APPID不能为空');exit;
                }
								if(empty($_POST['appsecret'])){
                    $this->error('APPSECRET不能为空');exit;
                }
                $url="wx_param_lists";
            }elseif($_POST['type']==3){
                 if(empty($_POST['appkey'])||empty($_POST['scope'])){
                    $this->error('参数不能为空');exit;
                }
                $url="wb_param_lists";
            }else{
                 if(empty($_POST['clientid'])){
                    $this->error('参数不能为空');exit;
                }
                $url="bd_param_lists";
            }

            $find=M('param','tab_')->where(['game_id'=>$_POST['game_id'],'type'=>$_POST['type']])->find();
            if(null!==$find){
                $this->error('该游戏已经设置过参数');exit;
            }
            if($_POST['game_id']==0){
                $count=M('param','tab_')->where(['game_id'=>['gt',0],'type'=>$_POST['type']])->count();
                if($count>=1){
                     $this->error('如需设置全部游戏 请先将其他游戏数据删除');exit;
                }
            }
            $add=M('param','tab_')->add($_POST);
            if($add){
                $this->success('新增成功',U($url));
            }else{
                $this->error('新增失败',U($url));
            }
        }else{

						switch($_GET['type']) {
							case 1:$this->meta_title = '第三方登录';break;
							case 2:$this->meta_title = '第三方登录';break;

						}

            $this->display();
        }
    }


    public function edit_thirdparty($id){
        if(IS_POST){
         if($_POST['type']==1){
                if(empty($_POST['openid'])){
                    $this->error('openid不能为空');exit;
                }
								if(empty($_POST['key'])) {
									$this->error('安全校验码不能为空');exit;
								}

                $url="qq_param_lists";
            }elseif($_POST['type']==2){
                if(empty($_POST['wx_appid'])){
                    $this->error('APPID不能为空');exit;
                }
								if(empty($_POST['appsecret'])){
									$this->error('APPSECRET不能为空');exit;
								}
                $url="wx_param_lists";
            }elseif($_POST['type']==3){
                 if(empty($_POST['appkey'])||empty($_POST['scope'])){
                    $this->error('参数不能为空');exit;
                }
                $url="wb_param_lists";
            }else{
                 if(empty($_POST['clientid'])){
                    $this->error('参数不能为空');exit;
                }
                $url="bd_param_lists";
            }

            $add=M('param','tab_')->save($_POST);
            if($add){
                $this->success('修改成功',U($url));
            }else{
                $this->error('修改失败',U($url));
            }
        }else{
            $find=M('param','tab_')->where(['id'=>$id])->find();
            $this->assign('data',$find);
						switch($_GET['type']) {
							case 1:$this->meta_title = '第三方登录';break;
							case 2:$this->meta_title = '第三方登录';break;

						}
            $this->display();
        }
    }

      /**
     * 删除
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function del(){
        $id = array_unique((array)I('ids',0));

        if ( empty($id) ) {
            $this->error('请选择要操作的数据!');
        }
        $map = array('id' => array('in', $id) );
        if(M('param','tab_')->where($map)->delete()){
            $this->success('删除成功');
        } else {
            $this->error('删除失败！');
        }
    }

    public function changeStatus($field = null,$value=null)
    {
        $id = array_unique((array)I('id', 0));
        $id = is_array($id) ? implode(',', $id) : $id;
        if (empty($id)) {
            $this->error('请选择要操作的数据!');
        }
        $map['id'] = array('in', $id);
        $result = M('param','tab_')->where($map)->setField($field,$value);
        $msg = $value == 0?"关闭":"开启";
        if($result !== false){
            $this->success($msg.'成功');
        }else{
            $this->error($msg.'失败');
        }
    }

		/*
		 * 水印
		 */
		public function watermark() {

			$this->BaseConfig("watermark");

      $this->meta_title = '水印设置';

			$this->m_title = '水印设置';
				$url = 'Tool/watermark';
				$this->m_url = $url;
				$this->assign('commonset',M('Kuaijieicon')->where(['url'=>$url,'status'=>1])->find());


			$this->display();

		}

    /**
     *第三方登录设置
     */
    public function wx_thirdparty($value='')
    {
        $str = "wx_login";//weixin_login
        $this->BaseConfig($str);
        $this->meta_title = '第三方登录';
        $this->display();
    }


}
