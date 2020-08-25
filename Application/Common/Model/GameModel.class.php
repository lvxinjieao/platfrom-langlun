<?php
/**
 * Created by PhpStorm.
 * User: xmy 280564871@qq.com
 * Date: 2017/3/28
 * Time: 9:03
 */
namespace Common\Model;

class GameModel extends BaseModel{
	const ANDROID = 1;
	const IOS = 2;
	const H5G = 3;
	const DOWN_OFF = 0;
	const DOWN_ON = 1;

	/**
	 * 游戏列表 xia_status 是否可以下载
	 * @param string $map
	 * @param string $order
	 * @param int $p
	 * @return mixed
	 * author: yyh 280564871@qq.com
	 */
	public function getGameLists($map="",$order="g.sort desc,g.id desc",$p=0,$row=10,$user_id='',$group="g.id"){
	    $page = intval($p);
		$page = $page ? $page : 1; //默认显示第一页数据
		if($user_id){
			$promote_id = empty(get_table_param('user',['id'=>$user_id],'promote_id')['promote_id'])?0:1;
		}else{
			$promote_id = -1;
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
                $mm = 'mobile';
                break;
        }
        $map['for_platform'] = array('like','%'.$for_platform_num.'%');
		$map['g.game_status'] = 1;
		$map['g.test_status'] = 1;//测试状态
        if($promote_id == 1){
            $rebate_join = "and (r.promote_id = 1 or r.promote_id = -1)";
        }else{
            $rebate_join = "and (r.promote_id = 0 or r.promote_id = -1)";
        }
		$time = NOW_TIME;
		$data = $this->table('tab_game as g')
			->field('g.icon,g.cover,g.relation_game_id,g.relation_game_name as game_name,g.id,g.game_type_id,g.features,g.play_count,g.dow_num,g.game_score,g.dow_status,g.sdk_version,s.pack_name,IF(g.down_port=1,g.game_size,g.game_address_size) as game_size,g.bind_recharge_discount as discount,IFNULL(r.ratio,0) as ratio,count("relation_game_id") as isannios')
			//游戏原包
			->join("left join tab_game_source as s on s.game_id = g.id")
			//返利
			->join("left join tab_rebate r on r.game_id = g.id  {$rebate_join} and r.starttime < {$time} and endtime = 0 or endtime > {$time}")
			->where($map)
			->page($page, $row)
			->order($order)
			->group($group)
			->select();
		foreach ($data as $key => $val){
			//游戏名称显示字数
			if (mb_strlen($val['game_name'],'utf-8')>8&&strtolower(MODULE_NAME)!='app')
				$data[$key]['game_name'] = mb_substr($val['game_name'],0,8,'utf-8').'...';

			$data[$key]['icon'] = icon_url($val['icon']);
			//类型名称显示字数
			$gametypename = get_game_type_name($val['game_type_id']);
			if (mb_strlen($gametypename,'utf-8')>5&&strtolower(MODULE_NAME)!='app')
				$data[$key]['game_type_name'] = mb_substr($gametypename,0,5,'utf-8').'...';
			else
				$data[$key]['game_type_name'] = $gametypename;
			$data[$key]['cover'] = icon_url($val['cover']);
            $data[$key]['real_game_score'] = $val['game_score'];
			$data[$key]['game_score'] = round($val['game_score'] / 2);
			$data[$key]['collect_num']=collect_num($val['id']);
			if($val['sdk_version']==3){
				$data[$key]['play_num']=$val['play_count'];
     			$data[$key]['play_url']=get_http_url().$_SERVER['HTTP_HOST']."/".$mm.".php?s=Game/open_game/game_id/".$val['id'];
                $data[$key]['play_detail_url']=U('Game/detail',array('game_id'=>$val['id'],'ish5'=>1));
			}else{
                $data[$key]['play_detail_url']=U('Game/detail',array('game_id'=>$val['id']));
				$data[$key]['play_num']=$val['dow_num'];
				if($val['dow_status'] == self::DOWN_OFF){
					$data[$key]['play_url'] = "";
				}else{
					$data[$key]['play_url'] = $this::generateDownUrl($val['id']);
				}
			}
			unset($data[$key]['play_count']);
			unset($data[$key]['dow_num']);
            if (!empty($user_id)){
                $data[$key]['collect_status'] = $this->game_is_collect($data[$key]['id'],$user_id);
            }
            if(empty($val['game_size'])){
				$data[$key]['game_size']='0MB';
			}
			//折扣专区 接口
			if(strtolower(ACTION_NAME)=='get_discount_game_lists'){
//                if(!get_user_play($user_id,$val['id'])){
//                    unset($data[$key]);
//                    continue;
//                }
				$promote_id!=1?:-2;
				$proarr = [$promote_id,-1,'or'];
				$promotewelf = M('promoteWelfare','tab_')->field('first_discount,continue_discount')->where(['promote_id'=>$proarr,'recharge_status'=>1,'game_id'=>$val['id']])->find();
				$promotewelf['first_discount'] = $promotewelf['first_discount']>1?$promotewelf['first_discount']:'10.00';
				$promotewelf['continue_discount'] = $promotewelf['continue_discount']>1?$promotewelf['continue_discount']:'10.00';
				if($promotewelf['first_discount']=='10.00'&&$promotewelf['continue_discount']=='10.00'&&$val['discount']=='10.00'){
					unset($data[$key]);
					continue;
				}else{
					$data[$key]['first_discount'] = $promotewelf['first_discount'];
					$data[$key]['continue_discount'] = $promotewelf['continue_discount'];
				}
			}elseif(strtolower(ACTION_NAME)=='gamereclist'||strtolower(ACTION_NAME)=='gamegrouplist'||strtolower(ACTION_NAME)=='more_game'){
	            $giftdata = M('Giftbag','tab_')
	            			->field('ifnull(id,0) as gift_id,is_unicode,novice,unicode_num')
	            			->where(['giftbag_version'=>['like','%'.$val['sdk_version'].'%'],'status'=>1,'game_id'=>$val['id']])
	            			->select();
	            $data[$key]['gift_id'] = 0;
	            foreach ($giftdata as $key1 => $value1) {
	            	$y = $value1['novice'];
		            if((!empty($y) && $value1['is_unicode']!=1)||(!empty($y) && $value1['is_unicode']==1&&$value1['unicode_num']>0)) {
		            	$data[$key]['gift_id'] = 1;
		            	break;
		            }
	            }
			}
            $data[$key]['xia_status']=check_game_sorue($val['id']);
		}
		return $data;
	}


