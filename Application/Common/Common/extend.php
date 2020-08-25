<?php

/**
 * 系统非常规MD5加密方法
 * @param  string $str 要加密的字符串
 * @return string 
 */
function think_ucenter_md5($str, $key = 'ThinkUCenter'){
    return '' === $str ? '' : md5(sha1($str) . $key);
}
/**
 *将时间戳装成年月日(不同格式)
 *@param  int    $time 要转换的时间戳
 *@param  string $date 类型
 *@return string
 *@author [yyh] <[<email address>]>
 */
function set_show_time($time = null,$type='time',$tab_type=''){
    $date = "";
    switch ($type) {
        case 'date':
            $date = date('Y-m-d ',$time);
            break;
        case 'time':
            $date = date('Y-m-d H:i',$time);
            break;
        default:
            $date = date('Y-m-d H:i:s',$time);
            break;
    }
    if(empty($time)){//若为空  根据不同情况返回
        if($tab_type==''){
            return "暂无登录";
        }
        if($tab_type=='forever'){
            return "永久";
        }
        if($tab_type=='other'){
            return "";
        }
    }
    return $date;
}
/**
 * [获取开放类型名称]
 * @param  string $id [description]
 * @return [type]     [description]
 */
function get_one_opentype_name($id=''){
    if($id==''){
        return '';
    }
    if($id==0){
         return "公测";
    }
    $data=M('Opentype','tab_')->where(array('id'=>$id))->find();
    if($data==''){
        return false;
    }else{
        return $data['open_name'];
    }
}
function get_recommend_type($type,$group=1){
    $recommend=explode(',',$type);
    $res=[];
    foreach ($recommend as $key => $value) {
        $res[$key]=get_info_status($value,$group);
    }
    return implode(',',$res);
}
/**
 * 获取对应游戏类型的状态信息
 * @ram int $group 状态分组
 * @param int $type  状态文字
 * @return string 状态文字 ，false 未获取到
 * @author [yyh] <[<email address>]>
 */
function get_info_status($type=null,$group=0){
    if(!isset($type)){
        return false;
    }
    $arr=array(
        0 =>array(0=>'关闭'   ,1=>'开启'),
        1 =>array(0=>'不推荐' ,1=>'推荐',2=>"热门",3=>'最新'),//游戏设置状态
        2 =>array(0=>'否'     ,1=>'是'),
        3 =>array(0=>'未审核' ,1=>'正常',2=>'锁定'),//推广员状态
        4 =>array(0=>'锁定'   ,1=>'正常'),//用户状态
        5 =>array(0=>'未审核' ,1=>'已审核'   ,2=>'已驳回'),//游戏审核状态
        6 =>array(0=>'未修复' ,1=>'已修复'),//纠错状态
        7 =>array(0=>'失败'   ,1=>'成功'),//纠错状态
        8 =>array(0=>'禁用'   ,1=>'启用'),//显示状态
        9 =>array(0=>'下单未付款' ,1=>'充值成功'),//显示状态
        10 =>array(0=>'正常'   ,1=>'拥挤',2=>'爆满'),//区服状态
        12 =>array(0=>'未支付',1=>'成功'),
        13 =>array(1=>'已读',2=>'未读'),
        14 =>array(0=>'通知失败',1=>'通知成功'),
        15 =>array(0=>'未充值',1=>'已充值'),
        16 =>array(0=>'未回复',1=>'已回复'),
        17 =>array(0=>'平台币',1=>'绑定平台币'),
        18 => ['平台币','支付宝','微信','聚宝云'],
        19 => ['审核中','已通过','拒绝'],
        20 => ['','一级','二级'],
        21 => ['平台币','支付宝','微信','微信APP','威富通','','竣付通'],
        22 => [1=>'安卓',2=>'苹果',3=>'H5'],
        23 => ['','安卓','苹果'],
        24 => ['','系统分配','自主添加'],
        25 => ['','通过','未审核'],
        26 => ['','开启','关闭'],
        27 => ['','实物','虚拟物品'],
        36 => ['未处理','已处理','忽略'],
    );
    return $arr[$group][$type];
}
//支付方式用于代充
function agent_all_way(){
    $pay_way[1]=array('key'=>1,'value'=>'支付宝');
    $pay_way[2]=array('key'=>2,'value'=>'微信');
    //$pay_way[3]=array('key'=>3,'value'=>'聚宝云');
    $pay_way[4]=array('key'=>4,'value'=>'平台币');
    //$pay_way[5]=array('key'=>5,'value'=>'竣付通');
    return $pay_way;
}
/**
 * [get_parent_id 获取父级id]
 * @param  [type] $param [description]
 * @param  string $type  [description]
 * @return [type]        [description]
 * @author [yyh] <[<email address>]>
 */
function get_parent_id($param,$type='id'){
    $map[$type]=$param;
    $pdata=M('Promote','tab_')->where($map)->find();
    if(empty($pdata)){
        return false;
    }else{
        $p_id=$pdata['parent_id']?$pdata['parent_id']:0;
        return $p_id;
    }
}
/**
 * [get_game_list 游戏详情]
 * @return [type] [description]
 */
function get_game_list($map='')
{
    $game = M("game","tab_");
    $lists = $game->where($map)->select();
    if(empty($lists)){return false;}
    return $lists;
}
function get_relation_game_list($map=[]){
    $game = M("game","tab_");
    $lists = $lists2 = $game
            ->field('id,game_name,short,relation_game_id,relation_game_name,sdk_version')
            ->where($map)
            ->order('id asc')
            ->select();
    $b=array();
    foreach($lists as $v){
        $b[]=$v['relation_game_id'];
    }
    $c=array_unique($b);
    foreach($c as $v){
        $n=0;
        foreach($lists as $t){
            if($v==$t['relation_game_id'])
            {
                $new_lists[$v] = $t; 
                $new_lists[$v]['game_ids'][] = $v; 
                if($t['sdk_version']!=3){
                    $new_lists[$v]['game_ids'][] = $t['id']; 
                }
                unset($t);
            }
        }
    }
    // dump($new_lists);exit;
    // foreach ($new_lists as $key => &$value) {
    //     $value['all_game_id'] = implode(',', $value['game_ids']);
    //     unset($value['game_ids']);
    // }
    if(empty($new_lists)){return false;}
    return $new_lists;
}
/**
 * [get_game_name 获取游戏名称]
 * @param  [type] $game_id [description]
 * @param  string $field   [description]
 * @return [type]          [description]
 * @author [yyh] <[<email address>]>
 */
function get_game_name($game_id=null,$field='id'){
    $map[$field]=$game_id;
    $data=M('Game','tab_')->where($map)->find();
    if(empty($data)){return ' ';}
    return $data['game_name'];
}
/**
 * [null_to_0 description]
 * @param  [type] $num [description]
 * @return [type]      [description]
 * @author [yyh] <[<email address>]>
 */
function null_to_0($num){
    if($num){
        return sprintf("%.2f",$num);
    }else{
        return sprintf("%.2f",0);
    }
}
/**
 * [total description]
 * @param  [type]  $type [description]
 * @param  boolean $str  [description]
 * @return [type]        [description]
 */
