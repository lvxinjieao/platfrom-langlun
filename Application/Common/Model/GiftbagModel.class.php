<?php
/**
 * Created by PhpStorm.
 * User: xmy 280564871@qq.com
 * Date: 2017/3/28
 * Time: 13:46
 */
namespace Common\Model;

use Com\WechatAuth;

class GiftbagModel extends BaseModel{

    /**
     * 礼包列表
     * @param $game_id
     * @param int $p
     * @return mixed
     * author: xmy 280564871@qq.com
     */
    public function getGiftLists($sdk_version,$game_id,$p=1,$row=5,$map=array(),$user_id='',$order="g.id asc"){
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        if($game_id!==false){
            $map['game_id'] = $game_id;
        }
        $mm = strtolower(MODULE_NAME);
        switch ($mm) {
            case 'media':
            case 'mediawide':
                $for_platform_num = 1;
                break;
            case 'mobile':
                $for_platform_num = 2;
                break;
            case 'app':
                $for_platform_num = 3;
                break;
        }
        $map['for_platform'] = array('like','%'.$for_platform_num.'%');
        $map['gb.status'] = 1;
        $map['g.game_status'] = 1;
        $map['g.test_status'] = 1;
        if($sdk_version!==false){
            $map['gb.giftbag_version'] = array('like','%'.$sdk_version.'%');
            $map['g.sdk_version'] = array('in',array($sdk_version,3));
        }
        $time = NOW_TIME;
        $map['start_time'] = ['elt',$time];
        $map['_string'] = "(end_time > {$time} or end_time = 0)";
        $map['_string'] .= " and novice !='' and ((is_unicode =1 and unicode_num>0) or (is_unicode = 0 )) ";
        $data = $this->alias('gb')
            ->field('g.id as game_id,g.game_name,g.relation_game_name,g.relation_game_id,gb.id as gift_id,gb.start_time,gb.end_time,giftbag_name,desribe,novice,g.icon,g.sdk_version,gb.novice_num,gb.giftbag_version')
            ->join('tab_game g on g.id = gb.game_id','left')
            ->where($map)
            ->order($order)
            ->page($page,$row)
            ->select();
        if(empty($data)){
            return [];
        }
        foreach ($data as $key=>$val){
            if(ACTION_NAME=='search' && CONTROLLER_NAME != 'Action'){
                $data[$key]['start_time'] = set_show_time($data[$key]['start_time'],'date','forever');
                $data[$key]['end_time'] = set_show_time($data[$key]['end_time'],'date','forever');
            }
            $data[$key]['gift_detail_url'] = U('Gift/giftdetail',array('gift_id'=>$val['gift_id']));
            $giftnum = gift_recorded($val['game_id'],$val['gift_id']);
            $data[$key]['novice_all'] = $giftnum['all'];//所有
            $data[$key]['novice_surplus'] = $giftnum['wei'];//剩余
            $data[$key]['novice_rate'] = floor($giftnum['wei']/$giftnum['all']*100);
            $data[$key]['icon'] = icon_url($val['icon']);
            if($val['sdk_version'] == 3){
                $data[$key]['play_detail_url']=U('Game/detail',array('game_id'=>$val['relation_game_id'],'ish5'=>1));
            }else{
                $data[$key]['play_detail_url']=U('Game/detail',array('game_id'=>$val['relation_game_id']));
            }
            if($giftnum['wei']<=0){
                unset($data[$key]);
            }
            unset($data[$key]['novice']);
            if($user_id && !empty($data[$key])){
                $grmap['gift_id'] = $data[$key]['gift_id'];
                $grmap['game_id'] = $data[$key]['game_id'];
                $grmap['user_id'] = $user_id;
                $gift_record = M('GiftRecord','tab_')->field('id')->where($grmap)->find();
                if($gift_record){
                    $data[$key]['receive'] = 1;
                    $data[$key]['novice_num'] = $gift_record['novice'];
                }else{
                    $data[$key]['receive'] = 0;
                }
            }
        }
        return $data;
    }
    //$p 用于app分页  网页一次加载完
    public function getGameGiftLists($sdk_version,$rec_status,$user_id=0,$map=array(),$p=1){
        if($rec_status!==false){
            $map['gb.giftbag_type'] = $rec_status;
        }
        if($sdk_version!==false){
            $map['gb.giftbag_version'] = ['like','%'.$sdk_version.'%'];
           // $join = ' and g.sdk_version = '.$sdk_version;
        }
        $mm = strtolower(MODULE_NAME);
        switch ($mm) {
            case 'media':
            case 'mediawide':
                $for_platform_num = 1;
                break;
            case 'mobile':
                $for_platform_num = 2;
                break;
            case 'app':
                $for_platform_num = 3;
                break;
        }
        $map['for_platform'] = array('like','%'.$for_platform_num.'%');
        $time = NOW_TIME;
        $map['start_time'] = ['elt',$time];
        $map['_string'] = "end_time > {$time} or end_time = 0";
        $data1 = $this->alias('gb')
            ->field('gb.game_id,gb.giftbag_name,gb.id as gift_id,g.sdk_version,gb.desribe,gb.is_unicode,gb.novice,gb.unicode_num,gb.start_time,gb.end_time,gb.giftbag_version,novice_num')
            ->join('tab_game as g on g.id = gb.game_id and gb.status = 1 and g.game_status = 1'.$join)
            ->where($map)
            ->order('gb.create_time desc,gb.id desc')
            ->select();//礼包列表
        $data2 = $this->alias('gb')
            ->field('g.relation_game_name as game_name,g.icon,gb.game_id,count(game_id) as gbnum,gb.start_time,gb.end_time,gb.giftbag_version,novice_num')
            ->join('tab_game as g on g.id = gb.game_id and gb.status = 1 and g.game_status = 1'.$join)
            ->where($map)
            ->order('max(gb.create_time) desc,gb.id desc')
            ->group('gb.game_id')
            ->select();//礼包分组
        foreach ($data2 as $k2 => $v2) {
            $data2[$k2]['icon'] = icon_url($v2['icon']);
            $data2[$k2]['play_detail_url']=U('Game/detail',array('game_id'=>$v2['game_id']));
            foreach ($data1 as $k1 => $v1) {
                if($v1['game_id'] == $v2['game_id']){
                    if(($v1['is_unicode']==1&&$v1['unicode_num']<=0)||($v1['is_unicode']!=1&&empty($v1['novice']))){
                        unset($v1);
                        $data2[$k2]['gbnum'] = $data2[$k2]['gbnum']-1;
                        if($data2[$k2]['gbnum']<=0){
                            unset($data2[$k2]);
                            continue;
                        }
                    }else{
                        if($user_id){
                            $grmap['gift_id'] = $v1['gift_id'];
                            $grmap['game_id'] = $v1['game_id'];
                            $grmap['user_id'] = $user_id;
                            $gift_record = M('GiftRecord','tab_')->where($grmap)->find();
                            if($gift_record){
                                $v1['geted'] = 1;
                                $v1['novice_num'] = $gift_record['novice'];
                            }else{
                                $v1['geted'] = 0;
                            }
                        }
                        unset($v1['is_unicode']);
                        unset($v1['novice']);
                        unset($v1['unicode_num']);
                        $v1['gift_detail_url']=U('Gift/giftdetail',array('gift_id'=>$v1['gift_id']));
                        $giftnum = gift_recorded($v1['game_id'],$v1['gift_id']);
                        $v1['novice_all'] = $giftnum['all'];//所有
                        $v1['novice_surplus'] = $giftnum['wei'];//剩余
                        $v1['desribe'] = $this->ttt($v1['desribe']);
                        $data2[$k2]['gblist'][] = $v1;
                    }

                }
            }
        }
        if(empty($data2)){
            return false;
        }else{
            if(strtolower(MODULE_NAME)=='app'){
                $size=10;//每页显示的记录数
                $page = intval($p);
                $page = $page ? $page : 1; //默认显示第一页数据
                $arraypage = $page; //默认显示第一页数据
                $pnum = ceil(count($data2) / $size); //总页数，ceil()函数用于求大于数字的最小整数
                //用array_slice(array,offset,length) 函数在数组中根据条件取出一段值;array(数组),offset(元素的开始位置),length(组的长度)
                $data2 = array_slice($data2, ($arraypage-1)*$size, $size);
            }
            return $data2;
        }
    }

