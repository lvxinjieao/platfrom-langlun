<?php
namespace Sdk\Controller;
use Think\Controller;
class GameController extends BaseController{
    /**
	 * 读取游戏客服qq
	 * @param int $game_id 游戏ID
	 * @return mixed
	 * 小纯洁
	 */
	public function get_game_ccustom_service_qq(){
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $request = json_decode(base64_decode(file_get_contents("php://input")),true);
		$data = array(
    		"status"=>200,
    		"ccustom_service_qq"=>C(PC_SET_SERVER_QQ),
        );
    	echo base64_encode(json_encode($data));
	}
	/**
	 * 获取渠道悬浮球图
	 * @param int $game_id 游戏ID
	 * @return mixed
	 * 小纯洁
	 */
	public function get_suspend(){
        #获取SDK上POST方式传过来的数据 然后base64解密 然后将json字符串转化成数组
        $request = json_decode(base64_decode(file_get_contents("php://input")),true);
        $data = array(
    		"status"=>200,
    		"sites_ball_logo"=>'http://'.$_SERVER['HTTP_HOST'].get_cover( C(WAP_SUSPEND_ICON) ,'path'),
    		"ball_status"=>C('SUSPENSION_IS_SHOW'),
        );
		echo base64_encode(json_encode($data));die;
	}
}