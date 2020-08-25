<?php
namespace Mobile\Controller;
use Think\Controller;
use Common\Api\GameApi;
use Common\Model\GameModel;
use Common\Model\UserPlayModel;
use Common\Model\UserModel;
use Common\Model\DocumentModel;
use Common\Model\AdvModel;
use Common\Model\ServerModel;

/**
* 首页
*/
class ArticleController extends BaseController {

    public function detail($id='',$type=''){
    	empty($id)&&$this->ajaxReturn(array('code'=>0,'msg'=>'缺少文章id'));
        if (!empty($type) && $type==1){
            $this->assign('type',$type);//App隐藏头部栏
        }
        $model = new DocumentModel();
        $data = $model->articleDetail($id);
        if($data===false){
        	$this->ajaxReturn(array('code'=>0,'msg'=>'文章不存在'));
        }
        if($data['share_cover']){
            $data['share_cover'] = get_cover($data['share_cover'],'path');
            if(strpos($data['share_cover'],'http') === false){
                $data['share_cover'] = 'http://'.$_SERVER['HTTP_HOST'].$data['share_cover'];
            }
        }else{
            $data['share_cover'] = 'http://'.$_SERVER['HTTP_HOST'].'/Public/static/images/pic_zhanwei.png';
        }
        $this->assign('data',$data);
        $this->display();
    }
    public function agreement() {
    	$category=D('category')->where(array('name'=>'wap_ur'))->find();
        $docu=D('document')->where(array('category_id'=>$category['id'],'status'=>1))->find();
    	$document_article=D('document_article')->where(array('id'=>$docu['id']))->find();
    	$this->assign('article',$document_article['content']); 
        $this->display();
    }
}  