function total($type,$str=true) {
    switch ($type) {
        case 1: { // 今天
            $start=mktime(0,0,0,date('m'),date('d'),date('Y'));
            $end=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
        };break;
        case 2: { // 本周
            //当前日期
            $sdefaultDate = date("Y-m-d");
            //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期
            $first=1;
            //获取当前周的第几天 周日是 0 周一到周六是 1 - 6
            $w=date('w',strtotime($sdefaultDate));
            //获取本周开始日期，如果$w是0，则表示周日，减去 6 天
            $week_start=date('Y-m-d',strtotime("$sdefaultDate -".($w ? $w - $first : 6).' days'));
            //本周结束日期
            $week_end=date('Y-m-d',strtotime("$week_start +6 days"));
                        //当前日期
            $sdefaultDate = date("Y-m-d");
            //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期
            $first=1;
            //获取当前周的第几天 周日是 0 周一到周六是 1 - 6
            $w=date('w',strtotime($sdefaultDate));
            //获取本周开始日期，如果$w是0，则表示周日，减去 6 天
            $start=strtotime("$sdefaultDate -".($w ? $w - $first : 6).' days');
            //本周结束日期
            $end=$start+7*24*60*60-1;
        };break;
        case 3: { // 本月
            $start=mktime(0,0,0,date('m'),1,date('Y'));
            $end=mktime(0,0,0,date('m')+1,1,date('Y'))-1;
        };break;
        case 4: { // 本年
            $start=mktime(0,0,0,1,1,date('Y'));
            $end=mktime(0,0,0,1,1,date('Y')+1)-1;
        };break;
        case 5: { // 昨天
            $start=mktime(0,0,0,date('m'),date('d')-1,date('Y'));
            $end=mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
        };break;
        case 6: { // 上周
            $start=mktime(0, 0 , 0,date("m"),date("d")-date("w")+1-7,date("Y"));
            $end=mktime(23,59,59,date("m"),date("d")-date("w")+7-7,date("Y"));
        };break;
        case 7: { // 上月
            $start=mktime(0, 0 , 0,date("m")-1,1,date("Y"));
            $end=mktime(23,59,59,date("m") ,0,date("Y"));
        };break;
        case 8: { // 上一年
            $start=mktime(0,0,0,date('m')-11,1,date('Y'));
            $end=mktime(0,0,0,date('m')+1,1,date('Y'))-1;
        };break;
        case 9: { // 前七天
            $start = mktime(0,0,0,date('m'),date('d')-6,date('Y'));
            $end=mktime(23,59,59,date('m'),date('d'),date('Y'));
        };break;
        case 10: { // 前30天
            $start = mktime(0,0,0,date('m'),date('d')-29,date('Y'));
            $end=mktime(23,59,59,date('m'),date('d'),date('Y'));
        };break;
        default:
            $start='';$end='';
    }
    if($str){
        return " between $start and $end ";
    }else{
        return ['between',[$start,$end]];
    }
}
/**
 * [二维数组 按照某字段排序]
 * @param  [type] $arrays     [description]
 * @param  [type] $sort_key   [description]
 * @param  [type] $sort_order [description]
 * @param  [type] $sort_type  [description]
 * @return [type]             [description]
 * @author [yyh] <[<email address>]>
 */
function my_sort($arrays,$sort_key,$sort_order=SORT_ASC,$sort_type=SORT_NUMERIC ){ 
    if(is_array($arrays)){   
        foreach ($arrays as $array){   
            if(is_array($array)){   
                $key_arrays[] = $array[$sort_key];
           }else{   
                return false;   
            }   
        }   
    }else{   
        return false;   
    }  
    array_multisort($key_arrays,$sort_order,$sort_type,$arrays);   
    return $arrays;   
} 
/**
 * [获取渠道名称]
 * @param  integer $prmote_id [description]
 * @return [type]             [description]
 * @author [yyh] <[<email address>]>
 */
function get_promote_name($prmote_id=0){
    if($prmote_id==0){
        return '官方推广员';
    }
    $promote = M("promote","tab_");
    $map['id'] = $prmote_id;
    $data = $promote->where($map)->find();
    if(empty($data)){return '官方推广员';}
    if(empty($data['account'])){return "未知推广";}
    $result = $data['account'];
    return $result;
}
/**
  * [获取用户实体]
  * @param  integer $id        [description]
  * @param  boolean $isAccount [description]
  * @return [yyh]             [description]
  */
function get_user_entity($id=0,$isAccount = false){
    if($id =='' ){
        return false;
    }
    $user = M('user',"tab_");
    if($isAccount){
        $map['account'] = $id;
        $data = $user->where($map)->find();
    }
    else{
        $data = $user->find($id);
    }
    if(empty($data)){
        return false;
    }
    return $data;
}
/**
*随机生成字符串
*@param  $len int 字符串长度
*@return string
*@author yyh
*/
function sp_random_string($len = 6) {
    $chars = array(
        "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
        "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
        "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
        "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
        "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
        "3", "4", "5", "6", "7", "8", "9"
    );
    $charsLen = count($chars) - 1;
    shuffle($chars);    // 将数组打乱
    $output = "";
    for ($i = 0; $i < $len; $i++) {
        $output .= $chars[mt_rand(0, $charsLen)];
    }
    return $output;
}
/**
 * [get_promote_account 推广员账号]
 * @param  [type] $promote_id [description]
 * @return [type]             [description]
 * @author [yyh] <[<email address>]>
 */
function get_promote_account($promote_id){
    if($promote_id == 0){
        return '官方推广员';
    }
    if($promote_id == -1){
        return '全站玩家';
    }
    if($promote_id == -2){
        return '推广渠道';
    }
    $data = M('promote','tab_')->find($promote_id);
    $account = empty($data['account']) ? '系统' : $data['account'];
    return $account;
}
/**
 * 获取渠道等级
 * @param $promote_id
 * @return mixed
 * @author [yyh] <[<email address>]>
 */
function get_promote_level($promote_id){
    $model = M('promote','tab_');
    $map['id'] = $promote_id;
    $data = $model->where($map)->find();
    if(empty($data)){
        return '';
    }
    if($data['parent_id'] == 0) {
        return '1';
    }else{
        return 2;
    }
}
// lwx 2017-01-07
function get_systems_name($id,$lan='cn') {
    $list = get_systems_list($lan);
    return $list[$id];
}

/**
 * [get_systems_list description]
 * @param  string $lan [description]
 * @return [type]      [description]
 */
function get_systems_list($lan='cn') {
    switch($lan) {
        case 'en':{$list = array(0=>'double',1=>'Android',2=>'IOS');}break;
        default:$list = array(1=>'安卓',2=>'苹果');
    }
    return $list;
}
/**
 * [get_game_type_name 游戏类型]
 * @param  [type] $type [description]
 * @return [type]       [description]
 */
function get_game_type_name($type = null){
    if(!isset($type) || empty($type)){
        return '全部';
    }
    $cl = M("game_type","tab_")->where("id={$type}")->find();
    return $cl['type_name'];
}
/**
 * 获取游戏类型列表
 * @return array，false
 * @author yyh 
 */