    function ttt($str){
        $order = array("\r\n", "\n", "\r");
        $replace = '';
        $str = str_replace($order, $replace, $str);
        return $str;
    }

    public function myGiftRecord($userid,$map=array(),$p=0){
        $mm = strtolower(MODULE_NAME);
        switch ($mm) {
            case 'media':
            case 'mediawide':
                $for_platform_num = 1;
                break;
            case 'mobile':
                $for_platform_num = 2;
                break;
            case 'app':
                $for_platform_num = 3;
                break;
        }
        $map['for_platform'] = array('like','%'.$for_platform_num.'%');
        $map['g.game_status'] = 1;
        $map['gr.user_id'] = $userid;
        $gift_record1 = M('GiftRecord gr','tab_')
            ->field('gr.*,gb.desribe,g.icon,gb.end_time,g.relation_game_id,g.relation_game_name,giftbag_version,gb.novice as novice_all,gb.end_time')
            ->where($map)
            ->join('tab_game g on g.id = gr.game_id')
            ->join('tab_giftbag gb on gb.game_id = gr.game_id and gb.id = gr.gift_id')
            ->group('gr.gift_id')
            ->order('gb.create_time desc')
            ->select();
        if($p){
            foreach ($gift_record1 as $k1 => $v1) {
                $gift_record1[$k1]['icon'] = icon_url($v1['icon']);
            }
            return $gift_record1;
        }
        $gift_record2 = M('GiftRecord gr','tab_')
            ->field('g.game_name,g.icon,gr.game_id,count(gb.game_id) as gbnum,g.sdk_version,gb.novice as novice_all,gb.end_time')
            ->where($map)
            ->join('tab_game g on g.id = gr.game_id')
            ->join('tab_giftbag gb on gb.game_id = gr.game_id and gb.id = gr.gift_id')
            ->group('gr.game_id')
            ->order('gb.create_time desc')
            ->select();
        foreach ($gift_record2 as $k2 => $v2) {
            $gift_record2[$k2]['icon'] = icon_url($v2['icon']);
            $gift_record2[$k2]['play_detail_url']=U('Game/detail',array('game_id'=>$v2['game_id']));
            $gift_record2[$k2]['game_name'] = str_replace('(苹果版)','',$v2['game_name']);
            $gift_record2[$k2]['game_name'] = str_replace('(安卓版)','',$gift_record2[$k2]['game_name']);
            if(($v2['end_time']<time() && $v2['end_time'] > 0) || empty($v2['novice_all'])){
                $gift_record2[$k2]['is_disabled'] = 1;
            }else{
                $gift_record2[$k2]['is_disabled'] = 0;
            }
            foreach ($gift_record1 as $k1 => $v1) {
                $v1['server_id']  = empty($v1['server_id'])?"":$v1['server_id'];
                $v1['server_name']  = empty($v1['server_id'])?"":$v1['server_name'];
                $v1['user_nickname']  = empty($v1['server_id'])?"":$v1['user_nickname'];
                if(($v1['end_time']<time() && $v1['end_time'] > 0) || empty($v1['novice_all'])){
                    $v1['is_disabled'] = 1;
                }else{
                    $v1['is_disabled'] = 0;
                }
                if($v1['game_id'] == $v2['game_id']){
                    $v1['gift_detail_url']=U('Gift/giftdetail',array('gift_id'=>$v1['gift_id']));
                    $gift_record2[$k2]['gblist'][] = $v1;
                }
            }

        }
        return $gift_record2;
    }
    private static function array_group_by($arr, $key)
    {
        $grouped = [];
        foreach ($arr as $value) {
            $grouped[$value[$key]][] = $value;
        }
        // Recursively build a nested grouping if more parameters are supplied
        // Each grouped array value is grouped according to the next sequential key
        if (func_num_args() > 2) {
            $args = func_get_args();
            foreach ($grouped as $key => $value) {
                $parms = array_merge([$value], array_slice($args, 2, func_num_args()));
                $grouped[$key] = call_user_func_array('array_group_by', $parms);
            }
        }
        return $grouped;
    }

