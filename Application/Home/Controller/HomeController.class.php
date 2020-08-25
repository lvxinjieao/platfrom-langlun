<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace Home\Controller;
use Think\Controller;

/**
 * 前台公共控制器
 * 为防止多分组Controller名称冲突，公共Controller名称统一使用分组名称
 */
class HomeController extends Controller {

	/* 空操作，用于输出404页面 */
	public function _empty(){
		$this->redirect('Index/index');
	}


    protected function _initialize(){
        /* 读取站点配置 */
        $config = api('Config/lists');
        C($config); //添加配置

        if(!C('WEB_SITE_CLOSE')){
            $this->error('站点已经关闭，请稍后访问~');
        }
    }
    public function rule()
    {
        $this->display();
    }
    public function simpleDecode($data){
        return simple_decode($data);
    }

    public function promitionofregestion(){
        $map['tab_game.id']=$_GET['gid'];
        $map['ta.promote_id']=$_GET['pid'];
        $data=M('game','tab_')->field('tab_game.id,tab_game.icon,tab_game.screenshot,tab_game.game_name,ta.register_url')->where($map)->join('tab_apply ta ON ta.game_id=tab_game.id')->find();
        $this->assign('data',$data);
        $this->display();

    }

    public function promitionofregestion1() {
        $map['relation_game_id']=$_GET['gid'];
        $map['promote_id']=$_GET['pid'];
        $data=M('game','tab_')->field('tab_game.id,tab_game.sdk_version,icon,screenshot,relation_game_id,relation_game_name,enable_status,ta.promote_id,game_status,tab_game.dow_status')->where($map)->join('tab_apply ta ON ta.game_id=tab_game.id')->select();
        if(empty($data)){
            $res=M('game','tab_')->field('relation_game_id')->find($_GET['gid']);
            if($res){
                $data=M('game','tab_')->field('tab_game.id,tab_game.sdk_version,icon,screenshot,relation_game_id,relation_game_name,enable_status,ta.promote_id,game_status,tab_game.dow_status')->where(['relation_game_id'=>$res['relation_game_id'],'promote_id'=>$_GET['pid']])->join('tab_apply ta ON ta.game_id=tab_game.id')->select();
            }
        }
        $this->assign('data',$data);
        $this->display();
    }

    public function appofregestion($pid=0,$gid=0){
        $map['p.promote_id'] = $pid;
        $map['tab_app.id'] = $gid;
        $data = M('app','tab_')
            ->field('tab_app.*,p.status as apply_status,p.app_name,p.dow_url,p.promote_id,p.apply_time,p.icon,p.back_img,p.version as user_version,p.plist_url as pplist_url')
            ->join("left join tab_app_apply p on p.app_id = tab_app.id")
            ->where($map)
            ->find();
        if(!empty($data['user_version'])){
            $data['version'] = $data['user_version'];
        }
        if($data['app_version'] == 0){
            if(is_mobile_request()){
                $url = "https://" . $_SERVER['HTTP_HOST'] . substr($data['pplist_url'],1);
                $data['url']  = "itms-services://?action=download-manifest&url=$url";
            }else{
                $data['url'] = "http://" . $_SERVER['HTTP_HOST'].substr($data['pplist_url'],1);
            }
        }else{
            $data['url'] = "http://" . $_SERVER['HTTP_HOST'].substr($data['dow_url'],1);
        }
        $this->assign('data',$data);
        $this->meta_title = "推广注册";
        $this->display();
    }
    //下载APP
    public function app_down($type=0,$pid=0)
    {
        if(is_mobile_request()){
            if (get_device_type() == "ios"){
                $type = 0;
            }else{
                $type = 1;
            }
        }
        $app_apply = M("App_apply", "tab_");
        $map['app_version'] = $type;
        $map['promote_id'] = $pid;
        $map['name'] = array('like','%联运平台%');
        $data = $app_apply->where($map)->find();
        if(empty($data) && $type == 1)$this->redirect("Home/down_error",array('message'=>"暂无安卓app下载~"));
        if(is_weixin() && $type == 1)$this->redirect("Home/down_error",array('message'=>"请使用安卓浏览器下载~"));
        if(empty($data) && $type == 0)$this->redirect("Home/down_error",array('message'=>"暂无苹果app下载~"));
        if($type == 0){
            if(is_mobile_request()){
                $url = "https://" . $_SERVER['HTTP_HOST'] . substr($data['plist_url'],1);
                $file = "itms-services://?action=download-manifest&url=$url";
                Header("Location:$file");//大文件下载
                exit;
            }else{
                $file = "http://" . $_SERVER['HTTP_HOST'].substr($data['plist_url'],1);
                $this->down($file);
                exit;
            }
        }else{
            $file = "http://" . $_SERVER['HTTP_HOST'].substr($data['dow_url'],1);
            $this->down($file);
            exit;
        }
    }

    public  function down_error($message=''){
        $this->assign("message",$message);
        $this->display();
    }
    //下载文件
    public function down($file, $isLarge = false, $rename = NULL)
    {
        if(headers_sent())return false;
        if(!$file) {
            $this->error('文件不存在哦 亲!');
            //exit('Error 404:The file not found!');
        }
        if($rename==NULL){
            if(strpos($file, '/')===false && strpos($file, '\\')===false)
                $filename = $file;
            else{
                $filename = basename($file);
            }
        }else{
            $filename = $rename;
        }

        header('Content-Description: File Transfer');
        header("Content-Type: application/force-download;");
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: binary");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: '.filesize($file));//$_SERVER['DOCUMENT_ROOT'].
        header("Pragma: no-cache"); //不缓存页面
        ob_clean();
        flush();
        if($isLarge)
            self::readfileChunked($file);
        else
            readfile($file);
    }

    public function logout(){
	    session('promote_auth',null);
	    session('promote_auth_sign',null);
	    $this->redirect('Index/index');
    }
}
