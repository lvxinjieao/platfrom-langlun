<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/19
 * Time: 13:38
 */

namespace Admin\Controller;

class AjaxController extends ThinkController{

    /**
     * 获取配置项
     * @param $category
     */
    public function getConfigCategory($category){
        switch ($category){
            case 1:$result = C('PC_CONFIG_CATEGORY_LIST');break;
            case 2:$result = C('APP_CONFIG_GROUP_LIST');break;
            case 3:$result = C('CHANNEL_CONFIG_GROUP_LIST');break;
            case 4:$result = C('WAP_CONFIG_GROUP_LIST');break;
            case 5:$result = C('WAP_CONFIG_GROUP_LIST');break;
            case 6:$result = C('BA_CONFIG_GROUP_LIST');break;
            default:$result = C('CONFIG_GROUP_LIST');
        }

        $this->AjaxReturn($result);
    }

    /**
     * 获取区服列表
     * @param $game_id
     */
    public function getServer($game_id=""){
        $data = M('server','tab_')->where(['game_id'=>$game_id])->select();
        $this->AjaxReturn($data);
    }

    /**
     * 获取游戏折扣
     * @param $game_id
     */
    public function getGameDiscount($game_id){
        $data = M('Game','tab_')->find($game_id);
        $res['discount'] = $data['discount'];
        $this->AjaxReturn($res);
    }

    public function getUserPlayGameName($user_id=0){
        $map['user_id'] = $user_id;
        $data = M("UserPlay","tab_")->field("game_id,game_name,bind_balance")->where($map)->group('user_id,game_id')->select();
        $this->AjaxReturn($data);
    }

    /**
    *修改游戏字段
    * @param int game_id
    * @param string $fields 要修改的字段名称
    * @param string $value 要修改的字段的值
    * @author 小纯洁
    */
    public function setGameDataField($game_id=0,$fields='',$value=''){
        $map['id'] = $game_id;
        $result = M('Game','tab_')->where($map)->setField($fields,$value);
        if($result !== false){
            if($fields != "game_status"){
                $game = M('Game','tab_')->where($map)->find();
                $map1['relation_game_name'] = $game['relation_game_name'];
                M('Game','tab_')->where($map1)->setField($fields,$value);
            }
            $data = array('status' =>1 ,'data'=>$result );
            $this->AjaxReturn($data);
        }else{
             $data = array('status' =>0 ,'data'=>$result );
            $this->AjaxReturn($data);
        }
    }

    /**
    *获取推广员申请通过的游戏
    */
    public function getPromoteApplyGame($promote_id = 0){
        if($promote_id == 0){
            $return_msg = array('status'=>0,'info'=>'请选择推广员');
            $this->AjaxReturn($return_msg);
        }
        $fields = "tab_game.id,tab_game.game_name,discount,tab_apply.promote_id,tab_apply.promote_account";
        $data = D('Apply')->getPromoteGame($promote_id,$fields);
        if(empty($data)){
            $return_msg = array('status'=>0,'info'=>'该推广员没有已审核的游戏');
            $this->AjaxReturn($return_msg);
        } else{
            $return_msg = array('status'=>1,'info'=>'成功','data'=>$data);
            $this->AjaxReturn($return_msg);
        }
    }
}