    /**
     * 游戏列表 xia_status 折扣游戏
     * @param string $map
     * @param string $order
     * @param int $p
     * @return mixed
     * author: yyh 280564871@qq.com
     */
    public function getGameLists1($map="",$order="g.sort desc,g.id desc",$p=0,$row=10,$user_id='',$group="g.id"){
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        if($user_id){
            $promote_id = empty(get_table_param('user',['id'=>$user_id],'promote_id')['promote_id'])?0:1;
        }else{
            $promote_id = -1;
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
                $mm = 'mobile';
                break;
        }
        $map['for_platform'] = array('like','%'.$for_platform_num.'%');
        $map['g.game_status'] = 1;
        $map['g.test_status'] = 1;//测试状态
        if($promote_id == 1){
            $rebate_join = "and (p.promote_id = -2 or p.promote_id = -1)";
        }else{
            $rebate_join = "and (p.promote_id = 0 or p.promote_id = -1)";
        }
        $time = NOW_TIME;
        $data = $this->table('tab_game as g')
            ->field('g.icon,g.cover,g.relation_game_id,g.relation_game_name as game_name,g.id,g.game_type_id,g.features,g.play_count,g.dow_num,g.game_score,g.dow_status,g.sdk_version,s.pack_name,IF(g.down_port=1,g.game_size,g.game_address_size) as game_size,g.bind_recharge_discount as discount,p.promote_status,p.first_discount,p.recharge_status,p.continue_discount,count("relation_game_id") as isannios,g.ratio')
            //游戏原包
            ->join("left join tab_game_source as s on s.game_id = g.id")
            //返利
            ->join("left join tab_promote_welfare p on p.game_id = g.id  {$rebate_join}")
            ->where($map)
            ->page($page, $row)
            ->order($order)
            ->group($group)
            ->select();
        foreach ($data as $key => $val){
            if($val['first_discount'] == null || $val['promote_status'] == 0){
                $data[$key]['first_discount'] = "10.00";
            }else{
                $data[$key]['first_discount'] =  $val['first_discount'];
            }
            if($val['continue_discount'] == null || $val['recharge_status'] == 0){
                $data[$key]['continue_discount'] = "10.00";
            }else{
                $data[$key]['continue_discount'] =  $val['continue_discount'];
            }
            //游戏名称显示字数
            if (mb_strlen($val['game_name'],'utf-8')>8&&strtolower(MODULE_NAME)!='app')
                $data[$key]['game_name'] = mb_substr($val['game_name'],0,8,'utf-8').'...';

            $data[$key]['icon'] = icon_url($val['icon']);
            //类型名称显示字数
            $gametypename = get_game_type_name($val['game_type_id']);
            if (mb_strlen($gametypename,'utf-8')>5&&strtolower(MODULE_NAME)!='app')
                $data[$key]['game_type_name'] = mb_substr($gametypename,0,5,'utf-8').'...';
            else
                $data[$key]['game_type_name'] = $gametypename;
            $data[$key]['cover'] = icon_url($val['cover']);
            $data[$key]['real_game_score'] = $val['game_score'];
            $data[$key]['game_score'] = round($val['game_score'] / 2);
            $data[$key]['collect_num']=collect_num($val['id']);
            if($val['sdk_version']==3){
                $data[$key]['play_num']=$val['play_count'];
                $data[$key]['play_url']=get_http_url().$_SERVER['HTTP_HOST']."/".$mm.".php?s=Game/open_game/game_id/".$val['id'];
                $data[$key]['play_detail_url']=U('Game/detail',array('game_id'=>$val['id'],'ish5'=>1));
            }else{
                $data[$key]['play_detail_url']=U('Game/detail',array('game_id'=>$val['id']));
                $data[$key]['play_num']=$val['dow_num'];
                if($val['dow_status'] == self::DOWN_OFF){
                    $data[$key]['play_url'] = "";
                }else{
                    $data[$key]['play_url'] = $this::generateDownUrl($val['id']);
                }
            }
            unset($data[$key]['play_count']);
            unset($data[$key]['dow_num']);
            if (!empty($user_id)){
                $data[$key]['collect_status'] = $this->game_is_collect($data[$key]['id'],$user_id);
            }
            if($val['game_size']=='0'){
                $data[$key]['game_size']='0MB';
            }
            $data[$key]['xia_status']=check_game_sorue($val['id']);
        }
        return $data;
    }