function get_game_type_all() {
    $list = M("Game_type","tab_")->where("status_show=1")->select();
    if (empty($list)) {return '';}
    return $list;
}
function get_opentype_all() {    
    $list = M("Opentype","tab_")->where("status=1")->select();
    if (empty($list)) {return '';}
    return $list;
}
/**
* 生成唯一的APPID
* @param  $str_key 加密key
* @return string
* @author yyh
*/
function generate_game_appid($str_key=""){
    $guid = '';  
    $data = $str_key;  
    $data .= $_SERVER ['REQUEST_TIME'];     
    $data .= $_SERVER ['HTTP_USER_AGENT']; 
    $data .= $_SERVER ['SERVER_ADDR'];       
    $data .= $_SERVER ['SERVER_PORT'];      
    $data .= $_SERVER ['REMOTE_ADDR'];     
    $data .= $_SERVER ['REMOTE_PORT'];     
    $hash = strtoupper ( hash ( 'MD4', $guid . md5 ( $data ) ) );
    $guid .= substr ( $hash, 0, 9 ) . substr ( $hash, 17, 8 ) ; 
    return $guid;
}
//随机码生成
function randomCode($num) {
    if(!is_numeric($num)) return false;
    $chars = array('A', 'B', 'C', 'D', 'E', 'F', 'G','H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S','T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'a', 'b', 'c', 'd', 'e', 'f', 'g','h', 'i', 'j', 'k', 'l', 'm','n', 'o', 'p', 'q', 'r', 's','t', 'u', 'v', 'w', 'x', 'y', 'z', '0', '1', '2', '3', '4','5', '6', '7', '8', '9');
    $len = count($chars);
    $ret = '';
    for ($i = 0; $i < $num; $i++) {
        $a = rand(0, $len - 1);
        $ret .= $chars[$a];
    }
    return $ret;
}
/**
 * 生成游戏server key
 */
function generate_game_server_key($num){
    do {
        $key = strtolower(randomCode($num));
        $isExist = M("GameSet","tab_")->where("game_key='{$key}'")->find();
    } while (!empty($isExist));
    return $key;
}
/**
* 生成游戏client key
*/
function generate_game_client_key($num){
    do {
        $key = strtoupper(randomCode($num));
        $isExist = M("GameSet","tab_")->where("access_key='{$key}'")->find();
    } while (!empty($isExist));
    return $key;
}
/**
 * [play_num description]
 * @param  [type] $id [description]
 * @return [type]     [description]
 * @author [yyh] <[<email address>]>
 */
function play_num($id){
    $gamedata=M('Game','tab_')->field('play_count')->where(array('id'=>$id))->find();
    return $gamedata['play_count'];
}
/**
 * [get_ss sdk加密方法]
 * @param  [type] $key [description]
 * @return [type]      [description]
 * @author [yyh] <[<email address>]>
 */
function get_ss($key){
    $verfy_key="gnauhcgnem";
    $len=strlen($key);
    $res="";
    for ($i=0; $i <$len; $i++) { 
        if($i<11){
            $a=0;
        }else{
            $a=-1;
        }
        $res.=chr(ord($key[$i])^ord($verfy_key[$i%10+$a]));
    }
    return base64_encode($res);
}
/**
 * 获取游戏列表
 * @return array，false
 * @author yyh 
 */
function get_small_game_list()
{
    $game = M("smallGame","tab_");
    $lists = $game->field('id,game_name,appid')->select();
    if(empty($lists)){return false;}
    return $lists;
}
/**
 * [auto_get_access_token 微信access_token有效性]
 * @param  [type] $dirname [description]
 * @return [type]          [description]
 * @author [yyh] <[<email address>]>
 */
function auto_get_access_token($dirname){
    $appid     = C('wx_login.appid');
    $appsecret = C('wx_login.appsecret');
    $access_token_validity=file_get_contents($dirname);
    if($access_token_validity){
        $access_token_validity=json_decode($access_token_validity,true);
        $is_validity=$access_token_validity['expires_in_validity']-1000>time()?true:false;
    }else{
        $is_validity=false;
    }
    $result['is_validity']=$is_validity;
    $result['access_token']=$access_token_validity['access_token'];
    return $result;
}
/**
 * [arr_count 计算数组个数]
 * @param  [type] $string [description]
 * @return [type]         [description]
 * @author [yyh] <[<email address>]>
 */
function arr_count($string){
    if($string){
        $arr=explode(',',$string);
        $cou=count($arr);
    }else{
        $cou=0;
    }
    return $cou;
}
/**
 * [获取文本 超过字数显示..]
 * @param  [type] $title [description]
 * @return [type]        [description]
 */
function get_title($title,$len=30){
    if(strlen($title) > $len){
         $title = substr($title, 0,$len).'...';
    }else{
        $title = $title;
    }
    if(empty($title)){return false;}
    return $title;
}
/**
 * [获取扩展状态]
 * @param  [type] $name [description]
 * @return [type]       [description]
 */
function get_tool_status($name){
    $map['name']=$name;
    $tool=M("tool","tab_")->where($map)->find();
    return $tool['status'];
}
/**
 * [用于统计排行  给根据某一字段倒序 获得的结果集插入排序字段 ]
 * @param  [type] $arr [description]
 * @return [type]      [description]
 * @author [yyh] <[<email address>]>
 */
function array_order($arr){
    foreach ($arr as $key => $value) {
        $arr[$key]['rand']=++$i;
    }
    return $arr;
}

/* 获取日期间隔数组 
@author yyh */
function get_date_list($d1='',$d2='',$flag=1) {
    if ($flag == 1){/* 天 形如：array('2017-03-10','2017-03-11','2017-03-12','2017-03-13')*/
        $d1 = $d1?$d1:mktime(0,0,0,date('m'),date('d')-6,date('Y'));
        $d2 = $d2?$d2:mktime(0,0,0,date('m'),date('d'),date('Y'));
        $date = range($d1,$d2,86400);
        $date = array_map(create_function('$v','return date("Y-m-d",$v);'),$date);
    } elseif ($flag == 2) {/* 月 形如：array('2017-01','2017-02','2017-03','2017-04')*/
        $d1 = $d1?$d1:mktime(0,0,0,date('m')-5,1,date('Y'));
        $d2 = $d2?$d2:mktime(0,0,0,date('m'),date('d'),date('Y'));
        $i = false;
        while($d1<$d2) {
            $d1 = !$i?$d1:strtotime('+1 month',$d1);
            $date[]=date('Y-m',$d1);$i=true;
        }
        array_pop($date);
    } elseif ($flag == 3) {/* 周 形如：array('2017-01','2017-02','2017-03','2017-04')*/
        $d1 = $d1?$d1:mktime(0,0,0,date('m')-2,1,date('Y'));
        $d2 = $d2?$d2:mktime(0,0,0,date('m'),date('d'),date('Y'));

        $i = false;
        while($d1<$d2) {
            $d1 = !$i?$d1:strtotime('+1 week',$d1);
            $date[]=date('Y-W',$d1);$i=true;
        }
    } elseif ($flag == 4) {
        $d1 = $d1?date('Y-m',$d1):date('Y-m',strtotime('-5 month'));
        $d2 = $d2?date('Y-m',$d2):date('Y-m');
        $temp = explode('-',$d1);
        $year = $temp[0];
        $month = $temp[1];
        while(strtotime($d1)<=strtotime($d2)) {
            $date[]=$d1;$month++;if($month>12) {$month=1;$year+=1;}
            $t = strlen($month.'')==1?'0'.$month:$month;
            $d1=$year.'-'.$t;
        }

    }

    return $date;
}
/**
 * [get_settlemt_sum 统计同一订单下的和]
 * @param  [type] $order  [description]
 * @param  [type] $string [description]
 * @return [type]         [description]
 * @author [yyh] <[<email address>]>
 */
function get_settlemt_sum($order,$string){
    $sum = M('Settlement','tab_')->where(array('settlement_number'=>$order))->sum($string);
    return $sum;
}

/**
 * 获取推广员支付宝账号
 */
function get_promote_alipay_account($promote_id){
    $data = M('promote','tab_')->find($promote_id);
    $account = empty($data['alipay_account']) ? '未知' : $data['alipay_account'];
    return $account;
}

/**
 * 获取推广员微信账号
 */
function get_promote_weixin_account($promote_id){
    $data = M('promote','tab_')->find($promote_id);
    $account = empty($data['weixin_account']) ? '未知' : $data['weixin_account'];
    return $account;
}
//下级推广员
function get_subordinate_promote($param,$type="parent_id"){
    if($param==''){
        return false;
    }
    $map[$type]=$param;
    $data=M('Promote','tab_')
        ->field('account')
        ->where($map)
        ->select();
    return array_column($data,'account');
}
/**
 * [判断结算时间是否合法]
 * @param  [type] $start      [description]
 * @param  [type] $promote_id [description]
 * @param  [type] $game_id    [description]
 * @return [type]             [description]
 * @author [yyh] <[<email address>]>
 */
function get_settlement($start,$end,$promote_id,$game_id){
    $start=strtotime($start);
    $end=strtotime($end)+24*60*60-1;
    $map['promote_id']=$promote_id;
    $map['game_id']=$game_id;
    $data=M('settlement','tab_')
        ->where($map)
        ->select();
    foreach ($data as $key => $value) {
        if($start<$value['endtime']){
            if($end>$value['starttime']){
                return true;//开始时间<结算 不可结算
            }else{
                return false;
            }
        }
    }
}
/**
 * [set_number_short 金额单位]
 * @param [type] $number [description]
 */
function set_number_short($number) {
    if (!is_numeric($number) || $number<=0) {return 0;}
    $len = strlen((integer)$number);
    $str = '';
    if ($len<5){$str = $number;}
    elseif($len<9) {$str=round($number/10000,2).'万';}
    elseif ($len<12) {$str=round($number/100000000,2).'亿';}
    elseif ($len<16) {$str=round($number/100000000000,2).'兆';}

    return $str;
}
/*
 * 记录访问日志
 * @site  访问站点  1:PC宽屏  2:PC窄屏 3:wap站  4:app 0:其他
 */
function access_log($site=0){

    $ip = get_client_ip();//访问ip
    $time = 1; //间隔时间

    $log_time = M('access_log','tab_')->where(['access_ip'=>$ip])->order('access_time desc')->getField('access_time');
    //判断间隔时间
    if( (time()-$log_time) > $time){
        return M('access_log','tab_')->add(['access_ip'=>$ip,'access_time'=>time(),'site'=>$site,'uid'=>is_login()]);
    }else{
        return false;
    }

}
/**
 * [icon_url 获取图片连接]
 * @param  [type] $value [description]
 * @return [type]        [description]
 * @author [yyh] <[<email address>]>
 */
function icon_url($value){
    $cover = get_cover($value, 'path');
    if(strpos($cover, 'http')!==false){
        $url = $cover;
    }elseif(!$cover){
        $url = '';
    }else{
        $url = 'http://'.$_SERVER['HTTP_HOST'].$cover;
    }
    return $url;
}
/**
 * [collect_num 收藏人数]
 * @param  [type] $id [description]
 * @return [type]     [description]
 * @author [yyh] <[<email address>]>
 */
function collect_num($id){
    $subQuery=M('user_behavior','tab_')->field('count(*)')->where(array('game_id'=>$id,'status'=>1))->group('user_id')->buildsql();
    $countsql = "select count(*) as num from (".$subQuery.') a';
    $count = M()->query($countsql);
    $count  = $count[0]['num'];
    if($count==''||$count==null){
        $count=0;
    }
    return $count;
}
//获取游戏http或https头
function get_http_url(){
    $type = C('IS_HTTPS');
    if($type==1){
        return 'https://';
    }else{
        return 'http://';
    }
}
/**
 * [phoneUnique 验证手机唯一性 手机号 和账号 都不能被使用，本账号绑定手机号除外]
 * @param  [type] $phone   [description]
 * @param  string $user_id [description]
 * @return [type]          [description]
 */
function phoneUnique($phone,$user_id=''){
    $map['account']=['eq',$phone];
    $map['phone']=['eq',$phone];
    $map['_logic']='or';
    $data = M('User','tab_')->field('id,account,phone')->where($map)->find();
    if(empty($data)){
        return true;
    }elseif($data['id']==$user_id){
        return true;
    }else{
        return false;
    }
}
/**
 * [checkPhone 检查手机号码格式]
 * @param  [type] $str [description]
 * @return [type]      [description]
 * @author [yyh] <[<email address>]>
 */
function checkPhone($str){
    if(strlen($str)!=11){
        return -1;
    }
    $preg = "/^((13[0-9])|(14[5,7])|(15[0-3,5-9])|(17[0,3,5-8])|(18[0-9])|166|198|199|(147))\d{8}$/";
    $res = preg_match($preg, $str);
    if($res){
        return 1;
    }else{
        return 0;
    }
}
//获取推广员父类id
function get_fu_id($id){
    $map['id']=$id;
    $pro=M("promote","tab_")->where($map)->find();
    if(null==$pro||$pro['parent_id']==0){
        return 0;
    }else{
        return $pro['parent_id'];
    }
}
function get_parent_name($id){
    $map['id']=$id;
    $pro=M("promote","tab_")->where($map)->find();
    if(null!=$pro&&$pro['parent_id']>0){
        $pro_map['id']=$pro['parent_id'];
        $pro_p=M("promote","tab_")->where($pro_map)->find();
        return $pro_p['account'];
    }else if($pro['parent_id']==0){
        return $pro['account'];
    }else{
        return false;
    }
}
/**
 * 模拟post进行url请求
 * @param string $url
 * @param string $param
 */
function request_post($url = '', $param = '') {
    if (empty($url) || empty($param)) {
        return false;
    }

    $postUrl = $url;
    $curlPost = $param;
    $ch = curl_init();//初始化curl
    curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
    curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
    curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
    curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
    $data = curl_exec($ch);//运行curl
    curl_close($ch);

    return $data;
}
/*短信发送验证限制条件  */
/**
 * [checksendcode description]
 * @param  [type]  $phone [description]
 * @param  integer $limit 每天限制发送条数
 * @return [type]         [description]
 */
function checksendcode($phone,$limit=8){
    $number = M('Short_message')->where(array('pid'=>0,'create_ip'=>get_client_ip(),'send_status'=>'000000','send_time'=>array(array('egt',strtotime('today')),array('elt',strtotime('tomorrow')))))->count();
    // $lastip = M('Short_message')->where(['create_ip'=>get_client_ip(),'send_status'])
    if (!empty($limit) && $number>=$limit) {
        $return['status'] = '0';
        $return['msg']='每天发送数量不能超过'.$limit.'条';
        echo json_encode($return); exit;
    }
    $request_time = time();
    $map = array('phone'=>$phone);
    $map['create_time']=array(array('egt',($request_time-60)),array('elt',$request_time));
    $number = $time = M('Short_message')->where($map)->count();
    if ($number>0){
        echo json_encode(array('status'=>0,'msg'=>'请一分钟后再次尝试'));exit;
    }
}
/**
 * [get_table_param 获取某一数据库的字段]
 * @param  [type]  $table [description]
 * @param  [type]  $map   [description]
 * @param  boolean $field [description]
 * @return [type]         [description]
 */
function get_table_param($table,$map,$field=true,$flex='tab_'){
    $data = M($table,$flex)->field($field)->where($map)->find();
    return $data;
}
function wite_text($txt,$name){
    $myfile = fopen($name, "w") or die("Unable to open file!");
    fwrite($myfile, $txt);
    fclose($myfile);
}
/**
 * 判断游戏原包/第三方链接是否可用
 * @param  [type] $game_id [description]
 * @return [type]          [description]
 */
function check_game_sorue($game_id){
    $xia_status = 1;
    $map['id']=$game_id;
    $game=M('game','tab_')->field('dow_status,down_port,sdk_version,and_dow_address,ios_dow_address,add_game_address,ios_game_address')->where(['id'=>$game_id])->find();
    if($game['dow_status']==0){
        $xia_status = 0;
        return $xia_status;
        exit;
    }
    if($game['down_port']==1){
        if($game['sdk_version']==1){
            if(empty($game['and_dow_address'])){
                $xia_status=0;
            }
        }elseif($game['sdk_version']==2){
            if(empty($game['ios_dow_address'])){
                $xia_status=0;
            }
        }else{          
            if(empty($game['and_dow_address'])&& empty($game['ios_dow_address'])){
                $xia_status=0;
            }
        }
    }else{
        if($game['sdk_version']==1){
            if(empty($game['add_game_address'])){
                $xia_status=0;
            }
        }elseif($game['sdk_version']==2){
            if(empty($game['ios_game_address'])){
                $xia_status=0;
            }
        }else{
            if(empty($game['ios_game_address'])&&empty($game['add_game_address'])){
                $xia_status=0;
            }
        }
    }
    return $xia_status;

}
/**
*检查链接地址是否有效
*/
function varify_url($url){  
    $check = @fopen($url,"r");  
    if($check){  
     $status = true;  
    }else{  
     $status = false;  
    }    
    return $status;  
}
/**
 * [游戏某一礼包所有激活码个数（未领取和已领取）]
 * @param  [type] $gid     [游戏id]
 * @param  [type] $gift_id [礼包 id]
 * @return [type]          [description]
 */
function gift_recorded($gid,$gift_id){
    $wnovice=M('giftbag','tab_')->where(array('game_id'=>$gid,'id'=>$gift_id))->find();
    if($wnovice['is_unicode']){
        $wnovice=$wnovice['unicode_num'];
    }else{
        if($wnovice['novice']!=''){
            $wnovice=count(explode(',',$wnovice['novice']));
        }else{
            $wnovice=0;
        }
    }
    $ynpvice=M('gift_record','tab_')->where(array('game_id'=>$gid,'gift_id'=>$gift_id))->select();
    
    if($ynpvice!=''){
        $ynpvice=count($ynpvice);
    }else{
        $ynpvice=0;
    }
    $return['all']=$wnovice+$ynpvice;
    $return['wei']=(int)$wnovice;
    return $return;
}
/**
 * 检测礼包所属区服是否关闭
 * @param int 区服编号
 * @return null或数组
 * @author 鹿文学
 */
function check_gift_server($serverid=0) {
    if (is_numeric($serverid) && $serverid>0) {
        return M('Server','tab_')->field('id')->where(array('show_status'=>1,'id'=>$serverid))->find();
    } else {
        return null;
    }
}
//获取微信支付类型 
function get_wx_type($isapp=0){
    if($isapp){
        $map['name'] = 'wei_xin';//扫码/H5支付
    }else{
        $map['name'] = 'wei_xin_app';//app支付
    }
    $type=M('Tool','tab_')->where($map)->find();
    return $type['status'];
}
//判断支付设置
//yyh
function pay_set_status($type){
    $sta=M('tool','tab_')->field('status')->where(array('name'=>$type))->find();
    return $sta['status'];
}
// lwx seo替换指定参数为相应的字符
function seo_replace($str='',$array=array(),$site='media',$meat='title') {
    // if ($site=='channel') {$title = C('CH_SET_TITLE');}
    // else {$title = C('PC_SET_TITLE');}
    switch ($site) {
        case 'channel':
            switch ($meat) {
                case 'title':
                    $title = C('CH_SET_TITLE');
                    break;
                case 'keywords':
                    $title = C('CH_SET_META_KEY');
                    break;
                case 'description':
                    $title = C('CH_SET_META_DESC');
                    break;
                default:
                    $title = C('CH_SET_TITLE');
                    break;
            }
            break;
        case 'media':
            switch ($meat) {
                case 'title':
                    $title = C('PC_TITLE');
                    break;
                case 'description':
                    $title = C('PC_SET_META_DESC');
                    break;
                default:
                    $title = C('PC_TITLE');
                    break;
            }
            break;
        case 'wap':
            switch ($meat) {
                case 'title':
                    $title = C('WAP_SET_TITLE');
                    break;
                case 'keywords':
                    $title = C('WAP_SET_META_KEY');
                    break;
                case 'description':
                    $title = C('WAP_SET_META_DESC');
                    break;
                default:
                    $title = C('WAP_SET_TITLE');
                    break;
            }
            
            break;
        default:
            $title = C('PC_SET_TITLE');
            break;
    }
    if(session('union_host')){
        $union_set=json_decode(session('union_host')['union_set'],true);
        $title = $union_set!=''?$union_set['site_name']:$title;
    }
    if (empty($str)) {return $title;}
    $find = array('%webname%','%gamename%','%newsname%','%giftname%','%gametype%','%goodname%');
    $replace = array($title,$array['game_name'],$array['title'],$array['giftbag_name'],$array['game_type_name'],$array['good_name']);
    $str =  str_replace($find,$replace,$str);
    
    return preg_replace('/((-|_)+)?((%[0-9A-Za-z_]*%)|%+)((-|_)+)?/','',$str);
}
//获取当前推广员id
function get_pid()
{
    return $_SESSION['onethink_home']['promote_auth']['pid'];
}
/**
 *获取推广员子账号
 */
function get_prmoote_chlid_account($id=0){
    $promote = M("promote","tab_");
    $map['status'] = 1;
    $map["parent_id"] = $id;
    $data = $promote->where($map)->select();
    if(empty($data)){return "";}
    return $data;
}
/**
 * 去除两边空格
 * @param array 要操作的数组
 * @return 数组
 * @author 鹿文学
 */
function array_trim($arr) {
    foreach($arr as $k => $v) {
        $arr[$k] = trim($v);
    }
    return $arr;
}
//二级推广员id
function get_child_ids($id){
    $map1['parent_id']=$id;
    $map1['id']=$id;
    $map1['_logic']='OR';
    $arr1=M('promote','tab_')->where($map1)->field('id')->select();
    if($arr1){
        return $arr1;
    }else{
        return false;
    }
    
}
//检测是否https
function is_https() {
    if ( !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    } elseif ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
        return true;
    } elseif ( !empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
        return true;
    }
    return false;
}
//判断是否是手机端请求
function is_mobile_request()   {    
    $_SERVER['ALL_HTTP'] = isset($_SERVER['ALL_HTTP']) ? $_SERVER['ALL_HTTP'] : '';    
    $mobile_browser = '0';    
    if(preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|iphone|ipad|ipod|android|xoom)/i', strtolower($_SERVER['HTTP_USER_AGENT'])))     $mobile_browser++;    
    if((isset($_SERVER['HTTP_ACCEPT'])) and (strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') !== false))     
        $mobile_browser++;    
    if(isset($_SERVER['HTTP_X_WAP_PROFILE']))     
        $mobile_browser++;    
    if(isset($_SERVER['HTTP_PROFILE']))     
        $mobile_browser++;    
        $mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'],0,4));    
        $mobile_agents = 
        array(       
            'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',       
            'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',       
            'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',       
            'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',       
            'newt','noki','oper','palm','pana','pant','phil','play','port','prox',       
            'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',       
            'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',       
            'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',       
            'wapr','webc','winw','winw','xda','xda-'     
        );    
    if(in_array($mobile_ua, $mobile_agents))     
        $mobile_browser++;    
    if(strpos(strtolower($_SERVER['ALL_HTTP']), 'operamini') !== false)     
        $mobile_browser++;    
    // Pre-final check to reset everything if the user is on Windows    
    if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows') !== false)     
        $mobile_browser=0;    
    // But WP7 is also Windows, with a slightly different characteristic    
    if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows phone') !== false)     
        $mobile_browser++;    if($mobile_browser>0)     
        return true;    
    else   
        return false; 
}
//判断客户端是否是在微信客户端打开
function is_weixin(){ 
    if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
        return true;
    }   
    return false;
}
/**
 * [获取热门游戏列表]
 * @param $rec_status
 * @return bool|mixed
 * @author 幽灵[syt]
 */
function get_hot_game_list($rec_status) {
    $game = M("game","tab_");
    $map['game_status'] = 1;
    $map['test_status'] = 1;
    $map['recommend_status'] = array('like',"%".$rec_status."%");
    $lists = $game->field('relation_game_name as game_name,relation_game_id as id')->where($map)->group('relation_game_id')->order('id desc')->select();
    if(empty($lists)){return false;}
    return $lists;
}
//如果字符长度没有超过限制 则不加上。。。
function msubstr3($str, $start=0, $length, $charset="utf-8", $suffix=true) {
    $slice = mb_substr($str, $start, $length, $charset);
    if (mb_strlen($str) > $length){
        return $suffix ? $slice.'...' : $slice;
    }else{
        return $slice;
    }
}

/**
 * [返回26个字母的数组集合]
 * @return array
 * @author [YYH] <[<email address>]>
 */
function zimu26(){
    for($i=0;$i<26;$i++){
        $zimu[]['value']=chr($i+65);
    } 
    return $zimu;
}
/**
 * [判断每日任务是否完成]
 * @param $user_id
 * @param $name
 * @author 幽灵[syt]
 */
function daily_task($user_id,$key){

    if (empty($user_id)){
        return false;
    }

    $map['user_id'] = $user_id;
    $map['key'] = $key;
    $map['tab_point_record.create_time'] = array('gt',strtotime(date('Y-m-d')));

    $data = M('PointType','tab_')
        ->where($map)
        ->field('tab_point_record.id')
        ->join('tab_point_record ON tab_point_record.type_id = tab_point_type.id')
        ->find();

    if ($data){
        return true;
    }else{
        return false;
    }
}
/**获取账号信息
 *@author 郭家屯
 */
function get_promote_entity($id=0,$isAccount = false){
    if($id =='' ){
        return false;
    }
    $user = M('Promote',"tab_");
    if($isAccount){
        $map['account'] = $id;
        $data = $user->where($map)->find();
    }
    else{
        $data = $user->find($id);
    }
    if(empty($data)){
        return false;
    }
    return $data;
}
/**
 * 获取当前子渠道
 * @param  [type] $id [description]
 * @return [type]     [description]
 * @author [yyh] <[<email address>]>
 */
function get_zi_promote_id($id){
    $map['parent_id']=$id;
    $pro=M("promote","tab_")->field('id')->where($map)->select();
    if(null==$pro){
        return 0;
    }else{
        for ($i=0; $i <count($pro); $i++) {
            $sd[]=implode(",", $pro[$i]);
        }
        return  implode(",", $sd);
    }
}
function is_cache(){
    if(C('CACHE_TYPE')==1||C('CACHE_TYPE')==2){
        return true;
    }else{
        return false;
    }
}
function ageVerify($cardno,$name){
    // $date = age($cardno,$name);
    $appCode = C('age.appcode');
    //身份证号码
    $params['cardNo']=$cardno;
    //身份证姓名
    $params['realName']=$name;
    $date = AGEAPISTORE($params,$appCode);
    if(!empty($date)){
        if($date['result']['isok']!=1){
        return 0;//认证失败
        }else{
            $age = floor((time()-strtotime($date['result']['details']['birth']))/(60*60*24*365));
            if ($age > 17){
                return 1;//已成年
            }else{
                return 2;//未成年
            }
        }
    }else{
       $date = age($cardno,$name);
        if ($date['resp']['code']==0 && $date>0){
            $age = floor((time()-strtotime($date['data']['birthday']))/(60*60*24*365));
            if ($age > 17){
                return 1;
            }else{
                return 2;
            }
        }elseif($date['resp']['code']!=0 && $date>0){
            return 0;
        }else{
            return 0;
        }
    }
   
}
function AGEAPISTORE($params = null, $appCode,$url="http://1.api.apistore.cn/idcard")
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url . '?' . http_build_query($params));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array (
        'Authorization:APPCODE '.$appCode
    ));
    //如果是https协议
    if (stripos($url, "https://") !== FALSE) {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        //CURL_SSLVERSION_TLSv1
        curl_setopt($curl, CURLOPT_SSLVERSION, 1);
    }
    //超时时间
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //返回内容
    $callbcak = curl_exec($curl);
    //关闭,释放资源
    curl_close($curl);
    file_put_contents(dirname(__FILE__).'/aaaa.txt', $callbcak);
    //返回内容JSON_DECODE
    return json_decode($callbcak, true);
}

