<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;

/**
 * 推广查询控制器
 * @author 王贺
 */
class QueryController extends ThinkController {
    public function settlement($p=0) {
        $group = I('group',1);
        $this->assign('group',$group);
        if(isset($_REQUEST['total_status'])){
            unset($_REQUEST['total_status']);
        }
        if ($group == 1) {
            if(!$_REQUEST['isbdbt']){
                unset($isbd);
                $isbd['pay_way'] = array('egt',0);
                $this->assign('isbd',0);
            }else{
                unset($isbd);
                $this->assign('isbd',1);
            }
            if($_REQUEST['unum']==2){
                $order='unum';
                $order_type=SORT_ASC;
            }else if($_REQUEST['unum']==1){
                $order='unum';
                $order_type=SORT_DESC;
            }
            if($_REQUEST['spay_amount']==2){
                $order='spay_amount';
                $order_type=SORT_ASC;
            }else if($_REQUEST['spay_amount']==1){
                $order='spay_amount';
                $order_type=SORT_DESC;
            }
            $model = array(
                'title'  => '渠道结算',
                'template_list' =>'settlement',
                'order' => $order,
                'order_type'=>$order_type//0倒序  1 正序
            );
            $start=$_REQUEST['timestart'];
            $end=$_REQUEST['timeend'];
            if(I('group')!=''){
                if($start==''||$end==''&&$_REQUEST['promote_account']==''){
                    $this->error('结算周期、所属渠道不能为空！','',1);
                }
                if($start==''||$end==''){
                    $this->error('请选择结算周期！','',1);
                }
                if($_REQUEST['promote_account']==''){
                    $this->error('请选择渠道！','',1);
                }
            }
            $smap['tab_spend.pay_status']=1;
            $this->assign('setdate',date("Y-m-d",strtotime("-1 day")));
            if($start && $end){
                if((strtotime($end)+24*60*60-1)<strtotime($start)){
                    $this->error('时间选择不正确！',U('Query/settlement'),'');
                }
                $umap['register_time']=array('BETWEEN',array(strtotime($start),strtotime($end)+24*60*60-1));
                if(isset($_REQUEST['game_name']) && $_REQUEST['game_name']!=''){
                    $umap['fgame_name']=$_REQUEST['game_name'];
                    $smap['tab_spend.game_name']=$_REQUEST['game_name'];
                }
                if(isset($_REQUEST['promote_account'])&&$_REQUEST['promote_account']!=''){
                    $allid=get_subordinate_promote(get_promote_id($_REQUEST['promote_account']));
                    $allid[]=$_REQUEST['promote_account'];
                    $umap['tab_user.promote_account']=array('in',implode(',',$allid));
                    $smap['tab_spend.promote_account']=array('in',implode(',',$allid));
                }else{
                    $this->error('未选择渠道！','',1);
                }
                $umap['is_check']=1;
                $umap['settle_check']=0;
                $smap['pay_time']=array('BETWEEN',array(strtotime($start),strtotime($end)+24*60*60-1));
                $smap['settle_check']=0;
                $smap['is_check']=1;
                if(!empty($isbd)){
                    $smap['pay_way']=$isbd['pay_way'];
                }
                $map['umap']=$umap;
                $map['smap']=$smap;
                $user = A('Settlement','Event');
                $user->settlement($model,$p,$map);
            }else{
                $this->display();
            }
        }
        if ($group == 2) {
            if (is_file(dirname(__FILE__).'/access_data_query_settlement.txt')&&I('get.p')>0) {
                $filetxt = file_get_contents(dirname(__FILE__).'/access_data_query_settlement.txt');
                $data = json_decode($filetxt,true);
                $list_data = $this->array_order_page($data['count'],I('get.p'),$data['data'],$data['row']);
                $data['list_data'] = $list_data;
            }else{
        		if (!empty($_REQUEST['stimestart']) && !empty($_REQUEST['stimeend'])) {
        			$map['create_time'] = array('BETWEEN',array(strtotime($_REQUEST['stimestart']),strtotime($_REQUEST['stimeend'])+24*60*60-1));
        			unset($_REQUEST['stimestart']);unset($_REQUEST['stimeend']);
        		} elseif (!empty($_REQUEST['stimestart']) ) {
        			$map['create_time'] = array('BETWEEN',array(strtotime($_REQUEST['stimestart']),time()));
        			unset($_REQUEST['stimestart']);
        		} elseif (!empty($_REQUEST['stimeend']) ) {
        			$map['create_time'] = array('elt',strtotime($_REQUEST['stimeend'])+24*60*60-1);
        			unset($_REQUEST['stimeend']);
        		}
        		
        		if (!empty($_REQUEST['timestart']) && !empty($_REQUEST['timeend'])) {
        			$map['starttime']=array('egt',strtotime($_REQUEST['timestart']));
        			$map['endtime']=array('elt',strtotime($_REQUEST['timeend'])+24*60*60-1);
        			unset($_REQUEST['timestart']);unset($_REQUEST['timeend']);
        		} elseif (!empty($_REQUEST['timestart']) ) {
        			$map['starttime']=array('egt',strtotime($_REQUEST['timestart']));
        			unset($_REQUEST['timestart']);
        		} elseif (!empty($_REQUEST['timeend']) ) {
        			$map['endtime']=array('elt',strtotime($_REQUEST['timeend'])+24*60*60-1);
        			unset($_REQUEST['timeend']);
        		}
    						
                if(isset($_REQUEST['game_name'])){
                    if($_REQUEST['game_name']=='全部'){
                        unset($_REQUEST['game_name']);
                    }else{
                        $map['game_name'] = $_REQUEST['game_name'];
                    }
                }
                if(isset($_REQUEST['promote_account'])){
                    if($_REQUEST['promote_account']=='全部'){
                        unset($_REQUEST['promote_account']);
                    }else{
                        $map['promote_account'] = $_REQUEST['promote_account'];
                    }
                }
                 if(!empty($_REQUEST['settlement_number'])){
                    $map['settlement_number'] = $_REQUEST['settlement_number'];
                }
                $model = array(
                    'm_name' => 'settlement',
                    'order'  => 'create_time desc ',
                    'title'  => '结算账单',
                    'template_list' =>'settlement', 
                    'group'  => 'settlement_number,promote_id',
                );
                $map1=$map;
                $map['order']='create_time DESC';
                $map['group']='settlement_number,promote_id';
                $map['fields']='*,sum(sum_money) as sm';
                $data = parent::order_lists('Settlement',$_GET["p"],$map);
                file_put_contents(dirname(__FILE__).'/access_data_query_settlement.txt',json_encode($data));
            }
            $map1['status'] = 1;
            $ztotal=null_to_0(D('settlement')->where($map)->sum('sum_money'));
            $this->assign('ztotal',$ztotal);
            $ttotal=null_to_0(D('settlement')->where($map1)->where('create_time'.total(1))->sum('sum_money'));
            $this->assign('ttotal',$ttotal);
            $ytotal=null_to_0(D('settlement')->where($map1)->where('create_time'.total(5))->sum('sum_money'));
            $this->assign('ytotal',$ytotal);
            $this->assign('model', $data['model']);
            $this->assign('list_grids', $data['grids']);
            $this->assign('list_data', $data['list_data']);
            $this->meta_title = $data['model']['title'];
            $this->display();
            // $user = A('Bill','Event');
            // $user->shou_list($model,$p,$map);
        }
    }
    public function generatesettlement(){
    	//结算单号
    	$settlement_number = 'JS-'.date('Ymd').date('His').sp_random_string(4);
        foreach ($_REQUEST['ids'] as $va){
            $game_data = explode(",", $va);
    		$data['starttime']=strtotime($_REQUEST['timestart']);
    		$data['endtime']=strtotime($_REQUEST['timeend'])+24*60*60-1;
    		$data['promote_account']=$_REQUEST['promote_account'];
    		$data['promote_id']=get_promote_id($_REQUEST['promote_account']);
    		$data['game_id'] = $game_data[0];
    		$data['game_name']=get_game_name($game_data[0]);
    		$game_data[1] == "CPS" ?  $data['pattern'] ="0" :$data['pattern'] ="1";
    		$data['ratio']=$game_data[2];
    		$data['money']=$game_data[3];
    		$data['total_money'] = $game_data[5];
    		$data['total_number'] = $game_data[4];
    		$data['settlement_number'] = $settlement_number;
    		$data['create_time']=NOW_TIME;
    		$data['isbd']=$_REQUEST['isbd'];
    	    $settlement_data[] = $data;
        }
        unset($data);
    	foreach ($settlement_data as $data){
    		//批量结算要加判断
    		$settlement_status = get_settlement($_REQUEST['timestart'],$_REQUEST['timeend'],$data['promote_id'],$data['game_id']);
    		if($settlement_status){
    			$game_name = $data['game_name'].'、'.$game_name;
    			continue;
    		}else{
    			if($data['pattern']){
    				$data['sum_money']=round($data['total_number']*$data['money'],2);
    			}else{
    				$data['sum_money']=round($data['total_money']*$data['ratio']/100,2);
    			}
    			if($data['game_id']==''||$data['promote_id']==''||$data['starttime']==''||$data['endtime']==''){
    				$this->error('必要参数不存在');
    			}
    			$map['fgame_id']=$data['game_id'];
    			$map['register_time']=array('BETWEEN',array($data['starttime'],$data['endtime']));
    			$allid=get_subordinate_promote(get_promote_id($data['promote_account']));
    			$allid[]=$data['promote_account'];
    			$map['promote_account']=array('in',$allid);
    			$u=M('User','tab_');
    			$u->startTrans();
    			$user=$u->where($map)->setField('settle_check',1);
    			unset($map['register_time']);
    			$map['pay_time']=array('BETWEEN',array($data['starttime'],$data['endtime']));
    			$s=M('spend','tab_');
    			$spend=$s->where($map)->setField('settle_check',1);
    			$result=M('settlement','tab_')->add($data);
    			if(!$result){
    				$u->rollback();
    			}
    			$u->commit();
    		}
    		}


            if($game_name){
                $tip = "结算成功，".$game_name."已结算。请不要重复结算";
            }else{
                action_log('tg_jiesuan','jiesuan',$_REQUEST['promote_account'],UID);
                $tip = "结算成功";
            }
    		$this->success($tip,U('Query/settlement',array('group'=>2)));
    }
    