	/**
	 * 游戏下载信息
	 * @param $game_id
	 * @return mixed
	 * author: xmy 280564871@qq.com
	 */
	public function getGameDownInfo($game_id){
		$map['id'] = $game_id;
		$map['apply_status'] = 1;
		$map['game_status'] = 1;
		$data['game_info'] = $this->field('id,game_name,add_game_address,ios_game_address,sdk_version,down_port,dow_status')->where(['id'=>$game_id])->find();
		$data['packet'] = M("game_source",'tab_')->where(['game_id'=>$game_id])->find();
		return $data;
	}

	/**
	 * 增加下载次数
	 * @param $game_id
	 * author: xmy 280564871@qq.com
	 */
	public function addGameDownNum($game_id){
		$map['id'] = $game_id;
		$this->where($map)->setInc('dow_num');
	}
	/**
	 * [getGameListsCounts 游戏个数]
	 * @param  string  $map     [description]
	 * @param  string  $order   [description]
	 * @param  integer $p       [description]
	 * @param  integer $row     [description]
	 * @param  string  $modul   [description]
	 * @param  string  $user_id [description]
	 * @return [type]           [description]
	 * @author [yyh] <[<email address>]>
	 */
	public function getGameListsCounts($map="",$group="g.relation_game_id"){
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
		$map['g.test_status'] = 1;
		$data = $this->table('tab_game as g')
			->field('g.id')
			->join('tab_giftbag as gb on gb.game_id = g.id and gb.status = 1','left')
			->where($map)
			->group($group)
			->select();
		return count($data);
	}