//用php从身份证中提取生日,包括15位和18位身份证 
function getIDCardInfo($IDCard){ 
    $result['error']=0;//0：未知错误，1：身份证格式错误，2：无错误 
    $result['flag']='';//0标示成年，1标示未成年 
    $result['tdate']='';//生日，格式如：2012-11-15 
    if(!preg_match("/^[1-9]([0-9a-zA-Z]{17}|[0-9a-zA-Z]{14})$/",$IDCard)){
        $result['error']=1;
        $result['msg'] = "位置才有";
        return $result; 
    }else{
        if(strlen($IDCard)==18){
            $tyear=intval(substr($IDCard,6,4)); 
            $tmonth=intval(substr($IDCard,10,2)); 
            $tday=intval(substr($IDCard,12,2)); 
            if($tyear>date("Y")||$tyear<(date("Y")-100)){ 
                $flag=0;
            }elseif($tmonth<0||$tmonth>12){ 
                $flag=0;
            }elseif($tday<0||$tday>31){ 
                $flag=0;
            }else{ 
                $tdate=$tyear."-".$tmonth."-".$tday." 00:00:00"; 
                if((time()-mktime(0,0,0,$tmonth,$tday,$tyear))>18*365*24*60*60){ 
                    $flag=0;
                }else{ 
                    $flag=1;
                } 
            }
        }elseif(strlen($IDCard)==15){
            $tyear=intval("19".substr($IDCard,6,2));
            $tmonth=intval(substr($IDCard,8,2)); 
            $tday=intval(substr($IDCard,10,2)); 
            if($tyear>date("Y")||$tyear<(date("Y")-100)){ 
                $flag=0; 
            }elseif($tmonth<0||$tmonth>12){ 
                $flag=0; 
            }elseif($tday<0||$tday>31){ 
                $flag=0; 
            }else{ 
                $tdate=$tyear."-".$tmonth."-".$tday." 00:00:00"; 
                if((time()-mktime(0,0,0,$tmonth,$tday,$tyear))>18*365*24*60*60){ 
                    $flag=0; 
                }else{ 
                    $flag=1; 
                } 
            } 
        } 
    }
    $result['error']=2;//0：未知错误，1：身份证格式错误，2：无错误 
    $result['isAdult']=$flag;//0标示成年，1标示未成年 
    $result['birthday']=$tdate;//生日日期 
    return $result; 
}
//根据配置向接口发送身份证号和姓名进行验证
function age($cardno,$name){
    $host = "http://idcard.market.alicloudapi.com";
    $path = "/lianzhuo/idcard";
    $method = "GET";
    $appcode = C('age.appcode');
    $headers = array();
    array_push($headers, "Authorization:APPCODE " . $appcode);
    $querys = "cardno=".$cardno."&name=".$name;
    $bodys = "";
    $url = $host . $path . "?" . $querys;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    if (1 == strpos("$".$host, "https://"))
    {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    }
    $output=curl_exec($curl);
    if (empty($output)){
        return -1;//用完
    }
    if(curl_getinfo($curl,CURLINFO_HTTP_CODE)=='200'){
        $headersize=curl_getinfo($curl,CURLINFO_HEADER_SIZE);
        $header=substr($output,0,$headersize);
        $body=substr($output,$headersize);
        curl_close($curl);
        return json_decode($body,true);
    }else{
        return -2;//失败
    }
}