    /*
     * 渠道结算详情
     */
    public function settlemeng_detail(){
    	$settlemeng_number = $_REQUEST;
    	if(empty($settlemeng_number['settlement_number'])){
    		$this->error("数据错误！",U('Query/my_earning'));
    	}
    	$map = $settlemeng_number;
    	$data = M('Settlement','tab_')->where($map)->field('starttime,endtime,game_id,game_name,sum_money,total_number,pattern,total_money,ratio,money')->select();
    	$sum_money = M('Settlement','tab_')->where($map)->sum('sum_money');
    	$this->assign('sum_money',$sum_money);
    	$this->assign('list_data',$data);
    	$this->display();
    }
    
    public function changeratio(){
        $gid    =   I('request.game_id');
        if(empty($gid)){
             $this->ajaxReturn(0,"请选择要操作的数据",0);exit;
        }
        $starttime=strtotime($_REQUEST['timestart'].'-01');
        $endtime=strtotime($_REQUEST['timestart']."+1 month -1 day")+24*3600-1;
        $map['s.pay_status']=1;
        $map['s.selle_status']=0;
        $map['s.game_id']=$_REQUEST['game_id'];
        $map['pay_time']=array('BETWEEN',array($starttime,$endtime));
        $spe=M('spend as s','tab_');
        $data=$spe
        ->field('s.id,s.selle_status,s.selle_ratio')
        ->join('tab_game as g on g.id=s.game_id','LEFT')
        ->where($map)
        ->setField('s.selle_ratio',$_POST['ratio']);
        if($data){
            $this->ajaxReturn($data);
        }else{
            $this->ajaxReturn(-1);
        }
    }
    public function withdraw($p=1) {
        $adminid = C('USER_ADMINISTRATOR');//获取超管id
        $adminmobile = M('UcenterMember')->field('mobile')->find($adminid);
        $this->assign('adminmobile',$adminmobile['mobile']);
        if(isset($_REQUEST['settlement_number'])){
            $map['settlement_number']=$_REQUEST['settlement_number'];
        }
        if(isset($_REQUEST['ti_status'])){
            $map['status']=$_REQUEST['ti_status'];
        }
        if(isset($_REQUEST['promote_account'])){
            if($_REQUEST['promote_account']=='全部'){
                unset($_REQUEST['promote_account']);
            }else{
                // $map['promote_id'] = array('gt',0);
                $map['promote_account'] = $_REQUEST['promote_account'];
                unset($_REQUEST['promote_account']);
            }
        }
        $map['parent'] = array('lt',1);
        
        if($_REQUEST['create_time']==2){
            $order='create_time desc';
        }elseif($_REQUEST['create_time']==1){
            $order='create_time asc';
        }else{
            $order='create_time desc';
        }
        if($_REQUEST['sum_money']==2){
            $order='sum_money desc';
        }elseif($_REQUEST['sum_money']==1){
            $order='sum_money asc';
        }
        $model = array(
            'm_name' => 'withdraw',
            'order'  => $order,
            'title'  => '渠道提现',
            'template_list' =>'withdraw',
        );
        $map1=array('status'=>1);
        $total=null_to_0(D('withdraw')->where($map1)->sum('sum_money'));
        $ttotal=null_to_0(D('withdraw')->where('end_time'.total(1))->where($map1)->sum('sum_money'));
        $ytotal=null_to_0(D('withdraw')->where('end_time'.total(5))->where($map1)->sum('sum_money'));
        $this->assign('total',$total);
        $this->assign('ttotal',$ttotal);
        $this->assign('ytotal',$ytotal);
        $user = A('Bill','Event');
        $user->money_list($model,$p,$map);
        
    }
    
