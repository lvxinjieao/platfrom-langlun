<?php
// +----------------------------------------------------------------------
// | 徐州梦创信息科技有限公司—专业的游戏运营，推广解决方案.
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.vlcms.com  All rights reserved.
// +----------------------------------------------------------------------
// | Author: kefu@vlcms.com QQ：97471547
// +----------------------------------------------------------------------
namespace Admin\Event;
use Think\Controller;
/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class SourceEvent extends Controller {

    public function add_source(){
        $model = D('Game_source');
        $plist = A("Plist");
        $data  = $_REQUEST;
        $data['file_size'] = round($data['file_size']/pow(1024,2),2)."MB";
        if($data['file_type']==2){
            if(preg_match("/.ipa/",$data['file_name'])){
                copy($data['file_url']."/".$data['file_name'],"./Uploads/Ios/original/".$data['file_name']);
                @unlink($data['file_url']."/".$data['file_name']);
                $data['file_url']="./Uploads/Ios/original";
            }
        }
        $data['file_url']  = $data['file_url']."/".$data['file_name'];
        $data['sdk_version'] = $data['file_type'];
        $data['op_id'] = UID;
        $data['version'] = $_POST['version'];
        $data['op_account'] = session("user_auth.username");
        $data['create_time'] = NOW_TIME;
        if($data['file_type']==2){
            $data['plist_url']="./Uploads/SourcePlist/".$data['game_id'].".plist";
            $plist->create_plist($data['game_id'],0,$data['bao_name'],$data['file_url']);
        }
        $res = $model->add($data);
        if($res){
            $this->update_game_size($data);
            $this->success('添加成功',U('GameSource/lists',array('type'=>$data['type'])));
        }
        else{
            $this->error('添加失败',U('GameSource/lists',array('type'=>$data['type'])));
        }
    }

    /**
    *修改游戏原包
    */
    public function update_source($id = null,$file_name,$from=""){
        $id || $this->error('id不能为空');
        $model = D('Game_source');
        $plist=A("Plist");
        $data =$_REQUEST;
        $url=$data['file_url'];
        $urll=$data['file_url2'];
          if($data['file_type']==2){
            if(preg_match("/.ipa/",$data['file_name'])){
                copy($data['file_url']."/".$data['file_name'],"./Uploads/Ios/original/".$data['file_name']);
                @unlink($data['file_url']."/".$data['file_name']);
                $data['file_url']="./Uploads/Ios/original";
                $url="./Uploads/Ios/original";

            }

         }
        $data['file_size'] = strpos($data['file_size'],'MB')?$data['file_size']:round($data['file_size']/pow(1024,2),2)."MB";
        $extend=substr($data['file_url'],strlen($str)-3,3);
        if($extend!="apk"&&$extend!="ipa"){
            $data['file_url']  = $data['file_url']."/".$data['file_name'];
        }
        $data['id'] = $id;
        $data['op_id'] = UID;
        $data['op_account'] = session("user_auth.username");
        $data['create_time'] = NOW_TIME;       
        if($data['file_type']==2){
        $data['plist_url']="./Uploads/SourcePlist/".$data['game_id'].".plist";
        $plist->create_plist($data['game_id'],0,$data['bao_name'],$data['file_url']);
        }
        $res = $model->save($data);
        if($res){
            @unlink($url."/".$file_name);
            @unlink($urll."/".str_replace('.ipa','.mobileprovision',$file_name));
            $this->update_game_size($data);
            //发送站内信
                $user_id = M('ucenter_member')->where('status=1')->field('id')->select();
                $content = '游戏：' . $data['game_name'] . ' 已更新，请尽快处理渠道包';
                D('Msg')->sendMsg($user_id, $content);
                if($from=="dev"){
                    $this->success('修改成功',U('Developers/source'));

                }else{
                    $this->success('修改成功',U('GameSource/lists'));

                }
        }
        else{
            if($from=="dev"){
                $this->error('修改失败',U('Developers/source'));

            }else{
                $this->error('修改失败',U('GameSource/lists'));

            }
        }

    }

    protected function update_game_size($param=null){
        $model = D('Game');
        $map['id'] = $param['game_id'];
        $data['game_size'] = $param['file_size'];
        // $data['version'] = $param['version'];
        if($param['file_type']==1){
            $data['and_dow_address'] = $param['file_url'];
            $ggame=$model->where(array('id'=>$map['id']))->find();
            if($ggame['sdk_version']!=-1){
                if($ggame['sdk_version']==2){
                    $data['sdk_version']=0;
                }
            }else{
                $data['sdk_version']=1;
            }
        }
        else{
            if($ggame['sdk_version']!=-1){
                if($ggame['sdk_version']==1){
                    $data['sdk_version']=0;
                }
            }else{
                $data['sdk_version']=2;
            }
            $data['ios_dow_address'] = $param['file_url'];
        }
        $model->where($map)->save($data);
    }

    /**
    *原包打包
    */
    protected function soure_pack($game_id=0,$file_url=""){
        $game_info = M("game","tab_")->find($game_id);
        $zip = new \ZipArchive;
        $res = $zip->open($file_url, \ZipArchive::CREATE);
        $data = array(
            "game_id"     => $game_info['id'],
            "game_name"   => $game_info['game_name'],
            "game_appid"  => $game_info['game_appid'],
            "promote_id"  => 0,
            "promote_account"=> "自然注册",
        );
        $zip->addFromString('META-INF/mch.properties', json_encode($data));
        $zip->close();
    }
   
}
