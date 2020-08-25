<?php

namespace App\Controller;

use Common\Model\GameModel;
use Think\Controller;

class DownController extends Controller {

	/**
	 * 游戏下载
	 * @param int $game_id
	 * @param int $promote_id
	 * author: xmy 280564871@qq.com
	 */
    public function down_file($game_id,$promote_id=0){
    	$data = D("Game")->getGameDownInfo($game_id);
    	$game_info = $data['game_info'];
    	if(!$game_info['dow_status']){
    		$this->set_message(1128,'下载通道已关闭');
    	}
    	if(empty($game_info)){
		    $this->set_message(1034,'游戏不存在');
	    }
    	$packet = $data['packet'];
    	//优先第三方链接
        if($game_info['down_port'] == 2) {
            if($game_info['sdk_version'] == 1){
                $down_url = $game_info['add_game_address'];
            }else{
                $down_url = $game_info['ios_game_address'];
            }
            $this->set_message(200,"成功",['url'=>$down_url,'game_id'=>$game_id,'down_port'=>1]);
        }
    	//判断系统
    	if(GameModel::ANDROID == $game_info['sdk_version']){
		    $down_url = $game_info['add_game_address'];
		    $file_url = $packet['file_url'];
	    }elseif(GameModel::IOS == $game_info['sdk_version']){
		    $down_url = $game_info['ios_game_address'];
		    $file_url = $packet['plist_url'];
	    }else{
		    $this->set_message(1048,'游戏版本错误');
	    }
	    //根据端口下载
	    //渠道下载 下载原包
	    if(!empty($down_url) && empty($promote_id)&&$game_info['down_port']==1){
		    if(varify_url($down_url)){
		    	D('Game')->addGameDownNum($game_id);

			    $this->set_message(200,"成功",['url'=>$down_url,'game_id'=>$game_id]);
		    }else{
			    $this->set_message(1049,'地址错误');
		    }

	    }elseif(!empty($packet)){//原包下载
		    D('Game')->addGameDownNum($game_id);
		    if(!empty($promote_id)){
			    $file_url = $this->package($packet,$promote_id);
		    }
		    $url = $game_info['sdk_version']==2?"https://".$_SERVER['HTTP_HOST'].substr($file_url, 1):"http://".$_SERVER['HTTP_HOST'].substr($file_url, 1);
		    $this->set_message(200,"成功",['url'=>$url,'game_id'=>$game_id]);

	    }else{
		    $this->set_message(1050,"未上传原包");
	    }

    }

	/**
	 * 安卓打包渠道信息
	 * @param $source_info  原包信息
	 * @param $promote_id
	 * @return string
	 * author: xmy 280564871@qq.com
	 */
	public function package($source_info, $promote_id)
	{
		$file_path = $source_info['file_url'];
		//验证原包是否存在
		if (!file_exists($file_path)) {
			$this->set_message(1050,"未上传原包");
		} else {
			$new_name = "game_package" . $source_info['game_id'] . "-" . $promote_id . ".apk";
			$to = "./Uploads/TmpGamePack/" . $new_name;
			copy($source_info['file_url'], $to);
			#打包新路径
			$zip = new \ZipArchive();
			$zip_res = $zip->open($to, \ZipArchive::CREATE);
			if ($zip_res == TRUE) {
				#打包数据
				$pack_data = array(
					"game_id" => $source_info["game_id"],
					"game_name" => $source_info['game_name'],
					"game_appid" => get_game_appid($source_info["game_id"], "id"),
					"promote_id" => $promote_id,
					"promote_account" => get_promote_account($promote_id),
				);
				$zip->addFromString('META-INF/mch.properties', json_encode($pack_data));
				$zip->close();
			}
			return $to;
		}
	}

	/**
	 * 返回输出
	 * @param int $status 状态
	 * @param string $return_msg   错误信息
	 * @param array $data           返回数据
	 * author: xmy 280564871@qq.com
	 */
	public function set_message($status, $return_msg = 0, $data = [])
	{
		$msg = array(
			"status" => $status,
			"return_code" => $return_msg,
			"data" => $data
		);
		echo json_encode($msg);
		exit;
	}

	/**
	 * 安卓下载
	 * author: xmy 280564871@qq.com
	 */
	public function android_app_down(){
		if($this->is_ios()){
			$ios = M('app', 'tab_')->find(4);
			$url="itms-services://?action=download-manifest&url=https://".$_SERVER['HTTP_HOST'].substr($ios['plist_url'],1);
			 Header("Location: ".$url);
		}else{
			$app = M("app","tab_")->find(1);
			Header("Location: ".$app['file_url']);
		}
		
	}

	public function is_ios(){
		if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
		 return true;
		}else if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android')){
		 return false;
		}else{
		  return false;
		}

	}


	// /**
	//  * 下载
	//  * @param $file
	//  * author: xmy 280564871@qq.com
	//  */
	// protected function down_file($file){
	// 	header('Content-Description: File Transfer');
	// 	header("Content-Type: application/force-download;");
	// 	header('Content-Type: application/octet-stream');
	// 	header("Content-Transfer-Encoding: binary");
	// 	header("Content-Disposition: attachment; filename={$file['file_name']}");
	// 	header('Expires: 0');
	// 	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	// 	header('Pragma: public');
	// 	header('Content-Length: ' . filesize(__ROOT__.$file['file_url']));
	// 	header("Pragma: no-cache"); //不缓存页面
	// 	ob_clean();
	// 	flush();
	// 	readfile($file['file_url']);
	// }

}