    public function set_withdraw_status($model='withdraw',$ids = array()) {
        if(empty($_REQUEST['ids']))$this->error('请选择要操作的数据');
        //行为日志判断
        if($_REQUEST['status']==1&&$_REQUEST['field']=='status'){
            //同意推广提现
            action_log('tg_tx_agree','withdraw',UID,UID);
        }
        if($_REQUEST['status']==2&&$_REQUEST['field']=='status'){
            //拒绝推广提现
            action_log('tg_tx_disagree','withdraw',UID,UID);
        }

        $withdraw=M('withdraw',"tab_");
        $seet=M('settlement',"tab_");
        $count=count($_REQUEST['ids']);
        if($count>1){
            for ($i=0; $i <$count; $i++) { 
                $map['id']=$_REQUEST['ids'][$i];
                $dind=$withdraw->where($map)->find();
                $se_map['settlement_number']=$dind['settlement_number'];
                $seet->where($se_map)->save(array("ti_status"=>$_REQUEST['status']));
                $withdraw->where($map)->save(array("end_time"=>time()));
            }
        }else{
            if(is_array($_REQUEST['ids'])){
                $map['id']=$_REQUEST['ids'][0];
            }else{
                $map['id']=$_REQUEST['ids'];
            }
            $dind=$withdraw->where($map)->find();
            $se_map['settlement_number']=$dind['settlement_number'];
            $seet->where($se_map)->save(array("ti_status"=>$_REQUEST['status']));
            $withdraw->where($map)->save(array("end_time"=>time()));
        }
        parent::set_status($model);
    }


