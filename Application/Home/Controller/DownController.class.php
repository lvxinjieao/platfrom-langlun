<?php

namespace Home\Controller;
use Think\Controller;

/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class DownController extends Controller {

    public function down_file($game_id=0,$promote_id=0){
        $applyModel = M('Apply','tab_');
        $map['status']        = 1;
        $map['enable_status'] = 1;
        $data = $applyModel
            ->field('game_id,tab_apply.game_name,promote_id,promote_account,relation_game_id,pack_url,plist_url,`status`,enable_status,tab_apply.sdk_version')
            ->join("tab_game ON tab_apply.game_id = tab_game.id AND promote_id = $promote_id AND game_id = $game_id ")
            ->where($map)
            ->find();
        $system_type = 1;
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            $system_type = 2;
        }
        $pack_url = $data['pack_url'];
        M('Game','tab_')->where('id='.$data['game_id'])->setInc('dow_num');
        switch ($data['sdk_version']) {
            case 1:
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
                        if (!file_exists($pack_url)){
                            $this->error('文件不存在哦 亲!');
                        }else{
                            redirect("http://".$_SERVER['HTTP_HOST'].ltrim($pack_url,'.'));
                        }
                    }
                }else{
                    $this->error('原包地址不存在');
                }
                break;
            default:
                switch ($system_type) {
                    case 1:
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
                        break;
                    default:
                        $plist_url = substr($data['plist_url'],'1',strlen($data['plist_url']));
                        Header("HTTP/1.1 303 See Other");
                        Header("Location: "."itms-services://?action=download-manifest&url="."https://".$_SERVER["HTTP_HOST"].$plist_url);
                        break;
                }
                break;
        }
    }

    public function media_down_file($game_id=0,$type=1){
        $model = M('Game','tab_');
        $map['tab_game.id'] = $game_id;
        $map['file_type'] = $type;
        $data = $model
        ->field('tab_game_source.*,tab_game.game_name,tab_game.add_game_address,tab_game.ios_game_address')
        ->join("left join tab_game_source on tab_game.id = tab_game_source.game_id")->where($map)->find();
        if($type==1){
            if($data['file_url']!=''||!varify_url($data['add_game_address'])){
                $this->down($data['file_url']);
            }
            else{
                Header("HTTP/1.1 303 See Other");
                Header("Location: ".$data['add_game_address']); 
            }
        }else{
            if($data['file_url']!=''||!varify_url($data['ios_game_address'])){
                $this->down($data['file_url']);
            }
            else{
                Header("HTTP/1.1 303 See Other");
                Header("Location: ".$data['ios_game_address']); 
            }
        }
    }
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
    //数据流下载
    // public function down($file, $isLarge = false, $rename = NULL)
    // {
    //     if(headers_sent())return false;
    //     if(!$file) {
    //         $this->error('文件不存在哦 亲!');
    //         //exit('Error 404:The file not found!');
    //     }
    //     if($rename==NULL){
    //         if(strpos($file, '/')===false && strpos($file, '\\')===false)
    //             $filename = $file;
    //         else{
    //             $filename = basename($file);
    //         }
    //     }else{
    //         $filename = $rename;
    //     }

    //     header('Content-Description: File Transfer');
    //     header("Content-Type: application/force-download;");
    //     header('Content-Type: application/octet-stream');
    //     header("Content-Transfer-Encoding: binary");
    //     header("Content-Disposition: attachment; filename=\"$filename\"");
    //     header('Expires: 0');
    //     header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    //     header('Pragma: public');
    //     header('Content-Length: '.filesize($file));//$_SERVER['DOCUMENT_ROOT'].
    //     header("Pragma: no-cache"); //不缓存页面
    //     //ob_clean();
    //     flush();
    //     if($isLarge)
    //         self::readfileChunked($file);
    //     else
    //         readfile($file);
    // }

    public function down_material($game_id){
        $map['status'] = 1;
        $game = M("game",'tab_')->where($map)->find($game_id);
        $material = $game['material_url'];
        $this->down($material);
    }
}
