<?php

namespace Media\Controller;
use Think\Controller;

/**
 * 后台首页控制器222
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class DownController extends Controller {

    public function down_file($game_id = 0,$type=1,$sdk_version=''){
        $host_name = $_SERVER['HTTP_NAME'];
        // $promote_id = M('SiteApply','tab_')->where('site_url="$host_name"')->getField('promote_id');
        $system_type = 1;
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            $system_type = 2;
        }
        if(is_mobile_request()){
            $type = get_devices_type();
        }
        if(!empty($sdk_version))$type=$sdk_version;
        if(empty($promote_id)){
            $this->official_down_file($game_id,$type,$system_type);
        }else{
            $this->promote_down_file($game_id,$promote_id,$system_type,$type);
        }
    }


    /**
    *推广游戏下载
    *@param int $game_id     [游戏关联id]
    *@param int $promote_id  [推广员id]
    *@param int $system_type [系统环境] 1 win  2 iphone 或 iPad
    *@param int $sdk_version [sdk 类型] 1 安卓 2 苹果
    */
    public function promote_down_file($game_id=0,$promote_id=0,$system_type=1,$sdk_version=1){
        $applyModel = D('Apply');
        $map['status']        = 1;
        $map['enable_status'] = 1;
        $data = $applyModel
                ->field('game_id,tab_apply.game_name,promote_id,promote_account,relation_game_id,pack_url,plist_url,`status`,enable_status,tab_apply.sdk_version')
                ->join("tab_game ON tab_apply.game_id = tab_game.id AND promote_id = $promote_id AND relation_game_id = $game_id AND tab_apply.sdk_version = $sdk_version")
                ->where($map)
                ->find();
        if(empty($data)){
            $this->official_down_file($game_id,$sdk_version,$system_type);
        }else{
            $pack_url = $data['pack_url'];
            M('Game','tab_')->where('id='.$data['game_id'])->setInc('dow_num');
            $this->add_down_stat($data['game_id']);
            if(!empty($pack_url)){
                if(preg_match("/oss/", $pack_url)){
                    $url=str_replace('-internal', '', $pack_url);
                    echo "<script>window.location.href='$url';</script>";
                }elseif(preg_match("/clouddn/", $pack_url)){
                    $url = "http://".$pack_url;
                    redirect($url);
                }elseif(preg_match("/myqcloud/", $pack_url)){                
                    redirect($pack_url);
                }elseif(preg_match("/bcebos/", $pack_url)){
                    redirect($pack_url);
                }else{
                    $this->down($pack_url);
                }
            }else{
                $this->error('原包地址不存在');
            }
        }
    }
    
    /**
    *官方游戏下载
    *@param int $game_id [游戏关联id]
    *@param int $sdk_version [sdk 类型] 1 安卓 2 苹果
    *@param int $system_type [下载系统类型] 0 window 1 苹果手机或ipa
    */
    public function official_down_file($game_id=0,$sdk_version=1,$system_type=1){
        $model = M('Game','tab_');
        $map['tab_game.relation_game_id'] = $game_id;
        $data = $model
            ->field('tab_game.sdk_version,tab_game.game_name,tab_game.relation_game_name,tab_game.id as game_id,tab_game.and_dow_address,tab_game.ios_dow_address,tab_game.add_game_address,tab_game.ios_game_address,plist_url,tab_game.down_port')
            ->join("left join tab_game_source on tab_game.id = tab_game_source.game_id and tab_game_source.file_type =".$sdk_version)
            ->where($map)
            ->order('tab_game.sdk_version asc')
            ->select();
        $first_data = reset($data);
        $end_data   = end($data);
        if(empty($first_data) && $sdk_version == 1){
            $this->error('暂无安卓原包！');
        }

        if(empty($end_data) && $sdk_version == 2){
            $this->error('暂无苹果原包！');
        }
        if(substr($first_data['and_dow_address'], 0 , 1)=="."){
            $first_data['and_dow_address']="http://".$_SERVER['HTTP_HOST'].substr($first_data['and_dow_address'],'1',strlen($first_data['and_dow_address']));
        }
        if(substr($end_data['ios_dow_address'], 0 , 1)=="."){
            $end_data['ios_dow_address']="http://".$_SERVER['HTTP_HOST'].substr($end_data['ios_dow_address'],'1',strlen($end_data['ios_dow_address']));
        }

        switch ($sdk_version) {
            case 1:
                if(is_weixin())exit("请使用安卓浏览器下载");
                M('Game','tab_')->where('id='.$first_data['game_id'])->setInc('dow_num');
                $this->add_down_stat($first_data['game_id']);
                if($first_data['down_port'] == 1){
                    if(varify_url($first_data['and_dow_address'])){
                        Header("HTTP/1.1 303 See Other");
                        Header("Location: ".$first_data['and_dow_address']);
                    }else{
                        $this->error('原包地址错误！');
                    }
                }else {
                    if(varify_url($first_data['add_game_address'])){
                        Header("HTTP/1.1 303 See Other");
                        Header("Location: ".$first_data['add_game_address']);
                    }else{
                        $this->error('第三方下载地址错误！');
                    }
                }
                break;
            default:
                M('Game','tab_')->where('id='.$end_data['game_id'])->setInc('dow_num');
                $this->add_down_stat($end_data['game_id']);
                if($end_data['down_port'] == 1){
                    if(varify_url($end_data['ios_dow_address'])){
                        switch ($system_type) {
                            case 1:
                                $this->down($end_data['ios_dow_address'],$sdk_version);
                                break;
                            default:
                                Header("HTTP/1.1 303 See Other");
                                Header("Location: "."itms-services://?action=download-manifest&url=https://".$_SERVER['HTTP_HOST'].substr($end_data['plist_url'],1));
                                break;
                        }
                        
                    }else{
                        $this->error('原包地址错误！');
                    }
                }else {
                    if(varify_url($end_data['ios_game_address'])){
                        Header("HTTP/1.1 303 See Other");
                        //Header("Location: ".$end_data['ios_game_address']);
                        Header("Location: "."itms-services://?action=download-manifest&url=".$end_data['ios_game_address']);
                    }else{
                        $this->error('下载地址未设置！');
                    }
                }
                break;
        }
                
    }

    function access_url($url) {    
        if ($url=='') return false;    
        $fp = fopen($url, 'r') or exit('Open url faild!');    
        if($fp){  
        while(!feof($fp)) {    
            $file.=fgets($fp)."";  
        }  
        fclose($fp);    
        }  
        return $file;  
    }  

    private function down($file,$type,$rename = NULL)
    {
        ob_clean();
        if(headers_sent())return false;
        if(!$file&&$type==1) {
            $this->error('安卓文件不存在哦 亲!');
        }
        if(!$file&&$type==2) {
            $this->error('苹果文件不存在哦 亲!');
            //exit('Error 404:The file not found!');
        }
        downloadFile($file);
    }
    /** 获取header range信息
    * @param  int   $file_size 文件大小
    * @return Array
    */
    private function getRange($file_size){
        if(isset($_SERVER['HTTP_RANGE']) && !empty($_SERVER['HTTP_RANGE'])){
            $range = $_SERVER['HTTP_RANGE'];
            $range = preg_replace('/[\s|,].*/', '', $range);
            $range = explode('-', substr($range, 6));
            if(count($range)<2){
                $range[1] = $file_size;
            }
            $range = array_combine(array('start','end'), $range);
            if(empty($range['start'])){
                $range['start'] = 0;
            }
            if(empty($range['end'])){
                $range['end'] = $file_size;
            }
            return $range;
        }
        return null;
    }
    /**
    *游戏下载统计
    */
    public function add_down_stat($game_id=null){
        $model = M('down_stat','tab_');
        $data['promote_id'] = 0;
        $data['game_id'] = $game_id;
        $data['number'] = 1;
        $data['type'] = 0;
        $data['create_time'] = NOW_TIME;
        $model->add($data);
    }
}