    protected function upPromote($promote_id){
        $model = D('Promote');
        $data['id'] = $promote_id;
        $data['money'] = 0;
        return $model->save($data);
    }




    /**
     * 审核推广提现
     * @author <jszsl001@163.com>
     */

    public function set_withdraw_agree(){

        //判断打款类型
        if(I('withdraw_type',0,'intval')==1){
            //手动打款,直接就改状态
            $this->set_withdraw_status();
        }else{
            action_log('tg_tx_agree','withdraw',UID,UID);
            $adminid = C('USER_ADMINISTRATOR');//获取超管id
            $adminmobile = M('UcenterMember')->field('mobile')->find($adminid);

            $dx = A('Phone');
            $res = -1;
            $res = $dx->check_tel_code($adminmobile['mobile'],$_POST['code']);
            switch ($res) {
                case '-1':
                    $this->error('短信验证码无效，请重新获取');
                    break;
                case '-2':
                    $this->error('时间超时,请重新获取短信验证码');
                    break;
                case '-3':
                    $this->error('短信验证码不正确，请重新输入');
                    break;
            }
            $withdraw=M('withdraw',"tab_");
            $pay = new \Think\Pay('alipay', C('alipay'));
            $withdraw_way = $_POST['withdraw_way'];
            //支付宝
            if($withdraw_way==1){
                $map['id']=$_POST['ids'];
                $dind=$withdraw->where($map)->find();
                if($dind['status']==1){
                    $this->ajaxReturn(['status'=>-1,'info'=>'请不要重复打款']);
                }
                $widthdrawNo = "TX_" . date('Ymd') . date('His') . sp_random_string(4);
                $vo   = new \Think\Pay\PayVo();
                $vo->setOrderNo($dind['settlement_number'])
                ->setBatchNo($widthdrawNo)
                ->setTable('Withdraw')
                ->setPayMethod("transfer")
                ->setDetailData('渠道结算提现，订单'.$dind["settlement_number"]);
                $res =  $pay->buildRequestForm($vo);
                if($res==10000){
                    $this->ajaxReturn(['status'=>1,'info'=>'打款成功']);
                }else{
                   $this->ajaxReturn(['status'=>0,'info'=>$res]); 
                }
            }else{//微信

            }


        }

    }



}