/**
 * 获取原包信息
 * @return [type] [description]
 */
function get_game_source_info($id){
    $map["game_id"] =$id;
    $data = M('GameSource','tab_')->where($map)->find();
    if(empty($data)){
        $result ='';
    }else{
        $result = $data;
    }
    if(empty($result['file_size'])){
        $result['file_size']=0;
    }
    return $result;

}
/**
 * 获取游戏原包路径
 * @return [type] [description]
 * @author zc 894827077@qq.com
 */
function get_game_source_file_url($game_id){
    $map['game_id']=$game_id;
    $find=M('game_source','tab_')->field('file_url')->where($map)->find();
    return ROOTTT.ltrim($find['file_url'],'./');
}

/**
 * 判断手机访问型号
 * @return string
 */
function get_device_type()
{
    //全部变成小写字母
    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    $type = 'other';
    //分别进行判断
    if (strpos($agent, 'iphone') || strpos($agent, 'ipad')) {
        $type = 'ios';
    }

    if (strpos($agent, 'android')) {
        $type = 'android';
    }
    return $type;
}

/**
 *获取推广员父类账号  改写
 *@param  $promote_id 推广id
 *@param  $isShow bool
 *@return string
 *@author yyh
 */
function get_parent_promote_($prmote_id=0,$isShwo=true)
{
    $promote = M("promote","tab_");
    $map['id'] = $prmote_id;//本推广员的id
    $data1 = $promote->where($map)->find();//本推广员的记录
    if(empty($data1)){return false;}
    if($data1['parent_id']==0){return false;}
    if($data1['parent_id']){
        $map1['id']=$data1['parent_id'];
    }
    $data = $promote->where($map1)->find();//父类的记录
    $result = "";
    if($isShwo){
        $result = "[{$data['account']}]";
    }
    else{
        $result = $data['account'];
    }
    return $result;
}