	/**
	 * [getHotGame 热门游戏]
	 * @param  string  $map     [description]
	 * @param  string  $order   [description]
	 * @param  integer $limit   [description]
	 * @param  string  $user_id [description]
	 * @return [type]           [description]、
	 * @author [yyh] <[<email address>]>
	 */
	public function getHotGame($map="",$order="g.sort desc",$limit=10,$user_id=''){
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
                $mm = 'mobile';
                break;
        }
        $map['for_platform'] = array('like','%'.$for_platform_num.'%');
        $map['g.game_status'] = 1;
        $map['g.test_status'] = 1;
        $data = $this->table('tab_game as g')
            ->field('g.icon,g.dow_status,g.sdk_version,g.play_count,g.dow_num,relation_game_id,g.cover,g.relation_game_name as game_name,g.id,g.game_type_id,g.features,ifnull(gb.id,0) as gift_id')
            ->join('tab_giftbag as gb on gb.game_id = g.id and gb.status = 1','left')
            ->where($map)
            ->limit($limit)
            ->order($order)
            ->group("g.relation_game_id")
            ->select();
        foreach ($data as $key => $val){
            $data[$key]['icon'] = icon_url($val['icon']);
            $data[$key]['game_type_name'] = get_game_type_name($val['game_type_id']);
            $data[$key]['cover'] = icon_url($val['cover']);
            $data[$key]['game_score'] = round($val['game_score'] / 2);
            $data[$key]['collect_num']=collect_num($val['id']);
            if($val['sdk_version']==3){
                $data[$key]['play_detail_url']=get_http_url().$_SERVER['HTTP_HOST'].U('Game/detail',array('game_id'=>$val['relation_game_id'],'ish5'=>1));
            	$data[$key]['play_num']=$val['play_count'];
     			$data[$key]['play_url']=get_http_url().$_SERVER['HTTP_HOST']."/".$mm.".php?s=Game/open_game/game_id/".$val['id'];
			}else{
                $data[$key]['play_detail_url']=get_http_url().$_SERVER['HTTP_HOST'].U('Game/detail',array('game_id'=>$val['relation_game_id']));
				$data[$key]['play_num']=$val['dow_num'];
				if($val['dow_status'] == 0){
					$data[$key]['play_url'] = "";
				}else{
					$data[$key]['play_url'] = $this::generateDownUrl($val['id']);
				}
			}
			unset($data[$key]['play_count']);
			unset($data[$key]['dow_num']);
            $data[$key]['game_gift'] = M("Giftbag","tab_")->where(array('game_id'=>$data[$key]['id']))->find()?1:0;
            $data[$key]['xia_status']=check_game_sorue($val['id']);
            if (!empty($user_id)){
                $data[$key]['collect_status'] = $this->game_is_collect($data[$key]['id'],$user_id);
            }
        }
        return $data;
    }
    /**
     * [game_is_collect 游戏是否收藏]
     * @param  [type] $game_id [description]
     * @param  [type] $user_id [description]
     * @return [type]          [description]
	 * @author [yyh] <[<email address>]>
     */
	public function game_is_collect($game_id,$user_id){
	    $map['g.id'] = $game_id;
        $map['g.game_status'] = 1;
        $map['g.test_status'] = 1;
        $map['b.user_id'] = $user_id;
        $map['b.status'] = 1;
        $data = $this->alias('g')
            ->field('g.icon,g.game_name,g.id,g.game_type_id,g.features,g.screenshot,g.introduction,ifnull(b.status,0) as collect_status')
            ->join("tab_user_behavior as b on b.game_id = g.id and b.status = 1",'left')
            ->where($map)
            ->find();
        if ($data){
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * [getGroupLists 游戏类型分组]
     * @param  [type] $map [description]
     * @return [type]      [description]
     * @author [yyh] <[<email address>]>
     */
	public function getGroupLists($map){
		$map['g.game_status'] = 1;
		$map['g.test_status'] = 1;
		$map['gt.status'] = 1;
		$jointable = "(SELECT id,relation_game_name as game_name,sdk_version,icon,game_status,test_status,game_type_id FROM tab_game ORDER BY sort DESC ,id desc)  as g on g.game_type_id = gt.id";
		$data = M('GameType as gt','tab_')
			->field('count(g.id) as counts,gt.type_name,gt.id as type_id,g.game_name,g.icon,gt.create_time')
			->join($jointable)
			->where($map)
			->group("gt.id")
			->order('counts desc,create_time desc')
			->select();
		foreach ($data as $key => $val){
			$data[$key]['icon'] = icon_url($val['icon']);
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
		$allgame = $this->alias('g')
					->field('g.id')
					->join('tab_game_type as gt on g.game_type_id = gt.id')
					->where($map)
					->group('g.id')
					->select();
		if(!$allgame){
			return false;
		}
		$dd['all'] = count($allgame);
		$dd['group'] = $data;
		return $dd;
	}
	/**
	 * [gameDetail 游戏详情]
	 * @param  [type]  $game_id [description]
	 * @param  integer $user_id [description]
	 * @return [type]           [description]
	 * @author [yyh] <[<email address>]>
	 */
	public function gameDetail($game_id,$user_id=0){
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
                $mm = 'mobile';
                break;
        }
        //$map['for_platform'] = array('like','%'.$for_platform_num.'%');
		$map['g.id'] = $game_id;
		$map['g.game_status'] = 1;
		$map['g.test_status'] = 1;
		if($user_id){
			$promote_id = empty(get_table_param('user',['id'=>$user_id],'promote_id')['promote_id'])?0:1;
		}else{
			$promote_id = -1;
		}
        if($promote_id == 1){
            $rebate_join = "and (r.promote_id = 1 or r.promote_id = -1)";
        }else{
            $rebate_join = "and (r.promote_id = 0 or r.promote_id = -1)";
        }
		$time = NOW_TIME;
		$data = $this->alias('g')
				->field('g.icon,g.relation_game_name as game_name,g.id,g.game_type_id,g.features,g.play_count,g.dow_num,g.screenshot,g.introduction,ifnull(b.status,0) as collect_status,and_dow_address,ios_dow_address,IF(g.down_port=1,g.game_size,g.game_address_size) as game_size,g.dow_status,g.sdk_version,g.bind_recharge_discount as discount,IFNULL(r.ratio,0) as ratio,g.relation_game_id,g.category')
				->join("tab_user_behavior as b on b.game_id = g.id and b.user_id = {$user_id} and b.status = 1",'left')
					//游戏原包
				->join("left join tab_game_source as s on s.game_id = g.id")
				//返利
				->join("left join tab_rebate r on r.game_id = g.id  {$rebate_join} and r.starttime < {$time} and endtime = 0 or endtime > {$time}")
				->where($map)
				->find();
        $data['category'] = get_game_opentype_name($data['category']);
		if(empty($data)){
			return false;
		}elseif(ACTION_NAME!='open_game'){
			$data['icon'] = icon_url($data['icon']);
			$gametypename = get_game_type_name($data['game_type_id']);
			if (mb_strlen($gametypename,'utf-8')>5&&strtolower(MODULE_NAME)!='app')
				$data['game_type_name'] = '（'.mb_substr($gametypename,0,5,'utf-8').'...';
			else
				$data['game_type_name'] = ''.$gametypename.'';
			$data['screenshots'] = $this->screenshots($data['screenshot']);
			$data['collect_num']=collect_num($data['id']);
			if($data['sdk_version']==3){
				$data['play_num']=$data['play_count'];
     			$data['play_url']=get_http_url() . $_SERVER['HTTP_HOST']."/".$mm.".php?s=Game/open_game/game_id/".$data['id'];
			}else{
				$data['play_num']=$data['dow_num'];
				if($data['dow_status'] == 0){
					$data['play_url'] = "";
				}else{
					$data['play_url'] = $this::generateDownUrl($data['id']);
				}
			}
			if(empty($data['game_size']))$data['game_size'] = '0MB';
			unset($data[$key]['play_count']);
			unset($data[$key]['dow_num']);
			$data['xia_status']=check_game_sorue($data['id']);
			return $data;
		}else{
			return $data;
		}
	}
	public function searchgame($map,$user_id=''){
		$order = 'g.sort desc';
		if($user_id){
			$promote_id = empty(get_table_param('user',['id'=>$user_id],'promote_id')['promote_id'])?0:1;
		}else{
			$promote_id = -1;
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
                $mm = 'mobile';
                break;
        }
        $map['for_platform'] = array('like','%'.$for_platform_num.'%');
		$map['g.game_status'] = 1;
		$map['g.test_status'] = 1;
		$rebate_join = "and (r.promote_id = {$promote_id} or r.promote_id = -1)";
		$time = NOW_TIME;
		$data = $this->table('tab_game as g')
			->field('g.icon,g.cover,g.relation_game_id,g.relation_game_name as game_name,g.id,g.game_type_id,g.features,g.play_count,g.dow_num,g.game_score,g.dow_status,g.sdk_version,s.pack_name,IF(g.down_port=1,g.game_size,g.game_address_size) as game_size,g.bind_recharge_discount as discount,IFNULL(r.ratio,0) as ratio')
			//游戏原包
			->join("left join tab_game_source as s on s.game_id = g.id")
			//返利
			->join("left join tab_rebate r on r.game_id = g.id  {$rebate_join} and r.starttime < {$time} and endtime = 0 or endtime > {$time}")
			->where($map)
			->order($order)
			->group("g.id")
			->select();
		foreach ($data as $key => $val){
			$data[$key]['icon'] = icon_url($val['icon']);
			$data[$key]['game_type_name'] = get_game_type_name($val['game_type_id']);
			$data[$key]['cover'] = icon_url($val['cover']);
			$data[$key]['game_score'] = round($val['game_score'] / 2);
			$data[$key]['play_detail_url']=U('Game/detail',array('game_id'=>$val['id']));
			if($val['sdk_version']==3){
				$data[$key]['play_num']=$val['play_count'];
     			$data[$key]['play_url']=get_http_url() . $_SERVER['HTTP_HOST']."/".$mm.".php?s=Game/open_game/game_id/".$val['id'];
			}else{
				$data[$key]['play_num']=$val['dow_num'];
				if($val['dow_status'] == self::DOWN_OFF){
					$data[$key]['play_url'] = "";
				}else{
					$data[$key]['play_url'] = $this::generateDownUrl($val['id']);
				}
			}
			unset($data[$key]['play_count']);
			unset($data[$key]['dow_num']);
			$data[$key]['xia_status']=check_game_sorue($val['id']);
		}
		return $data;
	}
	/**
	 * [myGameCollect 用户收藏行为]
	 * @param  [type] $user [description]
	 * @param  [type] $type [-1取消收藏，1已收藏]
	 * @param  [type] $is_page [返回分页数据 0否，1是]
	 * @return [type]       [description]
	 */
	public function myGameCollect($user,$type,$p,$map=[],$row=10,$is_page=0){
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
		$map['g.test_status'] = 1;
		$map['b.status'] = $type;
        $page = $p ? $p :1;
		$mm = strtolower(MODULE_NAME);
        if($mm!='media'&&$mm!='mediawide'){
            $mm = 'mobile';
        }
		if($type==1){
			$data = $this->table('tab_user_behavior as b')
						->field('b.id as bid,g.icon,g.relation_game_name as game_name,g.id,g.sdk_version,g.game_type_id,g.play_count,g.dow_num,g.sdk_version,g.features,g.screenshot,g.introduction,ifnull(b.status,0) as collect_status,g.dow_status,IF(g.down_port=1,g.game_size,g.game_address_size) as game_size')
						->join('tab_game g on g.id = b.game_id and b.user_id = '.$user)
						->where($map)
						->group("g.id")
						->order('b.create_time desc')
                        ->page($page,$row)
						->select();

			if($is_page){
			    $count = count($this->table('tab_user_behavior as b')
						->field('b.id as bid,g.icon,g.relation_game_name as game_name,g.id,g.sdk_version,g.game_type_id,g.features,g.screenshot,g.introduction,ifnull(b.status,0) as collect_status,g.dow_status')
						->join('tab_game g on g.id = b.game_id and b.user_id = '.$user)
						->where($map)
						->group("g.id")
						->select());
			}
		}
		foreach ($data as $key => $val){
			$data[$key]['icon'] = icon_url($val['icon']);
			
			$gametypename = get_game_type_name($val['game_type_id']);
			if (mb_strlen($gametypename,'utf-8')>5&&strtolower(MODULE_NAME)!='app')
				$data[$key]['game_type_name'] = mb_substr($gametypename,0,5,'utf-8').'...';
			else
				$data[$key]['game_type_name'] = $gametypename;
			
			
            $data[$key]['game_type_count'] = M('Game','tab_')->where(array('game_type_id'=>$data[$key]['game_type_id'],'game_status'=>1,'test_status'=>1,'sdk_version'=>$val['sdk_version']))->count();
			$data[$key]['cover'] = icon_url($val['cover']);
			if($val['sdk_version']==3){
				$data[$key]['play_num']=$val['play_count'];
			}else{
				$data[$key]['play_num']=$val['dow_num'];
			}
			unset($data[$key]['play_count']);
			unset($data[$key]['dow_num']);
            //$data[$key]['xia_status']=check_game_sorue($val['id']);
			if(strtolower(MODULE_NAME)=='app'){
				$data[$key]['play_detail_url']='http://' . $_SERVER['HTTP_HOST']."/".$mm.".php?s=Game/detail/game_id/".$val['id'];
			}else{
			    if($data[$key]['sdk_version']=='3'){//H5游戏
                    $data[$key]['play_url']=get_http_url() . $_SERVER['HTTP_HOST']."/".$mm.".php?s=Game/open_game/game_id/".$val['id'];
                    $data[$key]['play_detail_url']=U('Game/detail',array('game_id'=>$val['id'],'ish5'=>'1'));
                }else{
                    if($val['dow_status'] == self::DOWN_OFF){
                        $data[$key]['play_url'] = "";
                    }else{
                        $data[$key]['play_url'] = $this::generateDownUrl($val['id']);
                    }
                    $data[$key]['play_detail_url']=U('Game/detail',array('game_id'=>$val['id']));
                }

			}
			if(empty($val['game_size'])){
                $data[$key]['game_size'] = "0MB";
            }
            $data[$key]['xia_status']=check_game_sorue($val['id']);
		}
		if($is_page){
		    $data['list'] = $data;
		    $data['count'] = $count;
		}
		return $data;
	}
	/**
	 * 我的足迹
	 * @param  [type]  $user    [description]
	 * @param  [type]  $type    [description]
	 * @param  [type]  $p       [description]
	 * @param  integer $row     [description]
	 * @param  integer $is_page [description]
	 * @return [type]           [description]
	 * @author [yyh] <[<email address>]>
	 */
	public function myGameFoot($user,$type,$p,$map=[],$row=10,$is_page=0){
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
		$map['g.test_status'] = 1;
		$map['b.status'] = $type;
        $page = $p ? $p :1;
        // $row = 10;
		if($type==2){
			$data = $this->table('tab_user_behavior as b')
						->field('b.id as bid,g.icon,g.relation_game_name as game_name,g.id,g.relation_game_id,g.play_count,g.dow_num,g.sdk_version,g.game_type_id,g.features,g.screenshot,g.introduction,ifnull(b.status,0) as collect_status,date_format(FROM_UNIXTIME(b.create_time),"%m月%d日") AS date')
						->join('tab_game g on g.id = b.game_id and b.user_id = '.$user)
						->where($map)
						 ->group("g.id,date")
//						 ->group("g.id")
						->order('date desc')
                        ->page($page,$row)
						->select();
			if($is_page){
			    $count = count($this->table('tab_user_behavior as b')
			    ->field('b.id as bid,g.icon,g.relation_game_name as game_name,g.id,g.sdk_version,g.game_type_id,g.features,g.screenshot,g.introduction,ifnull(b.status,0) as collect_status,date_format(FROM_UNIXTIME(b.create_time),"%m月%d日") AS date')
			    ->join('tab_game g on g.id = b.game_id and b.user_id = '.$user)
			    ->where($map)
//			    ->group("g.id,date")
                ->group("g.id")
			    ->select());
			}
		}
		foreach ($data as $key => $value) {
			$value['icon'] = icon_url($value['icon']);
			$value['game_type_name'] = get_game_type_name($value['game_type_id']);
            $value['game_type_count'] = M('Game','tab_')->where(array('game_type_id'=>$data[$key]['game_type_id'],'game_status'=>1,'test_status'=>1))->count();
			$value['cover'] = icon_url($value['cover']);
			if($value['sdk_version']==3){
				$value['play_num']=$value['play_count'];
			}else{
				$value['play_num']=$value['dow_num'];
			}
			unset($value['play_count']);
			unset($value['dow_num']);
			if($value['sdk_version']=='3'){
                $value['play_detail_url']=U('Game/detail',array('game_id'=>$value['relation_game_id'],'ish5'=>'1'));
            }else{
                $value['play_detail_url']=U('Game/detail',array('game_id'=>$value['relation_game_id']));
            }

			$newdata[$value['date']][]=$value;
		}
		if($is_page){
		    $newdata['list'] = $newdata;
		    $newdata['count'] = $count;
		}
		return $newdata;
	}
	/**
	 * 获取绑币充值折扣
	 * @param $game_id
	 * @return int
	 * author: yyh
	 */
	public function getBindRechargeDiscount($game_id){
		$data = M('Game','tab_')->field("bind_recharge_discount")->find($game_id);
		$discount = empty($data['bind_recharge_discount']) ? 10 : $data['bind_recharge_discount'];
		return $discount;
	}
	/**
	 * [optionBehavior 行为操作]
	 * @param  [type] $user [description]
	 * @param  [type] $type [1收藏  2足迹]
	 * @param  [type] $map  [description]
	 * @return [type]       [description]
	 */
	public function optionBehavior($user,$type,$map){
		if($type==1){
			$map['status'] = 1;
			$map['user_id'] = $user;
			$data['status'] = -1;
			$data['update_time'] = time();
			$res = M('user_behavior','tab_')
						->where($map)
						->save($data);
			return $res;
		}elseif($type==2){
            $map['status'] = 2;
            $map['user_id'] = $user;

            $game_ids = M('user_behavior','tab_')->where($map)->getField('game_id',true);
            $map['game_id'] = ['in',$game_ids];
            unset($map['id']);

			$data['status'] = -2;
			$data['update_time'] = time();
			$res = M('user_behavior','tab_')
						->where($map)
						->save($data);
			return $res;
		}elseif($type==3){
            //下载记录删除
            $map['user_id'] = $user;
            $data = M('downRecord','tab_')->where($map)->delete();
            return $data;
        }

	}
	/**
     * 游戏截图
     * @param  string $str 游戏截图ID
     * @return 游戏截图的URL
     * @author lyf
     */
    protected function screenshots($str){
        $data = explode(',', $str);
        $screenshots = array();
        foreach ($data as $key => $value) {
            $screenshots[$key] = icon_url($value);
        }
        return $screenshots;
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
	/**
	 * [downRecord 记录下载游戏]
	 * @param  [type] $user_id      [description]
	 * @param  [type] $user_account [description]
	 * @param  [type] $game_id      [description]
	 * @return [type]               [description]
	 * yyh
	 */
	public function downRecord($user_id,$user_account,$game_id){
	    $map['user_id'] = $user_id;
	    $map['game_id'] = $game_id;
	    $game =  M('downRecord','tab_')->field('id')->where($map)->find();
	    if($game){
	        return $game['id'];
        }
		$data['user_id'] = $user_id;
		$data['user_account'] = $user_account;
		$data['game_id'] = $game_id;
		$data['game_name'] = get_game_name($game_id);
		$data['create_time'] = time();
		$res = M('downRecord','tab_')->add($data);
		return $res;
	}
	/**
	 * [downRecord 下载游戏记录]
	 * @param  [type] $user_id      [description]
	 * @param  [type] $user_account [description]
	 * @param  [type] $game_id      [description]
	 * @return [type]               [description]
	 */
	public function downRecordLists($sdk_version=false,$user_id,$p=1,$row=10){
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
                $mm = 'mobile';
                break;
        }
        $map['tab_game.for_platform'] = array('like','%'.$for_platform_num.'%');
	    $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
		$map['user_id'] = $user_id;
		$map['game_status'] = 1;
		if($sdk_version!==false){
			$map['sdk_version'] = $sdk_version;
		}
		$data = M('downRecord','tab_')
					->field('tab_down_record.id,tab_down_record.id as bid,tab_down_record.user_id,tab_down_record.user_account,tab_down_record.game_id,tab_game.relation_game_name as game_name,tab_game.relation_game_id,IF(tab_game.down_port=1,tab_game.game_size,tab_game.game_address_size) as game_size,tab_game.sdk_version,tab_game.icon,tab_game.cover,tab_game.features,tab_game.dow_num,tab_game.dow_status')
					->where($map)
					->join('tab_game on tab_game.id = tab_down_record.game_id')
					->group('game_id')
					->order('id desc')
					->page($page,$row)
					->select();
		foreach ($data as $key => $val){
			$data[$key]['icon'] = icon_url($val['icon']);
			$data[$key]['play_num'] = icon_url($val['dow_num']);
			$data[$key]['cover'] = icon_url($val['cover']);
			$data[$key]['play_detail_url'] = U('Game/detail',array('game_id'=>$val['relation_game_id']));
            $data[$key]['xia_status']=check_game_sorue($val['game_id']);
            if($val['sdk_version']==3){
                $data[$key]['play_num']=$val['play_count'];
                $data[$key]['play_url']=get_http_url().$_SERVER['HTTP_HOST']."/".$mm.".php?s=Game/open_game/game_id/".$val['game_id'];
            }else{
                $data[$key]['play_num']=$val['dow_num'];
                if($val['dow_status'] == self::DOWN_OFF){
                    $data[$key]['play_url'] = "";
                }else{
                    $data[$key]['play_url'] = $this::generateDownUrl($val['game_id']);
                }
            }
		}
		return $data;
	}
	/**
	 * [downRecord 删除下载游戏记录]
	 * @param  [type] $user_id      [description]
	 * @param  [type] $user_account [description]
	 * @param  [type] $game_id      [description]
	 * @return [type]               [description]
	 */
	public function downRecordDel($user_id,$record_id){
		$map['user_id'] = $user_id;
		$map['id'] = ['in','0,'.$record_id];

        $game_ids = M('downRecord','tab_')->where($map)->getField('game_id',true);
        if(!$game_ids){
            return 0;
        }
        $map['game_id'] = ['in',$game_ids];
        unset($map['id']);

		$data = M('downRecord','tab_')->where($map)->delete();
		return $data;
	}
	/**
	 * 绑币充值游戏列表
	 * @param $user_id
	 * @return mixed
	 * author: xmy 280564871@qq.com
	 */
	public function getUserRechargeGame($user_id,$version){
		$map['user_id'] = $user_id;
		$map['g.sdk_version'] = array('in',array($version,3));
		$data = M("user_play","tab_")->alias("p")
            ->field("p.game_name,g.id,g.bind_recharge_discount as discount")
			->join("right join tab_game g on g.id = p.game_id")
			->where($map)->where('g.game_status=1')->select();
		return $data;
	}
}