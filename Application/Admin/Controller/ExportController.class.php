<?php
namespace admin\Controller;
use Think\Controller;
use Admin\Controller\PlatformController;
class ExportController extends Controller
{
	public function exportExcel($expTitle,$expCellName,$expTableData){
        $xlsTitle = iconv('utf-8', 'gb2312', $expTitle);//文件名称  
        $fileName = session('user_auth.username').date('_YmdHis');//or $xlsTitle 文件名称可根据自己情况设定
        $cellNum = count($expCellName);
        $dataNum = count($expTableData);
        Vendor("PHPExcel.PHPExcel");
        $objPHPExcel = new \PHPExcel();
        $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');
        $objPHPExcel->getActiveSheet(0)->mergeCells('A1:'.$cellName[$cellNum-1].'1');//合并单元格
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle);  
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('A1')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        for($i=0;$i<$cellNum;$i++){
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i].'2', $expCellName[$i][1]); 
        } 
        for($i=0;$i<$dataNum;$i++){
          for($j=0;$j<$cellNum;$j++){
            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+3), $expTableData[$i][$expCellName[$j][0]]);
          }             
        }  
        header('pragma:public');
        header('Content-type:application/vnd.ms-excel;charset=utf-8;name="'.$xlsTitle.'.xls"');
        header("Content-Disposition:attachment;filename=$xlsTitle.xls");//attachment新窗口打印inline本窗口打印
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');  
        $objWriter->save('php://output'); 
        exit;   
    }

	//导出Excel
     function expUser($id){
     	switch ($id) {
            case 1:
                $xlsName  = "玩家列表";
                $xlsCell  = array(
                    array('id','编号'),
                    array('account','玩家账号'),
                    array('promote_account','所属推广员'),
                    array('balance','账户余额'),
                    array('recharge_total','累计充值'),
                    array('shop_point','积分余额'),
                    array('lock_status','账号状态'),
                    array('register_way','注册方式'),
                    array('register_time','注册时间'),  
                    array('login_ip','最后登录IP'),
                    array('login_time','最后登录时间'),
                );
                if(isset($_REQUEST['promote_id'])){
                    empty($hav) || $hav .= ' AND ';
                    if($_REQUEST['promote_id'] == "0"){
                        $hav .= "tab_user.promote_id like '%".I('promote_id')."%'";
                    }else{
                        $hav .= "tab_user.promote_id =".I('promote_id');
                    }
                    unset($_REQUEST['promote_id']);
                }
                //         "" == I('promote_id') || $hav .= "tab_user.promote_id =".I('promote_id');
                if(isset($_REQUEST['account'])){
                    //            $map['tab_user.account'] = array('like','%'.$_REQUEST['account'].'%');
                    empty($hav) || $hav .= ' AND ';
                    $hav .= "tab_user.account like '%".I('account')."%'";
                    unset($_REQUEST['account']);
                }
                if(isset($_REQUEST['register_way'])){
                    //            $map['register_way'] = $_REQUEST['register_way'];
                    empty($hav) || $hav .= ' AND ';
                    $hav .= 'tab_user.register_way ='.I('register_way');
                    unset($_REQUEST['register_way']);
                } else {
                    if ($_REQUEST['group']==1) {
                        empty($hav) || $hav .= ' AND ';
                        $hav .= 'tab_user.register_way in (3,4,5,6)';
                    } else {
                        empty($hav) || $hav .= ' AND ';
                        $hav .= 'tab_user.register_way in (0,1,2)';
                    }
                }
                if(isset($_REQUEST['time_start']) && isset($_REQUEST['time_end'])){
                    empty($hav) || $hav .= ' AND ';
                    $hav .= 'tab_user.register_time BETWEEN '.strtotime(I('time_start')).' AND '.(strtotime(I('time_end'))+24*60*60-1);
                    unset($_REQUEST['time_start']);unset($_REQUEST['time_end']);
                }elseif(isset($_REQUEST['time_start'])){
                    empty($hav) || $hav .= ' AND ';
                    $hav .= 'tab_user.register_time >= '.strtotime(I('time_start'));
                    unset($_REQUEST['time_start']);
                }elseif(isset($_REQUEST['time_end'])){
                    empty($hav) || $hav .= ' AND ';
                    $hav .= 'tab_user.register_time <= '.(strtotime(I('time_end'))+24*60*60-1);
                    unset($_REQUEST['time_end']);
                }
                if(isset($_REQUEST['start']) && isset($_REQUEST['end'])){
                    empty($hav) || $hav .= ' AND ';
                    $hav .= 'tab_user.register_time BETWEEN '.strtotime(I('start')).' AND '.strtotime(I('end'));
                    unset($_REQUEST['start']);unset($_REQUEST['end']);
                }
                if(isset($_REQUEST['status'])){
                    empty($hav) || $hav .= ' AND ';
                    $hav .= 'tab_user.lock_status = '.I('status');
                }
                if (isset($_REQUEST['user_id'])) {
                    empty($hav) || $hav .= ' AND ';
                    $hav .= 'tab_user.id = '.I('user_id');
                }
                //排序
                $order = '';
                if (I('recharge_status') == 1) {
                    $order = 'recharge_total,';
                } elseif (I('recharge_status') == 2) {
                    $order = 'recharge_total desc,';
                }
                if (I('balance_status') == 1) {
                    $order .= 'balance,';
                } elseif (I('balance_status') == 2) {
                    $order .= 'balance desc,';
                }
                $order .= 'tab_user.id desc';
                $xlsData= M('user','tab_')->field('tab_user.*,cumulative as recharge_total')
                ->having($hav)
                ->order($order)
                ->select();
            foreach ($xlsData as &$value) {
                $value['register_way'] = get_registertype($value['register_way']);
                $value['lock_status'] = get_info_status($value['lock_status'],4);
                $value['register_time'] = date('Y-m-d H:i:s',$value['register_time']);
                $value['login_time'] = date('Y-m-d H:i:s',$value['login_time']);
            }
            break;
            case '2':
                $xlsName  = I('get.timestart').'--'.I('get.timeend')."充值列表";
                $xlsCell  = array(
                    array('pay_order_number','支付订单号'),
                    array('pay_time','充值时间'),
                    array('user_account','玩家账号'),
                    array('game_name','游戏名称'),
                    array('promote_account','所属推广员'),
                    array('spend_ip','充值IP'),
                    array('pay_amount','充值金额'),
                    array('pay_way','充值方式'),
                    array('pay_status','订单状态'),  
                    array('pay_game_status','游戏通知状态'),
                );
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

                $map['order']='pay_time DESC';
                $map['fields']='id,pay_order_number,pay_time,user_account,game_name,promote_account,spend_ip,pay_amount,pay_way,pay_status,pay_game_status';
                $xlsData = D('Spend')
                /* 查询指定字段，不指定则查询所有字段 */
                ->field($map['fields'])
                // 查询条件
                ->where($map)
                /* 默认通过id逆序排列 */
                ->order(empty($map['order'])?"id desc":$map['order'])
                /* 分组 */
                /* 执行查询 */
                ->select();
                foreach ($xlsData as &$value) {
                    $value['pay_time'] = set_show_time($value['pay_time']);
                    $value['pay_way'] = get_pay_way($value['pay_way']);
                    $value['pay_status'] = get_info_status($value['pay_status'],9);
                    $value['pay_game_status'] = get_info_status($value['pay_game_status'],14);
                }
                break;
            case '3':
                $xlsName  = "平台币充值列表";
                $xlsCell  = array(
                    array('pay_order_number','订单号'),
                    array('create_time','充值时间'),
                    array('user_account','玩家账号'),
                    array('pay_ip','充值IP'),
                    array('promote_account','所属推广员'),
                    array('pay_amount','充值平台币'),
                    array('pay_way','充值方式'),
                    array('pay_status','订单状态'),  
                );
                $filetxt = file_get_contents(dirname(__FILE__).'/access_data_deposite_list.txt');
                $data = json_decode($filetxt,true);
                $xlsData = $data['data'];
                foreach ($xlsData as &$value) {
                    $value['create_time'] = set_show_time($value['create_time']);
                    $value['pay_way'] = get_pay_way($value['pay_way']);
                    $value['pay_status'] = get_info_status($value['pay_status'],9);
                }
                break;
            case '4':
                $xlsName  = "推广员列表";
                $xlsCell  = array(
                    array('id','推广员ID'),
                    array('create_time','推广员账号'),
                    array('balance_coin','平台币余额'),
                    array('pay_all','总流水'),
                    array('mobile_phone','手机号'),
                    array('get_qu_promote','推广员类型'),
                    array('get_top_promote','上线推广员'),
                    array('status','状态'),  
                    array('create_time','注册时间'),  
                );
                $filetxt = file_get_contents(dirname(__FILE__).'/access_data_promote_list.txt');
                $data = json_decode($filetxt,true);
                $xlsData = $data['data'];
                foreach ($xlsData as &$value) {
                    $value['pay_all'] = promote_user_pay($value['id']);
                    $value['get_qu_promote'] = get_qu_promote($value['parent_id']);
                    $value['get_top_promote'] = get_top_promote($value['id'],$value['parent_id']);
                    $value['status'] = get_info_status($value['status'],3);
                    $value['create_time'] = set_show_time($value['create_time']);
                }
                break;
            case '5':
                $xlsName  = "推广员注册统计";
                $xlsCell  = array(
                    array('promote_account','推广员账号'),
                    array('count','累计充值'),
                    array('rand','排行榜'),
                    array('today','今日充值'),
                    array('week','本周充值'),
                    array('mounth','本月充值'), 
                );
                $filetxt = file_get_contents(dirname(__FILE__).'/access_data_promote_statistics.txt');
                $data = json_decode($filetxt,true);
                $xlsData = $data['list_data'];
                break;
            case '6':
                $xlsName  = "推广员充值统计";
                $xlsCell  = array(
                    array('promote_account','推广员账号'),
                    array('count','累计注册'),
                    array('rand','排行榜'),
                    array('today','今日注册'),
                    array('week','本周注册'),
                    array('mounth','本月注册'), 
                );
                $filetxt = file_get_contents(dirname(__FILE__).'/access_data_promotepay_statistics.txt');
                $data = json_decode($filetxt,true);
                $xlsData = $data['list_data'];
                break;
            case '7':
                $xlsName  = "实时注册";
                $xlsCell  = array(
                    array('account','玩家账号'),
                    array('game_name','注册游戏'),
                    array('promote_account','所属推广员'),
                    array('register_time','注册时间'),
                    array('register_ip','注册IP'),
                    array('get_top_promote','上线推广员'), 
                    array('is_check','对账状态'), 
                );
                $filetxt = file_get_contents(dirname(__FILE__).'/access_data_ch_reg_list.txt');
                $data = json_decode($filetxt,true);
                $xlsData = $data['data'];
                foreach ($xlsData as &$value) {
                    $value['game_name'] = get_game_name($value['game_id']);
                    $value['register_time'] = set_show_time($value['register_time']);
                    $value['get_top_promote'] = get_top_promote($value['id'],$value['parent_id']);
                    $value['is_check'] = $value['is_check']==1?'参与':'不参与';
                }
                break;
            case '8':
                $xlsName  = "实时充值";
                $xlsCell  = array(
                    array('pay_order_number','订单号'),
                    array('pay_time','充值时间'),
                    array('user_account','玩家账号'),
                    array('promote_account','所属推广员'),
                    array('game_name','游戏名称'),
                    array('spend_ip','充值IP'),
                    array('pay_amount','充值金额(元)'),
                    array('pay_way','充值方式'),
                    array('is_check','对账状态'), 
                );
                $filetxt = file_get_contents(dirname(__FILE__).'/access_data_spend_list.txt');
                $data = json_decode($filetxt,true);
                $xlsData = $data['data'];
                foreach ($xlsData as &$value) {
                    $value['pay_time'] = set_show_time($value['pay_time']);
                    $value['pay_way'] = get_pay_way($value['pay_way']);
                    $value['is_check'] = $value['is_check']==1?'参与':'不参与';
                }
                break;
            case '9':
                $xlsName  = "结算记录";
                $xlsCell  = array(
                    array('promote_account','推广员账号'),
                    array('time','结算周期'),
                    array('settlement_number','结算单号'),
                    array('total_money','总充值(元)'),
                    array('total_number','总注册'),
                    array('sm','结算金额(元)'),
                    array('isbd','结算范围'),
                    array('create_time','结算时间'),
                );
                $filetxt = file_get_contents(dirname(__FILE__).'/access_data_query_settlement.txt');
                $data = json_decode($filetxt,true);
                $xlsData = $data['data'];
                foreach ($xlsData as &$value) {
                    $value['starttime'] = set_show_time($value['starttime']);
                    $value['endtime'] = set_show_time($value['endtime']);
                    $value['time'] = $value['starttime'].'至'.$value['endtime'];
                    $value['create_time'] = set_show_time($value['create_time']);
                    $value['isbd'] = $value['isbd']?'包含绑币':'排除绑币';
                }
                break;
            case 10:
                $xlsName  = "推广提现";
                $xlsCell  = array(
                        array('settlement_number','提现单号'),
                        array('sum_money','提现金额'),
                        array('promote_account','推广员账号'),
                        array('create_time','申请时间'),
                        array('status','提现状态（0:审核中;1:已通过;2:已拒绝;）'),
                        array('type','打款方式'),
                        array('end_time','审核时间'),
                );
                if(isset($_REQUEST['settlement_number'])){
                    $map['settlement_number']=$_REQUEST['settlement_number'];
                }
                if(isset($_REQUEST['status'])){
                    $map['status']=$_REQUEST['status'];
                }
                if(isset($_REQUEST['promote_account'])){
                    if($_REQUEST['promote_account']=='全部'){
                        unset($_REQUEST['promote_account']);
                    }else{
                        $map['promote_account'] = $_REQUEST['promote_account'];
                    }
                }
                $xlsData = M('withdraw','tab_')
                ->where($map)
                ->select();
                foreach ($xlsData as &$value) {
                    $value['create_time']=date('Y-m-d H:i:s',$value['create_time']);
                    if($value['end_time']!=''){
                        $value['end_time']=date('Y-m-d H:i:s',$value['end_time']);
                    }else{
                        $value['end_time']='未审核';
                    }
                    if($value['widthdraw_number']==''&&$value['status']==1){
                        $value['type']='手动';
                    }elseif($value['widthdraw_number']!=''&&$value['status']==1){
                        $value['type']='自动';
                    }else{
                        $value['type']='--';
                    }
                }
                break;
            case '11':
                $xlsName  = "代充记录";
                $xlsCell  = array(
                    array('promote_account','推广员账号'),
                    array('user_account','玩家账号'),
                    array('game_name','游戏名称'),
                    array('amount','代充金额(元)'),
                    array('zhekou','折扣比例(折)'),
                    array('real_amount','实付金额(元)'),
                    array('pay_status','充值状态'),
                    array('pay_way','支付方式'),
                    array('create_time','代充时间'),
                );
                $filetxt = file_get_contents(dirname(__FILE__).'/access_data_spend_list.txt');
                $data = json_decode($filetxt,true);
                $xlsData = $data['data'];
                foreach ($xlsData as &$value) {
                    $value['create_time'] = set_show_time($value['pay_time']);
                    $value['pay_way'] = get_pay_way($value['pay_way']);
                    $value['pay_status'] = get_info_status($value['pay_status'],7);
                }
                break;
            case 12:
                if(I('group')==2){
                    $xlsName  = "平台币发放记录";
                }else{
                    $xlsName  = "绑币发放记录";
                }
                $xlsCell  = array(
                    array('id','编号'),
                    array('pay_order_number','订单号'),
                    array('user_nickname','用户昵称'),
                    array('amount','金额'),
                    array('create_time','充值时间'),
                    array('status','状态(0未充值;1已充值)'),
                    array('op_account','操作人'),
                );
                if(I('group')==2){
                    $map['coin_type'] =0;
                }else{
                    $xlsCell  = array(
                        array('id','编号'),
                        array('pay_order_number','订单号'),
                        array('user_nickname','用户昵称'),
                        array('game_name','游戏名称'),
                        array('amount','金额'),
                        array('create_time','充值时间'),
                        array('status','状态'),
                        array('op_account','操作人'),
                    );
                    $map['coin_type'] =-1;
                }
                if(isset($_REQUEST['user_account'])){
                    $map['user_account']=array('like','%'.$_REQUEST['user_account'].'%');
                }
                if (isset($_REQUEST['game_name'])) {
                    if ($_REQUEST['game_name'] == '全部') {
                        unset($_REQUEST['game_name']);
                    } else {
                        $map['game_name'] = $_REQUEST['game_name'];
                        unset($_REQUEST['game_name']);
                    }
                }

                if (!empty($_REQUEST['timestart']) && !empty($_REQUEST['timeend'])) {
                    $map['create_time'] = array('BETWEEN',array(strtotime($_REQUEST['timestart']),strtotime($_REQUEST['timeend'])+24*60*60-1));

                } elseif (!empty($_REQUEST['timestart']) ) {
                    $map['create_time'] = array('BETWEEN',array(strtotime($_REQUEST['timestart']),time()));

                } elseif (!empty($_REQUEST['timeend']) ) {
                    $map['create_time'] = array('elt',strtotime($_REQUEST['timeend'])+24*60*60-1);

                }
                if(isset($_REQUEST['start']) && isset($_REQUEST['end'])){
                    $map['create_time'] = array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));;
                }
                $xlsData=M('Provide','tab_')
                    ->field("id,pay_order_number,user_nickname,game_name,amount,FROM_UNIXTIME(create_time,'%Y-%m-%d %H:%i:%s') as create_time,status,op_account")
                    ->where($map)
                    ->order("create_time")
                    ->select();
                foreach ($xlsData as $key=>$v){
                    $xlsData[$key]['status'] = $v['status']== 1 ? "已充值" : "未充值";
                }
                break;
            case 13:
                $xlsName  = "会长代充";
                $xlsCell  = array(
                    array('promote_account','所属推广员'),
                    array('user_account','玩家账号'),
                    array('game_id','充值游戏'),
                    array('amount','充值金额'),
                    array('zhekou','折扣比例'),
                    array('real_amount','实付金额'),
                    array('pay_status','充值状态（1成功0失败）'),
                    array('pay_way','支付方式'),
                    array('create_time','充值时间'),
                );
                $map['promote_id'] = array("neq",0);
                if(isset($_REQUEST['user_account'])){
                    $map['user_account']=array('like','%'.$_REQUEST['user_account'].'%');
                    unset($_REQUEST['user_account']);
                }
                if(isset($_REQUEST['pay_status'])){
                    $map['pay_status']=$_REQUEST['pay_status'];
                    unset($_REQUEST['pay_status']);
                }
                if(isset($_REQUEST['promote_name'])){
                    if($_REQUEST['promote_name']=='全部'){
                        unset($_REQUEST['promote_name']);
                    }else if($_REQUEST['promote_name']=='自然注册'){
                        $map['promote_id']=array("elt",0);
                        unset($_REQUEST['promote_name']);
                    }else{
                        $map['promote_id']=get_promote_id($_REQUEST['promote_name']);
                        unset($_REQUEST['promote_name']);
                        unset($_REQUEST['promote_id']);
                    }
                }

                if(isset($_REQUEST['start'])&&isset($_REQUEST['end'])){
                    $map['create_time']=array('BETWEEN',array(strtotime($_REQUEST['start']),strtotime($_REQUEST['end'])+24*60*60-1));
                    unset($_REQUEST['start']);unset($_REQUEST['end']);
                }

                if (!empty($_REQUEST['timestart']) && !empty($_REQUEST['timeend'])) {
                    $map['create_time'] = array('BETWEEN',array(strtotime($_REQUEST['timestart']),strtotime($_REQUEST['timeend'])+24*60*60-1));

                } elseif (!empty($_REQUEST['timestart']) ) {
                    $map['create_time'] = array('BETWEEN',array(strtotime($_REQUEST['timestart']),time()));

                } elseif (!empty($_REQUEST['timeend']) ) {
                    $map['create_time'] = array('elt',strtotime($_REQUEST['timeend'])+24*60*60-1);

                }


                if(isset($_REQUEST['game_name'])){
                    if($_REQUEST['game_name']=='全部'){
                        unset($_REQUEST['game_name']);
                    }else{
                        $map['game_name']=$_REQUEST['game_name'];
                        unset($_REQUEST['game_name']);
                    }
                }
                if(isset($_REQUEST['is_union'])){
                    $ids=get_union_member($_REQUEST['is_union']);
                    if($ids){
                        $ids=implode(',',$ids);
                        $map['user_id']=array('in',$ids);
                    }else{
                        $map['user_id']=-1;
                    }
                    unset($_REQUEST['is_union']);
                }
                if($_REQUEST['data_order']!=''){
                    $data_order=reset(explode(',',$_REQUEST['data_order']))==3?'desc':'asc';
                    $data_order_type=end(explode(',',$_REQUEST['data_order']));
                    $order = $data_order_type.' '.$data_order;
                }else{
                    $order = 'id desc';
                }
                empty(I('promote_id')) || $map['promote_id'] = I('promote_id');
                $xlsData = M('Agent','tab_')
                    ->where($map)
                    ->order($order)
                    ->select();
                foreach ($xlsData as &$value) {
                    $value['create_time']=date('Y-m-d H:i:s',$value['create_time']);
                    $value['game_id']=get_game_name($value['game_id']);
                    $value['pay_way'] = get_pay_way1($value['pay_way']);
                }
                break;
            default:
                $this->error('参数未定义');
                break;
        }
 	   $this->exportExcel($xlsName,$xlsCell,$xlsData);

     }


    public function user($p=1) {

        $xlsName = '用户分析';


        if(is_numeric($_REQUEST['game_id']) && $_REQUEST['game_id']>0) {
            $game_id = I('game_id','');
            $gamesource = get_source_from_game($game_id);
        } else {
            $gamesource = '全部';
        }

        if(is_numeric($_REQUEST['promote_id'])) {
            $promote_id = I('promote_id',0);
            $promoteaccount = get_promote_name($promote_id);
        } else {
            $promoteaccount = '全部';
        }

        $xlsCell = array(
            array('time','日期'),
            array('source','来源游戏'),
            array('promote','推广员'),
            array('news','新增用户'),
            array('old','老用户'),
            array('dau','DAU'),
            array('wau','WAU'),
            array('mau','MAU'),
        );

        $filetxt = file_get_contents(dirname(__FILE__).'/access_data_user.txt');

        $data = json_decode($filetxt,true);
        if(is_numeric($_REQUEST['game_id']) && $_REQUEST['game_id']>0) {
            $game_id = I('game_id','');
            $gamesource = get_game_name($game_id);
        } else {
            $gamesource = '全部';
        }

        if(is_numeric($_REQUEST['promote_id'])) {
            $promote_id = I('promote_id',0);
            $promoteaccount = get_promote_name($promote_id);
        } else {
            $promoteaccount = '全部';
        }

        foreach ($data as $v) {
            $v['source'] = $gamesource;
            $v['promote'] = $promoteaccount;

            $xlsData[] = $v;
        }

        $this->exportExcel($xlsName, $xlsCell, $xlsData);
    }

    public function ltv($p=1) {

        $xlsName = 'LTV统计';

        $xlsCell = array(
            array('time','日期'),
            array('amount','充值金额'),
            array('active','活跃用户'),
            array('ltv1','LTV1'),
            array('ltv2','LTV2'),
            array('ltv3','LTV3'),
            array('ltv4','LTV4'),
            array('ltv5','LTV5'),
            array('ltv6','LTV6'),
            array('ltv7','LTV7'),
            array('ltv14','LTV14'),
            array('ltv30','LTV30'),
        );

        if (is_file(dirname(__FILE__).'/access_data_ltv.txt')) {

            $filetxt = file_get_contents(dirname(__FILE__).'/access_data_ltv.txt');

            $res = json_decode($filetxt,true);
            $data = $res['data'];

        } else {
            $page = intval($p);
            $page = $page ? $page : 1; //默认显示第一页数据
            $row = get_list_row();//10;

            $start = I('start',date('Y-m-d',strtotime('-30 day')));

            $end =  I('end',date('Y-m-d',strtotime('-1 day')));

            $end = strtotime($end)>=strtotime(date('Y-m-d'))?date('Y-m-d',strtotime('-1 day')):$end;

            $data = D('user')->ltv(strtotime($start),strtotime($end));


        }

        foreach ($data as $v) {
            $xlsData[] = $v;
        }
        $this->exportExcel($xlsName, $xlsCell, $xlsData);
    }

    public function new_ltv($p=1) {

        $xlsName = 'LTV统计';

        $xlsCell = array(
            array('time','日期'),
            array('amount','充值金额'),
            array('active','活跃用户'),
            array('ltv1','LTV1'),
            array('ltv2','LTV2'),
            array('ltv3','LTV3'),
            array('ltv4','LTV4'),
            array('ltv5','LTV5'),
            array('ltv6','LTV6'),
            array('ltv7','LTV7'),
            array('ltv14','LTV14'),
            array('ltv30','LTV30'),
        );

        if (is_file(dirname(__FILE__).'/access_data_ltv.txt')) {

            $filetxt = file_get_contents(dirname(__FILE__).'/access_data_new_ltv.txt');

            $data = json_decode($filetxt,true);

        } else {
            $page = intval($p);
            $page = $page ? $page : 1; //默认显示第一页数据
            $row = get_list_row();//10;

            $start = I('start',date('Y-m-d',strtotime('-30 day')));

            $end =  I('end',date('Y-m-d',strtotime('-1 day')));

            $end = strtotime($end)>=strtotime(date('Y-m-d'))?date('Y-m-d',strtotime('-1 day')):$end;

            $data = D('user')->new_ltv(strtotime($start),strtotime($end));


        }

        foreach ($data as $v) {
            $xlsData[] = $v;
        }

        $this->exportExcel($xlsName, $xlsCell, $xlsData);
    }

    public function overview() {

        $xlsName = $_REQUEST['name'];

        $xlsCell = array(
            array('time','日期'),
            array('count',$_REQUEST['name']),
        );

        $num = I('num',2);
        $key = $_REQUEST['key'];
        if($key=='pays'){
            $key = 'money';
        }

        if (is_file(dirname(__FILE__).'/access_data_foldline.txt')) {

            $filetxt = file_get_contents(dirname(__FILE__).'/access_data_foldline.txt');

            $data = json_decode($filetxt,true);
            
            $xlsData = $data[$key];
            $xlsSummary[] = $data['sum'][$key];

        } else {

            $start = I('start',date('Y-m-d',strtotime('-1 day')));

            $end = I('end',date('Y-m-d',strtotime('-1 day')));

            $starttime = strtotime($start);
            $endtime = strtotime($end)+86399;

            $user = D('User');
            $spend = D('Spend');

            $flag = 1;

            if ($start == $end) {

                $hours = ['00~01','02~03','04~05','06~07','08~09','10~11','12~13','14~15','16~17','18~19','20~21','22~23'];

                foreach($hours as $v) {
                    if ($key == 'news'){
                        $data['news'][$v] = 0;}
                    if ($key == 'active'){
                        $data['active'][$v] = 0;}
                    if ($key == 'player'){
                        $data['player'][$v] = 0;}
                }

                // 新增用户
                if ($key == 'news'){
                    $hoursnews = $user->newsAdd(['register_time'=>['between',[$starttime,$endtime]]],'news','time',5);
                }
                if ($key == 'active'){
                    // 活跃用户
                    $hoursactive = $user->totalPlayerByGroup(['tab_user_login_record.login_time'=>['between',[$starttime,$endtime]]],'active','time',true,5);
                }if ($key == 'player'){
                    // 付费用户
                    $hoursplayer = $spend->totalPlayerByGroup(['pay_time'=>['between',[$starttime,$endtime]]],'player','time',5);
                }

                foreach($hours as $v) {
                    foreach($hoursnews as $h) {
                        $time = explode(' ',$h['time']);
                        if (strpos($v,$time[1]) !== false) {
                            $data['news'][$v] = (integer)$h['news'];break;
                        }
                    }

                    foreach($hoursactive as $h) {
                        $time = explode(' ',$h['time']);
                        if (strpos($v,$time[1]) !== false) {
                            $data['active'][$v] = (integer)$h['active'];break;
                        }
                    }

                    foreach($hoursplayer as $h) {
                        $time = explode(' ',$h['time']);
                        if (strpos($v,$time[1]) !== false) {
                            $data['player'][$v] = (integer)$h['player'];break;
                        }
                    }


                }
            } else {

                $datelist = get_date_list($starttime,$endtime,$num==7?2:1);

                $flag = 0;

                foreach($datelist as $k => $v) {
                    if ($key == 'news'){
                        $data['news'][$v] = 0;}
                    if ($key == 'active'){
                        $data['active'][$v] = 0;}
                    if ($key == 'player'){
                        $data['player'][$v] = 0;}

                }
                if ($key == 'news'){
                    // 新增用户
                    $news = $user->newsAdd(['register_time'=>['between',[$starttime,$endtime]]],'news','time',$num==7?2:1);
                }
                if ($key == 'active'){
                    // 活跃用户
                    $active = $user->totalPlayerByGroup(['tab_user_login_record.login_time'=>['between',[$starttime,$endtime]]],'active','time',true,$num==7?2:1);
                }
                if ($key == 'player'){
                    // 付费用户
                    $player = $spend->totalPlayerByGroup(['pay_time'=>['between',[$starttime,$endtime]]],'player','time',$num==7?2:1);
                }
                foreach($datelist as $v) {
                    foreach($news as $h) {
                        if ($v == $h['time']) {
                            $data['news'][$v] = (integer)$h['news'];break;
                        }
                    }

                    foreach($active as $h) {
                        if ($v == $h['time']) {
                            $data['active'][$v] = (integer)$h['active'];break;
                        }
                    }

                    foreach($player as $h) {
                        if ($v == $h['time']) {
                            $data['player'][$v] = (integer)$h['player'];break;
                        }
                    }

                }

            }

            foreach ($data as $k => $v) {
                $sum = 0;$tempexport=[];
                foreach($v as $t => $s){
                    $sum += $s;
                    if($flag==1){
                        $tempexport[]=['time'=>((integer)substr($t,0,2)).':00','count'=>$s];
                    }else{
                        $tempexport[]=['time'=>$t,'count'=>$s];
                    }
                }
                $export[$k]=$tempexport;
                $export['sum'][$k]=$sum;
            }

            $xlsData = $export[$key];
            $xlsSummary[] = $export['sum'][$key];
        }

        $this->exportExcel($xlsName, $xlsCell, $xlsData);

    }
}