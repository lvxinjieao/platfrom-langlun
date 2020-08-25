<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;

/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class AppController extends ThinkController {
	const model_name = 'App';

    public function lists(){
    	parent::lists(self::model_name,$_GET["p"]);
    }

    public function add($value='')
    {
    	if(IS_POST){
            if(empty($_POST['file_name'])){
                $this->error('未上传游戏原包');
            }
            $d = D('App')->find();
            $source = A('App','Event');
            if(empty($d)){
                $source->add_source();
            }
            else{
            $this->error('游戏已存在原包',U('App/lists'));
            }
    	}
    	else{
            $this->meta_title = '新增游戏原包';
    		$this->display();
    	}
    	
    }
    
    public function del($model = null, $ids=null){
        if ( empty($ids) ) {
            $this->error('请选择要操作的数据!');
        }
        $souce=M("App","tab_");
        $map['id']=array("in",$ids);
        $list=$souce->where($map)->select();
        foreach ($list as $key => $value) {
            @unlink($value['file_url']);
        }
        if($souce->where($map)->delete()){
            $this->success('删除成功');
        } else {
            $this->error('删除失败！');
        }
    }

    public function edit($id){
         $map['id']=$id;
         $map['app_version']=$_REQUEST['app_version'];
        if(IS_POST){
            if(empty($_POST['file_exit'])){
                $this->error('未上传原包');
            }
            if($_POST['app_version']==1){
                if(!preg_match("/^[1-9][0-9]*$/",$_POST['version'])){
                    $this->error('版本号需要是整数');
                }
            }
            $app_version=$_REQUEST['app_version'];
            $d = D('App')->where($map)->find();
            $source = A('App','Event');
            if(empty($d)){
                $source->add_source();
            }
            else{
                $source->update_source($d['id'],$d['file_name']);
            }
        }
        else{
            $d = M('App',"tab_")->where($map)->find();
            $this->meta_title = '编辑游戏原包';
            $this->assign("data",$d);
            $this->display();
        }
        
    }
    public function get_app_pack($source_url='',$sdk_version=1){

        //获取ios版本
        if($source_url==''||!file_exists($source_url)){
            $this->ajaxReturn('app上传地址无效，请刷新页面重新上传');
        }
        if($sdk_version==1){
            $newname = "and_app_package_for_version.apk";
            $versionpath = '';
            $newvpath = '';
        }else{
            $newname = "ios_app_package_for_version.ipa";
            $versionpath = 'Payload/H5App.app/Info.plist';
            $newvpath = 'ios_version';
        }
        $to = "./Uploads/App/" . $newname;
        $cp = copy($source_url, $to);
        if($cp==false){
            $newname = $source_url;
        }else{
            @unlink($source_url);
        }
        $jsonreturn['status'] = 0;
        $jsonreturn['data'] = '';
        if(!$cp){
            $jsonreturn['info'] = 'app复制失败，请刷新页面重新上传';
            $this->ajaxReturn($jsonreturn);
        }
        //安卓不自动获取版本号
        if($sdk_version==1){
            $jsonreturn['status'] = 1;
            $jsonreturn['info'] = '获取成功';
            $jsonreturn['newpath'] = $newname;
            $this->ajaxReturn($jsonreturn);
        }
        $zip = new \ZipArchive;
        $open = $zip->open($to, \ZipArchive::CREATE);
        if(!$open){
            $jsonreturn['info'] = 'app打开失败，请刷新页面重新上传';
            $this->ajaxReturn($jsonreturn);
        }else{
            for($i = 0; $i < $zip->numFiles; $i++) {
                $path = $zip->getNameIndex($i);
                if($path==$versionpath){
                    $jieya = $zip->extractTo('Uploads/App/'.$newvpath, array($zip->getNameIndex($i)));
                    $zip->close();
                    $content = file_get_contents('Uploads/App/'.$newvpath.'/'.$path);
                    if(empty($content)){
                        $jsonreturn['info'] = 'app获取信息失败，请确认app正确，并刷新重新上传';
                        $this->ajaxReturn($jsonreturn);
                    }else{
                        $xmla = "/<!-[\s\S]*?-->/si";//去除xml注释
                        $xml= preg_replace($xmla ,'', $content);
                        $xxml = preg_replace("/\s+/", " ", $xml);
                        $BundleVersion = '/CFBundleVersion<\/key> <string>(.*?)<\/string>/si';//BundleVersion
                        preg_match_all($BundleVersion , $xxml , $user_permission);
                        if(!$user_permission[1][0]){
                            $jsonreturn['info'] = 'app版本获取失败，请确认app正确，并刷新重新上传';
                            $this->ajaxReturn($jsonreturn);
                        }else{
                            $jsonreturn['status'] = 1;
                            $jsonreturn['info'] = '获取成功';
                            $jsonreturn['data'] = $user_permission[1][0];
                            $jsonreturn['newpath'] = $newname;
                            $this->ajaxReturn($jsonreturn);
                        }
                    }
                }
            }
            $jsonreturn['info'] = 'app获取信息失败，请确认app正确，并刷新重新上传';
            $this->ajaxReturn($jsonreturn);
        }
    }
}