/**
 *设置状态文本
 */
function get_status_text($index=1,$mark=1){
    $data_text = array(
        0  => array( 0 => '失败' ,1 => '成功'),
        1  => array( 0 => '锁定' ,1 => '正常'),
        2  => array( 0 => '未申' ,1 => '已审' , 2 => '拉黑'),
        3  =>array(0=>'不参与',1=>'已参与'),
        4 => ['系统','上级推广员'],
    );
    return $data_text[$index][$mark];
}

/*
* 获取充值方式（会长代充）
*/
function get_pay_way1($id=null)
{
    if(!isset($id)){
        return false;
    }
    switch ($id) {
        case 1:
            return "支付宝";
            break;
        case 2:
            return "微信";
            break;
        case 3:
            return "聚宝云";
            break;
        case 4:
            return "平台币";
            break;
        case 5:
            return "竣付通";
            break;
        case 10:
            return "银联支付";
            break;
        default:
            return "支付方式错误";
            break;
    }
}
function get_trade_no($code){
    if($code == 1){
        $prefix = 'SP_';
    }else{
        $prefix = 'PF_';
    }
    $out_trade_no = $prefix . date('Ymd') . date('His') . sp_random_string(4);
    return $out_trade_no;
}
/**
 * 简单对称加密算法之加密
 * @param String $string 需要加密的字串
 * @param String $skey 加密EKY
 * @author yyh
 * @return String
 */
