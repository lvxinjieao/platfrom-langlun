<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;
use Org\UcenterSDK\Ucservice;
use Admin\Model\SpendModel;
/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class SpendController extends ThinkController {
    const model_name = 'Spend';
    /**
     * [lists 充值列表]
     * @return [type] [description]
     * @author [yyh] <[<email address>]>
     */
    public function lists(){
        // $stime=microtime(true); //获取程序开始执行的时间
        if(isset($_REQUEST['user_account'])){
            $map['user_account']=array('like','%'.trim($_REQUEST['user_account']).'%');
            unset($_REQUEST['user_account']);
        }
        if(isset($_REQUEST['spend_ip'])){
            $map['spend_ip']=array('like','%'.trim($_REQUEST['spend_ip']).'%');
            unset($_REQUEST['spend_ip']);
        }
				
		if (!empty($_REQUEST['timestart']) && !empty($_REQUEST['timeend'])) {
			$map['pay_time'] = array('BETWEEN',array(strtotime($_REQUEST['timestart']),strtotime($_REQUEST['timeend'])+24*60*60-1));
			
		} elseif (!empty($_REQUEST['timestart']) ) {
			$map['pay_time'] = array('BETWEEN',array(strtotime($_REQUEST['timestart']),time()));
		
		} elseif (!empty($_REQUEST['timeend']) ) {
			$map['pay_time'] = array('elt',strtotime($_REQUEST['timeend'])+24*60*60-1);
			
		}
				
        if(isset($_REQUEST['start'])&&isset($_REQUEST['end'])){
            $map['pay_time'] =array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));
            unset($_REQUEST['start']);unset($_REQUEST['end']);
        }
				
        if(isset($_REQUEST['game_name'])){
            if($_REQUEST['game_name']=='全部'){
                unset($_REQUEST['game_name']);
            }else{
                $map['game_name']=$_REQUEST['game_name'];
                unset($_REQUEST['game_name']);
            }
        }
        if(isset($_REQUEST['pay_order_number'])){
            $map['pay_order_number']=array('like','%'.trim($_REQUEST['pay_order_number']).'%');
            unset($_REQUEST['pay_order_number']);
        }
        if(isset($_REQUEST['pay_status'])){
            $map['pay_status']=$_REQUEST['pay_status'];
            unset($_REQUEST['pay_status']);
        }
        if(isset($_REQUEST['pay_way'])){
            $map['pay_way']=$_REQUEST['pay_way'];
            unset($_REQUEST['pay_way']);
        }
        if(isset($_REQUEST['pay_game_status'])){
            $map['pay_game_status']=$_REQUEST['pay_game_status'];
            unset($_REQUEST['pay_game_status']);
        }
        $map['id']=['gt',0];
        $map1=$map;
        $map1['pay_status']=1;

        //获取排序规则
        $data_order = I('data_order','');
        $order_str = $this->getOrderRule($data_order);

        $map['order']= $order_str;
        $map['fields']='id,user_id,pay_order_number,pay_time,user_account,game_name,promote_id,spend_ip,pay_amount,pay_way,pay_status,pay_game_status';
        parent::data_lists(self::model_name,$_GET["p"],$map);
        $total=null_to_0(D(self::model_name)->where($map1)->sum('pay_amount'));
        $ttotal=null_to_0(D(self::model_name)->where('pay_time'.total(1))->where(array('pay_status'=>1))->sum('pay_amount'));
        $ytotal=null_to_0(D(self::model_name)->where('pay_time'.total(5))->where(array('pay_status'=>1))->sum('pay_amount'));
        $this->assign('total',$total);
        $this->assign('ttotal',$ttotal);
        $this->assign('ytotal',$ytotal);

        $this->meta_title = '游戏充值';
        $this->display();
    }

    //充值汇总
    public function summary($p=1){
        
        $model = new SpendModel();
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $row    = intval(C('LIST_ROWS')) ? intval(C('LIST_ROWS')):10;
        $map = [];
        if(I('get.isbd')==1){
            $map['pay_way'] = array('neq',-1);
        }else{
            unset($map['pay_way']);
        }
        $request1['isbd'] = 0;
        $this->assign('nobdurl',U('summary',$request1,false));
        $request1['isbd'] = 1;
        $this->assign('isbdurl',U('summary',$request1,false));
        if (!empty($_REQUEST['timestart']) && !empty($_REQUEST['timeend'])) {
            $map['time'] = array('BETWEEN',array($_REQUEST['timestart'],$_REQUEST['timeend']));
            
        } elseif (!empty($_REQUEST['timestart']) ) {
            $map['time'] = array('BETWEEN',array($_REQUEST['timestart'],date('Y-m-d',time())));
        
        } elseif (!empty($_REQUEST['timeend']) ) {
            $map['time'] = array('elt',$_REQUEST['timeend']);
        }
        $res = $model->summary($map,$page,$row);
        $this->showPage($res['count'],$row);
        $this->assign('list_data',$res['date']);
        $this->display();
    }


    //获取排序规则
    private function getOrderRule($data_order){

        $data_arr = explode(',',$data_order);
        if($data_arr[0]=='3'){
            $sort = 'DESC';
        }else{
            $sort = 'ASC';
        }
        $field = $data_arr[1];
        if($field!='pay_amount' && $field!='pay_time'){
            return 'pay_time DESC';
        }else{
            return $field.' '.$sort;
        }

    }
}