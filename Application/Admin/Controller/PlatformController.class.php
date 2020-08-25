<?php

namespace Admin\Controller;
use Think\Model;
use User\Api\UserApi as UserApi;

/**
 * 后台首页控制器
 * @author yyh
 */
class PlatformController extends ThinkController {
	function game_statistics($p=0){
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $arraypage=$page;
        $row = 10;
		$user=M('User','tab_');
		$map['fgame_id']=array('gt',0);
		if(isset($_REQUEST['timestart'])&&isset($_REQUEST['timeend'])){
            $map['register_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),strtotime($_REQUEST['timeend'])+24*60*60-1));
            unset($_REQUEST['timestart']);unset($_REQUEST['timeend']);
        }
        if(isset($_REQUEST['start'])&&isset($_REQUEST['end'])){
            $map['register_time'] =array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));
            unset($_REQUEST['start']);unset($_REQUEST['end']);
        }
        if(isset($_REQUEST['game_name'])&&$_REQUEST['game_name']!=''){
            $map['fgame_name'] =$_REQUEST['game_name'];
            unset($_REQUEST['fgame_name']);
        }
		$data=$user
			->field('fgame_name,fgame_id,date_format(FROM_UNIXTIME( register_time),"%Y-%m-%d") AS time, count(id) as count')
			->where($map)
			->group('fgame_id')
			->order('count desc')
			->select();
        $count=count($data);
		foreach ($data as $key => $value) {
			static $i=0;
			$i++;
			$data[$key]['rand']=$i;
			$adata=$this->day_data('User',array('fgame_id'=>$value['fgame_id']));
			$data[$key]['today']=$adata['today'];
			$data[$key]['week']=$adata['week'];
			$data[$key]['mounth']=$adata['mounth'];
		}
		$total=$this->data_total($data);
		$this->assign('total',$total);
		if($_REQUEST['data_order']!=''){
            $data_order=reset(explode(',',$_REQUEST['data_order']));
            $data_order_type=end(explode(',',$_REQUEST['data_order']));
            $this->assign('userarpu_order',$data_order);
            $this->assign('userarpu_order_type',$data_order_type);
        }
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%UP_PAGE% %FIRST% %LINK_PAGE% %END% %DOWN_PAGE% %HEADER%');
            $this->assign('_page', $page->show());
        }
        $data=my_sort($data,$data_order_type,(int)$data_order);
		$size=$row;//每页显示的记录数
        $pnum = ceil(count($data) / $size); //总页数，ceil()函数用于求大于数字的最小整数
        //用array_slice(array,offset,length) 函数在数组中根据条件取出一段值;array(数组),offset(元素的开始位置),length(组的长度)
        $data = array_slice($data, ($arraypage-1)*$size, $size);
		$this->assign('list_data',$data);
        $this->display();
    }





    function gamepay_statistics($p=0){
        if(I('isbd')==1){
            $isbdpw['pay_way'] = array('neq',-1);
        }else{
            $isbdpw['id'] = array('gt',0);
        }
		$page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $arraypage=$page;
        $row = 10;
		$spend=M('Spend','tab_');
        $deposit = M('Deposit',"tab_");
		$map['game_id']=array('gt',0);
		if(isset($_REQUEST['timestart'])&&isset($_REQUEST['timeend'])){
            $map['pay_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),strtotime($_REQUEST['timeend'])+24*60*60-1));
            unset($_REQUEST['timestart']);unset($_REQUEST['timeend']);
        }
        if(isset($_REQUEST['start'])&&isset($_REQUEST['end'])){
            $map['pay_time'] =array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));
            unset($_REQUEST['start']);unset($_REQUEST['end']);
        }
        // var_dump($_REQUEST);exit;
        if(isset($_REQUEST['game_name'])&&$_REQUEST['game_name']!=''){
            $map['game_name'] =$_REQUEST['game_name'];
            unset($_REQUEST['game_name']);
        }
        $map['pay_status']=1;
		$data=$spend
			->field('game_name,game_id,date_format(FROM_UNIXTIME(pay_time),"%Y-%m-%d") AS time, sum(pay_amount) as count')
			->where($map)
            ->where($isbdpw)
			->group('game_id')
			->order('count desc')
			->select();
        $count=count($data);
		foreach ($data as $key => $value) {
			static $i=0;
			$i++;
			$data[$key]['rand']=$i;
			$adata=$this->day_data('Spend',array('game_id'=>$value['game_id'],'pay_status'=>1));
			$data[$key]['today']=$adata['today']==''?0:$adata['today'];
			$data[$key]['week']=$adata['week']==''?0:$adata['week'];
			$data[$key]['mounth']=$adata['mounth']==''?0:$adata['mounth'];
		}
		$total=$this->data_total($data);
		$this->assign('total',$total);
		if($_REQUEST['data_order']!=''){
            $data_order=reset(explode(',',$_REQUEST['data_order']));
            $data_order_type=end(explode(',',$_REQUEST['data_order']));
            $this->assign('userarpu_order',$data_order);
            $this->assign('userarpu_order_type',$data_order_type);
        }
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%UP_PAGE% %FIRST% %LINK_PAGE% %END% %DOWN_PAGE% %HEADER%');
            $this->assign('_page', $page->show());
        }
        $data=my_sort($data,$data_order_type,(int)$data_order);
        $size=$row;//每页显示的记录数
        $pnum = ceil(count($data) / $size); //总页数，ceil()函数用于求大于数字的最小整数
        //用array_slice(array,offset,length) 函数在数组中根据条件取出一段值;array(数组),offset(元素的开始位置),length(组的长度)
        $data = array_slice($data, ($arraypage-1)*$size, $size);
		$this->assign('list_data',$data);
        $this->display();
    }







    function resway_statistics($p=0){
    	$page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $arraypage=$page;
        $row = 10;
		$user=M('User','tab_');
		if(isset($_REQUEST['timestart'])&&isset($_REQUEST['timeend'])){
            $map['register_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),strtotime($_REQUEST['timeend'])+24*60*60-1));
            unset($_REQUEST['timestart']);unset($_REQUEST['timeend']);
        }
        if(isset($_REQUEST['start'])&&isset($_REQUEST['end'])){
            $map['register_time'] =array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));
            unset($_REQUEST['start']);unset($_REQUEST['end']);
        }
        if(isset($_REQUEST['register_way'])&&$_REQUEST['register_way']!=''){
            $map['register_way'] =$_REQUEST['register_way'];
            unset($_REQUEST['register_way']);
        }
		$data=$user
			->field('register_way,date_format(FROM_UNIXTIME(register_time),"%Y-%m-%d") AS time, count(id) as count')
			->where($map)
			->group('register_way')
			->order('count desc')
			->select();
        $count=count($data);
		foreach ($data as $key => $value) {
			static $i=0;
			$i++;
			$data[$key]['rand']=$i;
			$adata=$this->day_data('User',array('register_way'=>$value['register_way']));
			$data[$key]['today']=$adata['today']==''?0:$adata['today'];
			$data[$key]['week']=$adata['week']==''?0:$adata['week'];
			$data[$key]['mounth']=$adata['mounth']==''?0:$adata['mounth'];
		}
		$total=$this->data_total($data);
		if($_REQUEST['data_order']!=''){
            $data_order=reset(explode(',',$_REQUEST['data_order']));
            $data_order_type=end(explode(',',$_REQUEST['data_order']));
            $this->assign('userarpu_order',$data_order);
            $this->assign('userarpu_order_type',$data_order_type);
        }
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%UP_PAGE% %FIRST% %LINK_PAGE% %END% %DOWN_PAGE% %HEADER%');
            $this->assign('_page', $page->show());
        }
        $data=my_sort($data,$data_order_type,(int)$data_order);
        $size=$row;//每页显示的记录数
        $pnum = ceil(count($data) / $size); //总页数，ceil()函数用于求大于数字的最小整数
        //用array_slice(array,offset,length) 函数在数组中根据条件取出一段值;array(数组),offset(元素的开始位置),length(组的长度)
        $data = array_slice($data, ($arraypage-1)*$size, $size);
		$this->assign('list_data',$data);
		$this->assign('total',$total);
        $this->display();
    }





    function payway_statistics($p=0){
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $arraypage=$page;
        $row = 10;
        $deposit = M('Deposit',"tab_");
        $user=M('User','tab_');
		$spend=M('Spend','tab_');
		if(isset($_REQUEST['timestart'])&&isset($_REQUEST['timeend'])){
            $map['pay_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),strtotime($_REQUEST['timeend'])+24*60*60-1));
            unset($_REQUEST['timestart']);unset($_REQUEST['timeend']);
        }
        if(isset($_REQUEST['start'])&&isset($_REQUEST['end'])){
            $map['pay_time'] =array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));
            unset($_REQUEST['start']);unset($_REQUEST['end']);
        }
        if(isset($_REQUEST['pay_way'])&&$_REQUEST['pay_way']!=''){
            $map['pay_way'] =$_REQUEST['pay_way'];
            unset($_REQUEST['pay_way']);
        }
        $map['pay_status']=1;
		$data=$spend
			->field('pay_way,date_format(FROM_UNIXTIME(pay_time),"%Y-%m-%d") AS time, sum(pay_amount) as count')
			->where($map)
			->group('pay_way')
			->order('count desc')
			->select();
        $count=count($data);
		foreach ($data as $key => $value) {
			static $i=0;
			$i++;
			$data[$key]['rand']=$i;
			$adata=$this->day_data('Spend',array('pay_way'=>$value['pay_way'],'pay_status'=>1));
			$data[$key]['today']=$adata['today']==''?0:$adata['today'];
			$data[$key]['week']=$adata['week']==''?0:$adata['week'];
			$data[$key]['mounth']=$adata['mounth']==''?0:$adata['mounth'];
		}
		$total=$this->data_total($data);
		$this->assign('total',$total);
        if($_REQUEST['data_order']!=''){
            $data_order=reset(explode(',',$_REQUEST['data_order']));
            $data_order_type=end(explode(',',$_REQUEST['data_order']));
            $this->assign('userarpu_order',$data_order);
            $this->assign('userarpu_order_type',$data_order_type);
        }
		if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%UP_PAGE% %FIRST% %LINK_PAGE% %END% %DOWN_PAGE% %HEADER%');
            $this->assign('_page', $page->show());
        }
        $data=my_sort($data,$data_order_type,(int)$data_order);
        $size=$row;//每页显示的记录数
        $pnum = ceil(count($data) / $size); //总页数，ceil()函数用于求大于数字的最小整数
        //用array_slice(array,offset,length) 函数在数组中根据条件取出一段值;array(数组),offset(元素的开始位置),length(组的长度)
        $data = array_slice($data, ($arraypage-1)*$size, $size);
		$this->assign('list_data',$data);
        $this->display();
    }





    function promote_statistics($p=0){
    	$page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $arraypage=$page;
        $row = intval(C('LIST_ROWS')) ? :10;
        if (is_file(dirname(__FILE__).'/access_data_promote_statistics.txt')&&I('get.p')>0) {
            $filetxt = file_get_contents(dirname(__FILE__).'/access_data_promote_statistics.txt');
            $res = json_decode($filetxt,true);
        }else{
    		$user=M('User','tab_');
    		$map['promote_id']=array('egt',1);
    		if(isset($_REQUEST['timestart'])&&isset($_REQUEST['timeend'])){
                $map['register_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),strtotime($_REQUEST['timeend'])+24*60*60-1));
                unset($_REQUEST['timestart']);unset($_REQUEST['timeend']);
            }elseif (isset($_REQUEST['timestart'])){
                $map['register_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),time()));
            }elseif (isset($_REQUEST['timeend'])){
                $map['register_time'] =array('elt',strtotime($_REQUEST['timeend'])+24*60*60-1);
            }
            if(isset($_REQUEST['start'])&&isset($_REQUEST['end'])){
                $map['register_time'] =array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));
                unset($_REQUEST['start']);unset($_REQUEST['end']);
            }
            if(isset($_REQUEST['promote_id'])){
                $map['_string'] = "promote_id = ".$_REQUEST['promote_id']." or tab_promote.parent_id=".$_REQUEST['promote_id'];
                unset($_REQUEST['promote_id']);
            }
    		$data=$user
    			->field('promote_account,promote_id,IF(tab_promote.parent_id>0,tab_promote.parent_id,tab_promote.id) as pid,date_format(FROM_UNIXTIME(register_time),"%Y-%m-%d") AS time, count(tab_user.id) as count')
    			->where($map)
                ->join('tab_promote on tab_user.promote_id = tab_promote.id')
    			->group('pid')
    			->order('count desc')
    			->select();
            $count=count($data);
    		foreach ($data as $key => $value) {
    			static $i=0;
    			$i++;
    			$data[$key]['rand']=$i;
    			$adata=$this->day_data('User',array('promote_id'=>$value['promote_id']));
    			$data[$key]['today']=$adata['today']==''?0:$adata['today'];
    			$data[$key]['week']=$adata['week']==''?0:$adata['week'];
    			$data[$key]['mounth']=$adata['mounth']==''?0:$adata['mounth'];
    			if($data[$key]['promote_id']==0){
                        unset($data[$key]);
        		}
            }
            $res['list_data'] = $data;
            $res['count'] = $count;
            file_put_contents(dirname(__FILE__).'/access_data_promote_statistics.txt',json_encode($res));
        }
        $data = [];
        $data = $res['list_data'];
        $count = $res['count'];
		$total=$this->data_total($data);
		if($_REQUEST['data_order']!=''){
            $data_order=reset(explode(',',$_REQUEST['data_order']));
            $data_order_type=end(explode(',',$_REQUEST['data_order']));
            $this->assign('userarpu_order',$data_order);
            $this->assign('userarpu_order_type',$data_order_type);
        }
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%UP_PAGE% %FIRST% %LINK_PAGE% %END% %DOWN_PAGE% %HEADER%');
            $this->assign('_page', $page->show());
        }
        $data=my_sort($data,$data_order_type,(int)$data_order);
        $size=$row;//每页显示的记录数
        $pnum = ceil(count($data) / $size); //总页数，ceil()函数用于求大于数字的最小整数
        //用array_slice(array,offset,length) 函数在数组中根据条件取出一段值;array(数组),offset(元素的开始位置),length(组的长度)
        $data = array_slice($data, ($arraypage-1)*$size, $size);
		$this->assign('list_data',$data);
		$this->assign('total',$total);
        $this->display();
    }

    /*
     * 渠道注册详情
     */
    public function pro_regs_detail($p = 0){
    	$page = intval($p);
    	$page = $page ? $page : 1; //默认显示第一页数据
    	$arraypage=$page;
    	$row = 5;
    	$user=M('User','tab_');
    	$map['fgame_id']=array('gt',0);
    	$map['promote_id']=$_REQUEST['promote_id'];
    	
    	$data=$user
    	->field('fgame_name,fgame_id,date_format(FROM_UNIXTIME( register_time),"%Y-%m-%d") AS time, count(id) as count')
    	->where($map)
    	->group('fgame_id')
    	->order('count desc')
    	->select();
    	$count=count($data);
    	foreach ($data as $key => $value) {
    		static $i=0;
    		$i++;
    		$data[$key]['rand']=$i;
    		$adata=$this->day_data('User',array('fgame_id'=>$value['fgame_id']));
    		$data[$key]['today']=$adata['today'];
    		$data[$key]['week']=$adata['week'];
    		$data[$key]['mounth']=$adata['mounth'];
    	}
    	$total=$this->data_total($data);
    	$this->assign('total',$total);
    	if($_REQUEST['data_order']!=''){
    		$data_order=reset(explode(',',$_REQUEST['data_order']));
    		$data_order_type=end(explode(',',$_REQUEST['data_order']));
    		$this->assign('userarpu_order',$data_order);
    		$this->assign('userarpu_order_type',$data_order_type);
    	}
    	if($count > $row){
    		$page = new \Think\Page($count, $row);
    		$page->setConfig('theme','%UP_PAGE% %FIRST% %LINK_PAGE% %END% %DOWN_PAGE% %HEADER%');
    		$this->assign('_page', $page->show());
    	}
    	$data=my_sort($data,$data_order_type,(int)$data_order);
    	$size=$row;//每页显示的记录数
    	$pnum = ceil(count($data) / $size); //总页数，ceil()函数用于求大于数字的最小整数
    	//用array_slice(array,offset,length) 函数在数组中根据条件取出一段值;array(数组),offset(元素的开始位置),length(组的长度)
    	$data = array_slice($data, ($arraypage-1)*$size, $size);
    	$this->assign('list_data',$data);
    	$this->display();
    }
    
    function promotepay_statistics($p=0){
        $isbdpw['tab_spend.pay_way'] = array('gt',0);
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $arraypage=$page;
        $row = intval(C('LIST_ROWS')) ? :10;
        if (is_file(dirname(__FILE__).'/access_data_promotepay_statistics.txt')&&I('get.p')>0) {
            $filetxt = file_get_contents(dirname(__FILE__).'/access_data_promotepay_statistics.txt');
            $res = json_decode($filetxt,true);
        }else{
            $map['promote_id']=array('gt',0);
            $map2['promote_id']=array('gt',0);
            $map3['promote_id']=array('gt',0);
    		$spend=M('Spend','tab_');
    		if(isset($_REQUEST['timestart'])&&isset($_REQUEST['timeend'])){
                $map['tab_spend.pay_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),strtotime($_REQUEST['timeend'])+24*60*60-1));
                $map2['tab_deposit.create_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),strtotime($_REQUEST['timeend'])+24*60*60-1));
                $map3['tab_bind_recharge.create_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),strtotime($_REQUEST['timeend'])+24*60*60-1));
                unset($_REQUEST['timestart']);unset($_REQUEST['timeend']);
            }
            elseif (isset($_REQUEST['timestart'])){
                $map['tab_spend.pay_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),time()));
                $map2['tab_deposit.create_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),time()));
                $map3['tab_bind_recharge.create_time'] =array('BETWEEN',array(strtotime($_REQUEST['timestart']),time()));
            }elseif (isset($_REQUEST['timeend'])){
                $map['tab_spend.pay_time'] =array('elt',strtotime($_REQUEST['timeend'])+24*60*60-1);
                $map2['tab_deposit.create_time'] =array('elt',strtotime($_REQUEST['timeend'])+24*60*60-1);
                $map3['tab_bind_recharge.create_time'] =array('elt',strtotime($_REQUEST['timeend'])+24*60*60-1);
            }
            if(isset($_REQUEST['start'])&&isset($_REQUEST['end'])){
                $map['tab_spend.pay_time'] =array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));
                $map2['tab_deposit.create_time'] =array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));
                $map3['tab_bind_recharge.create_time'] =array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));
                unset($_REQUEST['start']);unset($_REQUEST['end']);
            }
            if(isset($_REQUEST['promote_id'])){
                $map['_string'] = "tab_spend.promote_id = ".$_REQUEST['promote_id']." or tab_promote.parent_id=".$_REQUEST['promote_id'];
                $map2['_string'] = "tab_deposit.promote_id = ".$_REQUEST['promote_id']." or tab_promote.parent_id=".$_REQUEST['promote_id'];
                $map3['_string'] = "tab_bind_recharge.promote_id = ".$_REQUEST['promote_id']." or tab_promote.parent_id=".$_REQUEST['promote_id'];
//                $map['tab_spend.promote_id'] =$_REQUEST['promote_id'];
//                $map2['tab_deposit.promote_id'] =$_REQUEST['promote_id'];
//                $map3['tab_bind_recharge.promote_id'] =$_REQUEST['promote_id'];
                unset($_REQUEST['promote_id']);
            }
            $map['pay_status']=1;
            $map2['pay_status']=1;
            $map3['pay_status']=1;
            //查询spend表中的数据
    		$union=$spend
    			->field('promote_account,promote_id,sum(pay_amount) as count,parent_id')
                ->join('tab_promote on tab_spend.promote_id = tab_promote.id')
    			->where($map)
                ->where($isbdpw)
    			->group('promote_id')
    			->select(false);
            $union1=M('bindRecharge','tab_')
                ->field('promote_account,promote_id,sum(real_amount) as count,parent_id')
                ->join('tab_promote on tab_bind_recharge.promote_id = tab_promote.id')
                ->where($map3)
                ->group('promote_id')
                ->select(false);

            //查询deposit表中的数据
            $sql = M('deposit','tab_')
                ->field('promote_account,promote_id,sum(pay_amount) as count,parent_id')
                ->join('tab_promote on tab_deposit.promote_id = tab_promote.id')
                ->union($union)
                ->union($union1)
                ->where($map2)
                ->group('promote_id')
                ->select(false);
            $model = new Model();
            $data = $model->table('('.$sql.') as a')
                ->field('promote_account,promote_id,sum(count) as count,if(parent_id>0,parent_id,promote_id) as pid')
                ->group('pid')
                ->order('promote_id')
                ->select();
            $count = count($data);
    		foreach ($data as $key => $value) {
    			static $i=0;
    			$i++;
    			$data[$key]['rand']=$i;
    			$adata=$this->day_data('Spend',array('promote_id'=>$value['promote_id'],'pay_status'=>'1'));
    			$data[$key]['today']=$adata['today']==''?0:$adata['today'];
    			$data[$key]['week']=$adata['week']==''?0:$adata['week'];
    			$data[$key]['mounth']=$adata['mounth']==''?0:$adata['mounth'];
                if($data[$key]['promote_id']=='0'){
                    unset($data[$key]);
                }
    		}
            $res['list_data'] = $data;
            $res['count'] = $count;
            file_put_contents(dirname(__FILE__).'/access_data_promotepay_statistics.txt',json_encode($res));
        }
        $data = [];
        $data = $res['list_data'];
        $count = $res['count'];
		$total=$this->data_total($data);
		$this->assign('total',$total);
		if($_REQUEST['data_order']!=''){
            $data_order=reset(explode(',',$_REQUEST['data_order']));
            $data_order_type=end(explode(',',$_REQUEST['data_order']));
            $this->assign('userarpu_order',$data_order);
            $this->assign('userarpu_order_type',$data_order_type);
        }
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%UP_PAGE% %FIRST% %LINK_PAGE% %END% %DOWN_PAGE% %HEADER%');
            $this->assign('_page', $page->show());
        }
        $data=my_sort($data,$data_order_type,(int)$data_order);
        $size=$row;//每页显示的记录数
        $pnum = ceil(count($data) / $size); //总页数，ceil()函数用于求大于数字的最小整数
        //用array_slice(array,offset,length) 函数在数组中根据条件取出一段值;array(数组),offset(元素的开始位置),length(组的长度)
        $data = array_slice($data, ($arraypage-1)*$size, $size);
		$this->assign('list_data',$data);
        $this->display();
    }

    /*
     * 渠道游戏充值详情
     */
    public function pro_pay_detail($p = 0){
        if(I('isbd')==1){
            $isbdpw['pay_way'] = array('neq',-1);
        }else{
            $isbdpw['tab_spend.id'] = array('gt',0);
        }
    	$page = intval($p);
    	$page = $page ? $page : 1; //默认显示第一页数据
    	$arraypage=$page;
    	$row = 5;
    	$map['promote_id']=array('gt',0);
    	$spend=M('Spend','tab_');
    	if(isset($_REQUEST['promote_id'])){
    		$map['promote_id'] =$_REQUEST['promote_id'];
    		unset($_REQUEST['promote_id']);
    	}
    	$map['pay_status']=1;
    	$data=$spend
    	->field('game_id,game_name,date_format(FROM_UNIXTIME(pay_time),"%Y-%m-%d") AS time, sum(pay_amount) as count')
    	->join('tab_promote on tab_spend.promote_id = tab_promote.id')
    	->where($map)
    	->where($isbdpw)
    	->group('game_id')
    	->order('count desc')
    	->select();
    	$count=count($data);
    	foreach ($data as $key => $value) {
    		static $i=0;
    		$i++;
    		$data[$key]['rand']=$i;
    		$adata=$this->day_data('Spend',array('promote_id'=>$value['promote_id'],'pay_status'=>'1'));
    		$data[$key]['today']=$adata['today']==''?0:$adata['today'];
    		$data[$key]['week']=$adata['week']==''?0:$adata['week'];
    		$data[$key]['mounth']=$adata['mounth']==''?0:$adata['mounth'];
    		if($data[$key]['promote_id']=='0'){
    			unset($data[$key]);
    		}
    	}
    	$total=$this->data_total($data);
    	$this->assign('total',$total);
    	if($_REQUEST['data_order']!=''){
    		$data_order=reset(explode(',',$_REQUEST['data_order']));
    		$data_order_type=end(explode(',',$_REQUEST['data_order']));
    		$this->assign('userarpu_order',$data_order);
    		$this->assign('userarpu_order_type',$data_order_type);
    	}
    	if($count > $row){
    		$page = new \Think\Page($count, $row);
    		$page->setConfig('theme','%UP_PAGE% %FIRST% %LINK_PAGE% %END% %DOWN_PAGE% %HEADER%');
    		$this->assign('_page', $page->show());
    	}
    	$data=my_sort($data,$data_order_type,(int)$data_order);
    	$size=$row;//每页显示的记录数
    	$pnum = ceil(count($data) / $size); //总页数，ceil()函数用于求大于数字的最小整数
    	//用array_slice(array,offset,length) 函数在数组中根据条件取出一段值;array(数组),offset(元素的开始位置),length(组的长度)
    	$data = array_slice($data, ($arraypage-1)*$size, $size);
    	
    	$this->assign('list_data',$data);
    	$this->display();
    	
    	
    }
    private function data_total($data){
    	$total['sum_count']=array_sum(array_column($data,'count'));
		$total['sum_today']=array_sum(array_column($data,'today'));
		$total['sum_week']=array_sum(array_column($data,'week'));
		$total['sum_mounth']=array_sum(array_column($data,'mounth'));
		return $total;
    }
  
    function day_data($model='User',$column1=array(),$column2='count'){
    	//今日本周本月不跟随选择的实现变动 只以当前日期为基准
    	$table=M($model,'tab_');
    	$today=total(1);
    	$week=total(2);
    	$mounth=total(3);
    	if($model=='User'){
    		$data['today']=$table->field('count(id) as count')->where($column1)->where('register_time'.$today)->select();
			$data['week']=$table->field('count(id) as count')->where($column1)->where('register_time'.$week)->select();
			$data['mounth']=$table->field('count(id) as count')->where($column1)->where('register_time'.$mounth)->select();
    	}else{
            $data['today'] = $this->getPayData($today,$column1,1);
            $data['week'] = $this->getPayData($week,$column1,1);
            $data['mounth'] = $this->getPayData($mounth,$column1,1);

//          $isbdpw['tab_spend.pay_way'] = array('gt',0);
//    		$data['today']=$table->field('sum(pay_amount) as count')->where($column1)->where('pay_time'.$today)->where($isbdpw)->select();
//			$data['week']=$table->field('sum(pay_amount) as count')->where($column1)->where('pay_time'.$week)->where($isbdpw)->select();
//			$data['mounth']=$table->field('sum(pay_amount) as count')->where($column1)->where('pay_time'.$mounth)->where($isbdpw)->select();
    	}
    	foreach ($data as $key => $value) {
    		$v=reset($value);
    		$data[$key]=$v['count'];
    	}
    	return $data;
    }



    /**
     * 获取当日,每周,每月的充值
     * @param $time         时间条件字符串
     * @param $column1      查询条件数组
     * @param int $is_bdpw  是否包含绑币 (1:排除绑币,0:包含绑币)
     * @auther <zsl>
     * @return mixed
     */
    public function getPayData($time,$column1,$is_bdpw=0){
        if($is_bdpw){//排除绑币
            $isbdpw['tab_spend.pay_way'] = array('gt',0);
        }else{//包含绑币
            $isbdpw['tab_spend.pay_way'] = array('neq',0);
        }

        $map['promote_id']=array('gt',0);
        $map2['promote_id']=array('gt',0);

        if(!empty($time)){
            $mtime = 'tab_spend.pay_time'.$time;
            $mtime2 = 'tab_deposit.create_time'.$time;
            $mtime3 = 'tab_bind_recharge.create_time'.$time;
        }
        if(!empty($column1['promote_id'])){
            $promote_ids = get_prmoote_chlid_account($column1['promote_id']);
            $promote_ids = array_column($promote_ids,'id');
            $promote_ids[] = $column1['promote_id'];
            $map['tab_spend.promote_id'] = array('in',$promote_ids);
            $map2['tab_deposit.promote_id'] = array('in',$promote_ids);
            $map3['tab_bind_recharge.promote_id'] = array('in',$promote_ids);
        }

        $map['pay_status']=1;
        $map2['pay_status']=1;


        //查询spend表中的数据
        $union=M('spend','tab_')
            ->field('promote_account,promote_id,sum(pay_amount) as count,IF(parent_id,parent_id,promote_id) as pid')
            ->join('tab_promote on tab_spend.promote_id = tab_promote.id')
            ->where($map)
            ->where($mtime)
            ->where($isbdpw)
            ->group('pid')
            ->order('tab_spend.pay_time desc')
            ->select(false);
        $union1=M('bindRecharge','tab_')
            ->field('promote_account,promote_id,sum(real_amount) as count,IF(parent_id,parent_id,promote_id) as pid')
            ->join('tab_promote on tab_bind_recharge.promote_id = tab_promote.id')
            ->where($map3)
            ->where($mtime3)
            ->group('pid')
            ->select(false);
        //查询deposit表中的数据
        $sql = M('deposit','tab_')
            ->field('promote_account,promote_id,sum(pay_amount) as count,IF(parent_id,parent_id,promote_id) as pid')
            ->join('tab_promote on tab_deposit.promote_id = tab_promote.id')
            ->union('('.$union.')')
            ->union('('.$union1.')')
            ->where($map2)
            ->where($mtime2)
            ->group('pid')
            ->select(false);

        $model = new Model();
        $data = $model->table('('.$sql.') as a')
            ->field('promote_account,promote_id,sum(count) as count')
            ->group('a.promote_id')
            ->order('promote_id')
            ->select();

        return $data;
    }



}