function simple_encode($string = '', $skey = 'mengchuang') {
    $strArr = str_split(base64_encode($string));
    $strCount = count($strArr);
    foreach (str_split($skey) as $key => $value)
        $key < $strCount && $strArr[$key].=$value;
    return str_replace(array('=', '+', '/'), array('O0O0O', 'o000o', 'oo00o'), join('', $strArr));
}
/**
 * 简单对称加密算法之解密
 * @param String $string 需要解密的字串
 * @param String $skey 解密KEY
 * @author yyh
 * @return String
 */
function simple_decode($string = '', $skey = 'mengchuang') {
    $strArr = str_split(str_replace(array('O0O0O', 'o000o', 'oo00o'), array('=', '+', '/'), $string), 2);
    $strCount = count($strArr);
    foreach (str_split($skey) as $key => $value)
        $key <= $strCount  && isset($strArr[$key]) && $strArr[$key][1] === $value && $strArr[$key] = $strArr[$key][0];
    return base64_decode(join('', $strArr));
}

/**
 * 折扣信息
 * @param $data
 * @return mixed
 */
function discount_data($data){
    if($data['recharge_status'] != 1){
        $game = M('game','tab_')->find($data['game_id']);
        $game_discount = $game['discount'];
        $data['promote_discount'] = $game_discount;
    }
    if($data['promote_status'] != 1 || empty($data['first_discount'])){
        $data['first_discount'] = 10;
    }
    if($data['cont_status'] != 1 || empty($data['continue_discount'])){
        $data['continue_discount'] = 10;
    }
    return $data;
}

/**
 * 子渠道结算状态
 */
function get_son_settlement_stauts($game_id,$promote_id,$str_time,$endtime){
    $result = M('Son_settlement','tab_')
        ->where(array('game_id'=>$game_id,'promote_id'=>$promote_id,
                      'settlement_start_time'=>$str_time,'settlement_end_time'=>$endtime))
        ->find();
    return $result ? 1 : 0;
}

/**
 * 子渠道订单根据字段求和
 */
function get_son_settlemt_sum($order,$string){
    $sum = M('Son_settlement','tab_')->where(array('settlement_number'=>$order))->sum($string);
    return $sum;
}

/**
 * [返回需要的礼包列表页]
 * @param bool $all
 * @return array
 * @author 幽灵[syt]
 */
function get_kefu_lists($all = false,$field=''){
    if ($all){
        $typelist = array('often'=>'客服问题','jifen'=>'积分规则','gift'=>'礼包常见问题');
    }else{
        $typelist = array('changjian'=>'常见问题','mima'=>'密码问题','zhanghu'=>'账户问题','chongzhi'=>'充值问题','gift'=>'礼包中心','jifen'=>'积分商城');
    }
    if (!empty($field)){
        return $typelist[$field];
    }else{
        return $typelist;
    }
}
/**
 * [获取游戏appid]
 * @param  [type] $game_name [description]
 * @param  string $field     [description]
 * @param  string $md5       [16位加密]
 * @return [type]            [description]
 * @author yyh <[email address]>
 */
function get_game_appid($game_name=null,$field='game_name',$md5=false){
    if($game_name==null){
        return false;
    }
    $map[$field]=$game_name;
    $data=M('Game','tab_')->where($map)->find();
    if(empty($data)){return false;}
    if($md5){
        return md5($data['game_appid']);
    }else{
        return $data['game_appid'];
    }
}
/**
 * 获取苹果包名
 * @param  [type] $game_id [description]
 * @return [type]          [description]
 */
function get_payload_name($game_id){
    $map['game_id']=$game_id;
    $find=M('game_source','tab_')->field('bao_name')->where($map)->find();
    return $find['bao_name'];
}
//获得当前的脚本网址 
function GetCurUrl() 
{
  if(!empty($_SERVER["REQUEST_URI"])) 
  {
    $scriptName = $_SERVER["REQUEST_URI"];
    $nowurl = $scriptName;
  } else
  {
    $scriptName = $_SERVER["PHP_SELF"];
    if(empty($_SERVER["QUERY_STRING"])) 
    {
      $nowurl = $scriptName;
    } else
    {
      $nowurl = $scriptName."?".$_SERVER["QUERY_STRING"];
    }
  }
  return $nowurl;
}
function get_source_from_game($id=0) {

    if (!is_numeric($id) || $id<1) {return '全部';}

    $game = M('game','tab_')->field('game_name')->where(['id'=>$id])->find();

    return $game['game_name']?$game['game_name']:'全部';
}
//签名字符串方法
function sortData($data)
{
    ksort($data);
    foreach ($data as $k => $v) {
        $tmp[] = $k . '=' . urlencode($v);
    }
    $str = implode('&', $tmp);
    return $str;
}
//签名方法
function signsortData($data, $secret)
{
    ksort($data);
    foreach ($data as $k => $v) {
        $tmp[] = $k . '=' . urlencode($v);
    }
    $str = implode('&', $tmp) . $secret;
    return md5($str);
}
function auto_get_ticket($dirname){
    $appid     = C('wx_login.appid');
    $appsecret = C('wx_login.appsecret');
    $access_token_validity=file_get_contents($dirname);
    if($access_token_validity){
        $access_token_validity=json_decode($access_token_validity,true);
        $is_validity=$access_token_validity['expires_in_validity']-1000>time()?true:false;
    }else{
        $is_validity=false;
    }
    $result['is_validity']=$is_validity;
    $result['ticket']=$access_token_validity['ticket'];
    return $result;
}


