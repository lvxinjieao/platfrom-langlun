<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

/**
 * 前台公共库文件
 * 主要定义前台公共函数库
 */


/**
 * 检测用户是否登录
 * @return integer 0-未登录，大于0-当前登录用户ID
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function is_login_promote(){
    $user = session('promote_auth');
    if (empty($user)) {
        return 0;
    } else {
        return session('promote_auth_sign') == data_auth_sign($user) ? $user['pid'] : 0;
    }
}

function get_pay_sett($id){
    switch ($id) {
        case 0:
        return "未提现";
            break;

        case 1:
        return "已提现";
            break;
        
    }
}
/**
 * 检测验证码
 * @param  integer $id 验证码ID
 * @return boolean     检测结果
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function check_verify($code, $id = 1){
	$verify = new \Think\Verify();
	return $verify->check($code, $id);
}

/**
 * 获取列表总行数
 * @param  string  $category 分类ID
 * @param  integer $status   数据状态
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function get_list_count($category, $status = 1){
    static $count;
    if(!isset($count[$category])){
        $count[$category] = D('Document')->listCount($category, $status);
    }
    return $count[$category];
}

/**
 * 获取段落总数
 * @param  string $id 文档ID
 * @return integer    段落总数
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function get_part_count($id){
    static $count;
    if(!isset($count[$id])){
        $count[$id] = D('Document')->partCount($id);
    }
    return $count[$id];
}

/**
 * 获取导航URL
 * @param  string $url 导航URL
 * @return string      解析或的url
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function get_nav_url($url){
    switch ($url) {
        case 'http://' === substr($url, 0, 7):
        case '#' === substr($url, 0, 1):
            break;        
        default:
            $url = U($url);
            break;
    }
    return $url;
}


function get_apply_dow_url($game_id=0,$promote_id=0)
{
    $model = M('Apply','tab_');
    $map['game_id'] = $game_id;
    $map['promote_id'] = $promote_id;
    $data = $model->where($map)->find();
    if(empty($data['dow_url'])){
        $game_address = M('game','tab_')->where('id='.$game_id)->find();
        if($game_address['sdk_version']==1){
            if($game_address['add_game_address']!=''){
                $game_address_url=$game_address['add_game_address'];
                return $game_address_url;
            }else{
                $game_address_url=$game_address['and_dow_address'];
                return 'http://'.$_SERVER['HTTP_HOST'].substr($game_address_url,1);
            }
        }else{
            if($game_address['ios_game_address']!=''){
                $game_address_url=$game_address['ios_game_address'];
                return $game_address_url;
            }else{
                $game_address_url=$game_address['ios_dow_address'];
                return 'http://'.$_SERVER['HTTP_HOST'].substr($game_address_url,1);
            }
        }
    }elseif ($data['status'] != 1 || $data['enable_status'] != 1 || $data['dow_status'] != 1){
        return 'http://'.$_SERVER['HTTP_HOST'];
    }else{
        return 'http://'.$_SERVER['HTTP_HOST'].$data['dow_url'];
    }
}

function get_promote_list_by_id(){
    $map['parent_id']=get_pid();
    $pro=M("promote","tab_")->where($map)->select();
    return $pro;
}
function index_show($param=array()){
    $paramcount=count($param);
    if($paramcount>0){
        $paramm[0][]=$param[0];
        $paramm[0][]=$param[1];
        $paramm[0][]=$param[2];
    }
    if($paramcount-3>0){
        $paramm[1][]=$param[3];
        $paramm[1][]=$param[4];
        $paramm[1][]=$param[5];
    }
    foreach ($paramm as $key => $value) {
        foreach ($value as $k => $v) {
            if($v==''){
                unset($paramm[$key][$k]);
            }
        }
    }
    return $paramm;
}

//获取站点游戏名称
function get_site_game_name($id){
    $data = M('site_game','tab_')->find($id);
    return $data['game_name'];
}

//获取礼包激活码数量
function get_site_gift_num($id){
    $data = M('site_gift','tab_')->find($id);
    $novice = explode(',',$data['novice']);
    $count = count($novice);
    if(empty(array_pop($novice)) && $count > 0){
        $count--;
    }
    if(empty($novice[0])){
        $count--;
    }
    return $count;
}

//获取支付方式
function get_pay_way($id=null)
{
     if(!isset($id)){
         return false;
     }
     switch ($id) {
        case 0:
             return "平台币";
            break;
         case 1:
            return "支付宝";
             break;
        case 2:
             return "微信";
            break;
        case 3:
             return "微信APP";
            break;
       case 4:
               return "威富通";
             break;
         case 10:
             return "银联支付";
             break;
         default:
              return "支付方式错误";
            break;
       }

   }
/**
 * 获取申请游戏列表
 * @return array，false
 * @author yyh 
 */
