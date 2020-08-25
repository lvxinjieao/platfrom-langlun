<?php
namespace Admin\Controller;
class MsgController extends ThinkController{
	public function lists($p=0){
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据
        $row = intval(C('LIST_ROWS')) ? :10;

        $map['user_id'] = session('user_auth.uid');
        $map['status'] = array('neq','-1');
        $map['type'] = array('eq','1');
      
        if(isset($_REQUEST['status'])){
            if($_REQUEST['status']=='0'){
                $map['status']=2;
            }elseif($_REQUEST['status']=='1'){
                $map['status']=1;
            }
        }
        $data = D('Msg')->where($map)->page($page,$row)->order('status desc,id')->select();
        $count = D('Msg')->where($map)->count();
        
        if($count > $row){
            $page = new \Think\Page($count, $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $this->assign('_page', $page->show());
        }
        $this->assign('list_data',$data);
        $this->meta_title = "站内通知";
        $this->display();
    }

    public function read($ids=0){
	    if(empty($ids))$this->error('请选择要操作的数据');
        if (!empty($ids)) {
            $map['id'] = array('in', $ids);
            $res = M('Msg', 'tab_')->where($map)->setField(array('status' => 1));
            $res = M('Msg', 'tab_')->where($map)->setField(array('read_time' => time()));
        }
        if (I('type') == 2){
            $this->redirect("http://".$_SERVER['HTTP_HOST']."admin.php?s=/Msg/lists");
        }else{
            $this->success('操作成功');
        }
    }
}
?>