//检测域名格式
function is_url($C_url){
    $str="/^http(s?):\/\/(?:[A-za-z0-9-]+\.)+[A-za-z]{2,4}(?:[\/\?#][\/=\?%\-&~`@[\]\':+!\.#\w]*)?$/";
    if (!preg_match($str,$C_url)){
        return false;
    }else{
        return true;
    }
}
//验证短信
function sdkchecksendcode($phone,$limit,$type=1){
    $number = M('Short_message')->where(array('pid'=>0,'create_ip'=>get_client_ip(),'send_status'=>'000000','send_time'=>array(array('egt',strtotime('today')),array('elt',strtotime('tomorrow')))))->count();
   if (!empty($limit) && $number>=$limit) {
        $return['status'] = '0';
        $return['msg']='每天发送数量不能超过'.$limit.'条';
        echo base64_encode(json_encode($return)); exit;
    }
    $request_time = time();
    $map = array('phone'=>$phone);
    $map['create_time']=array(array('egt',($request_time-60)),array('elt',$request_time));
    $number = $time = M('Short_message')->where($map)->count();
    if ($number>0){
        echo base64_encode(json_encode(array('status'=>$type==1?0:1024,$type==1?"msg":"return_msg"=>'请一分钟后再次尝试')));exit;
    }
}
/* 获取时间闭合区间 @author 鹿文学 */
function period($flag=0,$opposite=true) {/* 0:今日，1：昨天 4:本周 5:上周 8:本月 9:上月*/
    switch($flag) {
        case 0:
        case 1:{
            $start = mktime(0,0,0,date('m'),date('d')-$flag,date('Y'));
            $end = mktime(0,0,0,date('m'),date('d')-$flag+1,date('Y'))-1;
        };break;
        case 3:
        case 7:{
            $start = mktime(0,0,0,date('m'),date('d')-$flag,date('Y'));
            $end = mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
        };break;
        case 4:{
            $start = mktime(0,0,0,date('m'),date('d')-date('w')+1,date('Y'));
            $end = mktime(0,0,0,date('m'),date('d')-date('w')+8,date('Y'))-1;
        };break;
        case 5:{
            $start = mktime(0,0,0,date('m'),date('d')-date('w')-6,date('Y'));
            $end = mktime(0,0,0,date('m'),date('d')-date('w')+1,date('Y'))-1;
        };break;
        case 8: {
            $start = mktime(0,0,0,date('m'),1,date('Y'));
            $end = mktime(0,0,0,date('m')+1,1,date('Y'))-1;
        };break;
        case 9: {
            $start = mktime(0,0,0,date('m')-1,1,date('Y'));
            $end = mktime(0,0,0,date('m'),1,date('Y'))-1;
        };break;
    }
    if ($opposite)
        return array(array('egt',$start),array('elt',$end));
    else
        return array(array('elt',$start),array('egt',$end));
}
/**
 * 检测该渠道是否申请某个游戏
 * @param  [type]  $pid [description]
 * @return boolean      [description]
 */
function is_check_apply_promote($gid,$pid){
    $map['promote_id']=$pid;
    $map['game_id']=$gid;
    $map['status']=1;
    $find=M('apply','tab_')->field('id')->where($map)->find();
    if(null==$find){
        return false;
    }else{
        return true;
    }
}
//获取游戏cp比例
function get_game_selle_ratio($game_id=null,$field='id'){
    $map[$field]=$game_id;
    $data=M('game','tab_')->where($map)->find();
    if(empty($data)){return '';}
    return $data['dratio'];
}

//获取游戏图标
function getgameicon($game_id){
    if(!$game_id) return false;
    $icon_id = M('Game','tab_')->field('icon')->where(array('id'=>$game_id))->find();
    if(empty($icon_id)) return false;
    $icon_url = icon_url($icon_id['icon']);
    return $icon_url;
}
function get_game_icon_id($id)
{
    $map['id']=$id;
    $data=M("game","tab_")->field('icon')->where($map)->find();
    return $data['icon'];
}
//根据游戏ID获取游戏的全部数据
function game_entity_data($game_id = 0){
    $game = M('game','tab_');
    $map['id'] = $game_id;
    $entity = $game->where($map)->find();
    if(empty($entity)){
        return false;
    }
    return $entity;
}
//获取QQUID
function get_union_id($access_token){
    $url = "https://graph.qq.com/oauth2.0/me?access_token=".$access_token."&unionid=1";
    $content = file_get_contents($url);
    $packname1 = '/\"unionid\"\:\"(.*?)\"/';
    preg_match($packname1,$content,$packname2);
    $packname = $packname2[1];
    return $packname;
}
// lwx 判断手机类型
function get_devices_type() {
    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    $type = '';
    if (strpos($agent,'iphone') || strpos($agent,'ipad')) {
        $type = 2;
    } elseif (strpos($agent,'android')) {
        $type = 1;
    }
    
    return $type;
}

/**
 *获取游戏实体
 */
function get_game_entity($data,$field="game_appid"){
    $game = M("Game","tab_");
    $map['status'] = 1;
    $map[$field]   = $data;
    $entity_data   = $game->where($map)->find();
    return $entity_data;
}

//获取游戏运营状态
function get_game_opentype_name($id){
    $model = M('Opentype','tab_');
    $data = $model
        ->field("open_name")
        ->find($id);
    if(empty($data['open_name'])){
        return "";
    }else{
        return $data['open_name'];
    }
}

/**
 * [获取礼包列表]
 * @param string $game_id
 * @param string $limit
 * @return mixed
 */
function get_gift_list($game_id='all',$limit=""){
    $map['status'] = 1;
    $map['game_status']=1;
    $map['end_time']=array(array('gt',time()),array('eq',0),'or');
    if($game_id!='all'){
        $map['game_id']=$game_id;
    }
    $model = array(
        'm_name'=>'Giftbag',
        'prefix'=>'tab_',
        'field' =>'tab_giftbag.id as gift_id,relation_game_name,game_id,tab_giftbag.game_name,giftbag_name,giftbag_type,tab_game.icon,tab_giftbag.create_time',
        'join'  =>'tab_game on tab_giftbag.game_id = tab_game.id',
        'order' =>'create_time desc',
    );
    if(!empty($limit)){
        $map_list=$limit;
    }
    $game  = M($model['m_name'],$model['prefix']);
    $data  = $game
        ->field($model['field'])
        ->join($model['join'])
        ->where($map)
        ->limit($map_list)
        ->order($model['order'])
        ->select();
    return $data;
}

/**
 * [获取游戏类型名称]
 * @param null $type
 * @return bool
 */
function get_game_type($type = null){
    if(!isset($type)){
        return false;
    }
    $cl = M("game_type","tab_")->where("status=1 and id=$type")->limit(1)->select();
    return $cl[0]['type_name'];
}

/**
 * 根据用户账号 获取用户id
 * @param  [type] $account [用户账号]
 * @return [type] id       [用户id]
 * @author [yyh]
 */
function get_user_id($account){
    $map['account']=$account;
    $user=D("User")->where($map)->find();
    return $user['id'];
}

/**
 *随机生成字符串
 *@param  $len int 字符串长度
 *@return string
 *@author 小纯洁
 */
function random_string($len = 7) {

    $chars = array(

        "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",

        "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",

        "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",

        "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",

        "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",

        "3", "4", "5", "6", "7", "8", "9"

    );

    $charsLen = count($chars) - 1;

    shuffle($chars);    // 将数组打乱

    $output = "";

    for ($i = 0; $i < $len; $i++) {

        $output .= $chars[mt_rand(0, $charsLen)];

    }

    return $output;

}