function get_apply_game_list2($promote_id=0)
 {
    $apply = M('Apply',"tab_");
     $map['tab_game.game_status'] = 1;
    $map['promote_id'] = $promote_id;
    $lists = $apply
            ->field("tab_apply.*,tab_game.icon,tab_game.relation_game_id,tab_game.id as game_id,tab_game.short,tab_game.game_appid,tab_game.discount")
            ->join("tab_game on tab_apply.game_id = tab_game.id")
            ->where($map)
            ->order("id DESC")
            ->select();
    
    if(empty($lists)){return false;}
    return $lists;
 }



/**
 *推广员搜索游戏下拉列表
 */
function get_promote_serach_game(){
    $game = M("game","tab_");
    if(!empty($type)){
        $map['developers']  = $type == 1? array('EQ',0):array('GT',0);
        $map['game_status'] =  $type == 1? 1:array('in','0,1');

    }else{
        $map['apply_status'] = 1;
        $map['online_status'] = 1;

    }
    $map['game_status'] = 1;
    $lists = $game->field('id,game_name,icon')->where($map)->select();
    if(empty($lists)){return false;}
    return $lists;
}

//二级推广员id
function get_promote_child_ids($id){
    $map1['parent_id']=$id;
    $arr1=M('promote','tab_')->where($map1)->field('id')->select();
    if($arr1){
        return $arr1;
    }else{
        return false;
    }

}


/**
 * 获取打包id ,单价 分成比例
 * @param  [type] $promote_id [description] 0:id  1:money 2:ratio
 * @param  [type] $game_id    [description]
 * @return [type]             [description]
 */
function get_apply_id($promote_id=0,$game_id=0,$type=0){
    if($promote_id !=0 && $game_id != 0){
        $map['promote_id']=$promote_id;
        $map['game_id']=$game_id;
        $data=M('apply','tab_')->field('id,ratio,money')->where($map)->find();
        if($data){
            if($type==1){
                return $data['money'];
            }elseif ($type==2) {
                return $data['ratio'];
            }else{
                return $data['id'];
            }
        }else{
            return false;
        }
    }else{
        return false;
    }
}

/**
 * [获取手游版本号]
 * @param string $game_id
 * @return bool
 * @author 郭家屯[gjt]
 */
function get_version($game_id=''){
    if($game_id){
        $res=M('game_source','tab_')->field('version')->where(['game_id'=>$game_id])->find();
        return $res['version'];
    }else{
        return false;
    }
}

/**
 * [获取游戏版本]
 * @param  [type] $id [description]
 * @return [type]     [description]
 */
function game_version($id){
    $game=M('game','tab_');
    $map['id']=$id;
    $data=$game->field('sdk_version')->where($map)->find();
    if($data['id']==null){
        return false;
    }
    return $data['version'];
}
// 获取IOS游戏名称
function get_ios_game_name($game_id=null,$field='id'){
    $map[$field]=$game_id;
    $map['game_version']=0;
    $data=M('Game','tab_')->where($map)->find();
    if(empty($data)){return false;}
    $game_name=explode("(", $data['game_name']);
    return $game_name[0];
}