    /**
     * 礼包详情
     * @param $gift_id
     * @return mixed
     * author: xmy 280564871@qq.com
     */
    public function getDetail($gift_id,$user_id=''){
        $time = NOW_TIME;
        $map['status'] = 1;
        $map['game_status'] = 1;
        $map['test_status'] = 1;
        $map['start_time'] = ['elt',$time];
        $map['_string'] = "end_time > {$time} or end_time = 0";
        $map['gb.id'] = $gift_id;
        $data = $this->alias('gb')
            ->field("game_id,gb.id as gift_id,gb.game_name,g.relation_game_id,g.relation_game_name,icon,giftbag_name,server_id,server_name,novice,gb.digest,
				gb.desribe,start_time,end_time,is_unicode,g.game_type_id,g.sdk_version,giftbag_version")
            ->join('tab_game g on g.id = gb.game_id')
            ->where($map)
            ->find();
        if(empty($data)){
            return false;
        }
        $omap['id'] = ['neq',$data['game_id']];
        $omap['relation_game_id'] = $data['relation_game_id'];
        $other = M('Game','tab_')->field('id')->where($omap)->find();
        if(!empty($other)){
            $data['isannios'] = $data['relation_game_id'];
        }
        $novice_arr = str2arr($data['novice'],',');
        $novice_num = count(array_filter($novice_arr));
        unset($data['novice']);
        $data['icon'] = icon_url($data['icon']);
        $giftnum = gift_recorded($data['game_id'],$gift_id);
        $data['play_url']=U('Game/open_game',array('game_id'=>$data['relation_game_id']));
        $mm = strtolower(MODULE_NAME);
        if($mm!='media'&&$mm!='mediawide'){
            $mm = 'mobile';
        }
        if($data['sdk_version']==3){
            $data['play_num']=$data['play_count'];
            $data['play_url']=get_http_url() . $_SERVER['HTTP_HOST']."/".$mm.".php?s=Game/open_game/game_id/".$data['game_id'];
        }else{
            $data['play_num']=$data['dow_num'];
            $data['play_url'] = $this->generateDownUrl($data['game_id']);
        }
        $data['novice_all'] = $giftnum['all'];//所有
        $data['novice_surplus'] = $giftnum['wei'];//剩余
        $data['received'] = 0;
        if($user_id){
            $grmap['gift_id'] = $gift_id;
            $grmap['game_id'] = $data['game_id'];
            $grmap['user_id'] = $user_id;
            $gift_record = M('GiftRecord','tab_')->where($grmap)->find();
            if($gift_record){
                $data['received'] = 1;
                $data['novice_num'] = $gift_record['novice'];
            }else{
                $data['received'] = 0;
            }
        }
        $data['xia_status']=check_game_sorue($data['game_id']);
        $data['game_type_name'] = get_game_type_name($data['game_type_id']);
        return $data;

    }

    //获取礼包
    public function getGift($giftid,$userid){
        $gamegift = $this->getDetail($giftid);
        if(!$gamegift){
            return array('code'=>'-1','msg'=>'礼包已过期');
        }
        $list=M('Gift_record','tab_');
        $info=$list->field('novice')->where(array("gift_id"=>$giftid,"user_id"=>$userid))->find();
        if($info) {
            $data=$info['novice'];
            return array('code'=>'-2','msg'=>'您已领取过，给别人一个领取的机会吧','nvalue'=>$data);
        }else {

            //判断是否为联盟站点
            if(empty(session('union_host'))){
                //判断是否开启公众号验证
                if(C('IS_GET_GIFT')==1){
                    //检查用户是否关注公众号
                    $is_wechat_subscribe = M('user','tab_')->where(['id'=>$userid])->getField('is_wechat_subscribe');
                    if(empty($is_wechat_subscribe)){
                        $qrCodeUrl = $this->getQrCode();
                        return ['code'=>'-4','msg'=>'您还未关注公众号,请关注公众号后领取','qrcodeurl'=>$qrCodeUrl];
                    }
                }
            }else{
                //联盟站点,读取站点配置
                $union_set = json_decode(session('union_host.union_set'),true);
                //判断是否开启公众号验证
                if($union_set['is_subscribe']==1){
                    //检查用户是否关注公众号
                    $is_wechat_subscribe = M('user','tab_')->where(['id'=>$userid])->getField('is_wechat_subscribe');
                    if(empty($is_wechat_subscribe)){
                        $qrCodeUrl = $this->getQrCode();
                        return ['code'=>'-4','msg'=>'您还未关注公众号,请关注公众号后领取','qrcodeurl'=>$qrCodeUrl];
                    }
                }
            }

            

            $n = $this->field('novice,end_time,is_unicode,unicode_num')->where(array('id'=>$giftid))->find();
            $y = $n['novice'];
            if(empty($y)||($n['is_unicode']==1&&$n['unicode_num']<=0)) {
                return array('code'=>'-3','msg'=>'你来晚了一步，礼包已被领取完了');
            }else{
                if($n['is_unicode'] != 1){
                    $novice = explode(",",$y);
                    $guid = $novice[0];
                    array_shift($novice);//删除一个元素
                }else{
                    $novice = $n['novice'];
                    $guid = $n['novice'];
                }
                $data['user_id']=$userid;
                $data['user_account']=get_user_entity($userid)['account'];
                $data['game_id']=$gamegift['game_id'];
                $data['game_name']=$gamegift['game_name'];
                $data['gift_id']=$gamegift['gift_id'];
                $data['gift_name']=$gamegift['giftbag_name'];
                $data['status']=0;
                $data['novice']=$guid;
                $data['create_time']=time();
                $list->add($data);
                if($n['is_unicode']!= 1){
                    $n = implode(",",$novice);
                    $this->where("id=$giftid")->setField('novice',$n);
                }else{
                    $this->where("id=$giftid")->setDec('unicode_num');
                }
                $giftnum = gift_recorded($data['game_id'],$data['gift_id']);
                $wei = round($giftnum['wei']/$giftnum['all'] * 100 ,2)."%";
                return array('code'=>'1','msg'=>'恭喜你，领取成功！','nvalue'=>$guid,'wei'=>$wei);
            }
        }
    }

    /**
     * 获取推荐礼包
     * @param $game_id
     * @param int $limit
     * @return mixed
     * author: xmy 280564871@qq.com
     */
    public function getRecommend($game_id,$limit=2){
        $map['game_id'] = $game_id;
        $map['status'] = 1;
        $map['giftbag_type'] = 1;
        $map['novice']=array('neq','');
        $time = NOW_TIME;
        $map['start_time'] = ['elt',$time];
        $map['_string'] = "end_time > {$time} or end_time = 0";
        $data = $this->field('id,giftbag_name,desribe')->where($map)->limit($limit)->select();
        return $data;
    }

    /**
     * 检查是否已经领取
     * @param $user_id
     * @param $gift_id
     * @return bool
     * author: xmy 280564871@qq.com
     */
    public function checkAccountGiftExist($user_id,$gift_id){
        $map['user_id'] = $user_id;
        $map['gift_id'] = $gift_id;
        $data = M("gift_record",'tab_')->where($map)->find();
        if (empty($data)){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 领取激活码
     * @param $user_id
     * @param $gift_id
     * author: yyh 280564871@qq.com
     */
    public function getNovice($user_id,$gift_id){
        //判断是否开启公众号验证
        if(C('IS_GET_GIFT')==1){
            //检查用户是否关注公众号
            $is_wechat_subscribe = M('user','tab_')->where(['id'=>$user_id])->getField('is_wechat_subscribe');
            if(empty($is_wechat_subscribe)){
                $qrCodeUrl = $this->getQrCode();
                return ['qrCodeUrl'=>$qrCodeUrl];
            }
        }


        $data = $this->find($gift_id);
        if($data['is_unicode']==1){
            $data1['id'] = $data['id'];
            $novice = $data['novice'];
            $data1['unicode_num'] = $data['unicode_num']-1;
        }else{
            $novice_str = $data['novice'];
            $novice_arr = str2arr($novice_str,",");
            if (empty($novice_arr)){
                return "";
            }
            $novice_arr = array_filter($novice_arr);
            $novice = array_pop($novice_arr);
            $data1['id'] = $data['id'];
            $data1['novice'] = arr2str($novice_arr,",");
        }
        $this->startTrans();
        $novice_result = $this->save($data1);

        //记录领取
        $record['game_id'] = $data['game_id'];
        $record['game_name'] = get_game_name($data['game_id']);
        $record['gift_id'] = $gift_id;
        $record['gift_name'] = $data['giftbag_name'];
        $record['status'] = 0;
        $record['novice'] = $novice;
        $record['user_id'] = $user_id;
        $user = get_user_entity($user_id);
        $record['user_account'] = $user['account'];
        $record['create_time'] = time();
        $record['start_time'] = $data['start_time'];
        $record['end_time'] = $data['end_time'];
        $record_result = M("gift_record",'tab_')->add($record);
        if($novice_result === false || $record_result === false){
            $this->rollback();
            return "";
        }else{
            $this->commit();
            return $novice;
        }
    }


    /**
     *
     * 获取微信公众号二维码
     * @return string
     */
    public function getQrCode(){

        $result = auto_get_access_token( 'access_token_validity.txt');
        $appid = C('wechat.appid');
        $appsecret = C('wechat.appsecret');

        //获取access_token
        if (!$result['is_validity']) {
            $auth = new WechatAuth($appid, $appsecret);
            $token = $auth->getAccessToken();
            $token['expires_in_validity'] = time() + $token['expires_in'];
            wite_text(json_encode($token), 'access_token_validity.txt');
            $result = auto_get_access_token('access_token_validity.txt');
        }
        $auth = new WechatAuth($appid, $appsecret,$result['access_token']);
        $user_id = session('user_auth.user_id');
        $res  = $auth->qrcodeCreate($user_id);
        $qrcodeurl = $auth->showqrcode($res['ticket']);
        return $qrcodeurl;
    }

    /**
     * 生成游戏下载链接
     * @param $game_id
     * @return string
     * author: xmy 280564871@qq.com
     */
    public static function generateDownUrl($game_id){
        $url = U('Down/down_file',['game_id'=>$game_id],'',true);
        return $url;
    }

}