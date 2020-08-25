<?php
namespace Media\Controller;
use Think\Controller;
use Common\Api\GameApi;
use Common\Model\GameModel;
use Common\Model\UserPlayModel;
use Common\Model\UserModel;
use Common\Model\DocumentModel;
use Common\Model\AdvModel;
use Common\Model\ServerModel;
use Admin\Model\PointTypeModel;
use Common\Model\GiftbagModel;
/**
* 首页
*/
class IndexController extends BaseController {

    public function index(){
        $Advmodel = new AdvModel();
        $Docmodel = new DocumentModel();
        $Advdata = $Advmodel->getAdv("slider_pc_wide",5);
        $this->assign('sliderWap',$Advdata);//轮播图
        //首页手游推荐
        $tuisy = $this->more_game(1,1,1,5);
        $ctsy = count($tuisy['data']);
        if($ctsy<5){
            $ntuisy = $this->more_game(1,0,1,5-$ctsy);
            foreach ($ntuisy['data'] as $key => $value) {
                $tuisy['data'][] = $value;
            }
        }
        $this->assign('tuisy',$tuisy);
        //首页H5推荐
        $tuih5 = $this->more_game(0,1,1,5);
        $cth5 = count($tuih5['data']);
        if($cth5<5){
            $ntuih5 = $this->more_game(0,0,1,5-$cth5);
            foreach ($ntuih5['data'] as $key => $value) {
                $tuih5['data'][] = $value;
            }
        }
        $this->assign('tuih5',$tuih5);
        //公告资讯
        $this->newzixun();
        //热门手游
        $hotsy = $this->more_game(1,2,1,4);
        if(count($hotsy['data'])<4){
            $nhotsy = $this->more_game(1,0,1,4-count($hotsy['data']));
            foreach ($nhotsy['data'] as $key => $value) {
                $hotsy['data'][] = $value;
            }
        }

        $this->assign('hotsy',$hotsy);
        //热门H5
        $hoth5 = $this->more_game(0,2,1,4);
        if(count($hoth5['data'])<4){
            $nhoth5 = $this->more_game(0,0,1,4-count($hoth5['data']));
            foreach ($nhoth5['data'] as $key => $value) {
                $hoth5['data'][] = $value;
            }
        }
        $this->assign('hoth5',$hoth5);
        //最新手游
        $newsy = $this->more_game(1,3,1,8);
        if(count($newsy['data'])<8){
            $nnewsy = $this->more_game(1,0,1,8-count($newsy['data']));
            foreach ($nnewsy['data'] as $key => $value) {
                $newsy['data'][] = $value;
            }
        }
        $this->assign('newsy',$newsy);
        //最新H5
        $newh5 = $this->more_game(0,3,1,8);
        if(count($newh5['data'])<8){
            $nnewh5 = $this->more_game(0,0,1,8-count($newh5['data']));
            foreach ($nnewh5['data'] as $key => $value) {
                $newh5['data'][] = $value;
            }
        }
        $this->assign('newh5',$newh5);
        $user = is_login();
        //礼包
        $giftbgmodel = new GiftbagModel();
        //手游
        $giftsymap['gb.giftbag_version'] = ['like',array('%1%','%2%'),'OR'];
        $gbsy = $giftbgmodel->getGiftLists(false,false,1,10,$giftsymap,$user,'gb.create_time desc');
        $this->assign('gbsy',$gbsy);
        //H5
        $gifth5map['gb.giftbag_version'] = ['like',array('%3%'),'OR'];
        $gbh5 = $giftbgmodel->getGiftLists(false,false,1,10,$gifth5map,$user,'gb.create_time desc');
        $this->assign('gbh5',$gbh5);

        //开服
        //手游
        $syserver = $this->server(1);
        foreach ($syserver as $key => $value) {
            $syservernew[$key] = array_chunk($value,8);
        }
        $this->assign('syserver',$syservernew);
        //H5
        $h5server = $this->server(0);
        foreach ($h5server as $key => $value) {
            $h5servernew[$key] = array_chunk($value,8);
        }
        $this->assign('h5server',$h5servernew);

        //活动专题
        $hdadv = $this->get_article_lists();
        $this->assign('hdadv',$hdadv['data']);
        //友情链接
        $links = M('links','tab_')->field('link_url,title')->where(array('mark'=>0))->select();
        $this->assign('links',$links);
        $this->display();
    }

    public function more_game($is_sy=0,$rec_status='',$p=1,$limit=10){
        if($_REQUEST['game_id']>0){
            $map['g.id'] = array('neq',$_REQUEST['game_id']);
        }
        $map['recommend_status'] = array('like',"%".$rec_status."%");
        if($is_sy==1){
            $map['g.sdk_version'] = ['neq',3];
        }else{
            $map['g.sdk_version'] = ['eq',3];
        }
        $model = new GameModel();
        $data = $model->getGameLists($map,'g.sort desc, g.id desc',$p,$limit,$user_id,'g.relation_game_id');
        if(empty($data)){
            $res['status'] = 0;
        }else{
            $res['status'] = 1;
            $res['data'] = $data;
        }
        if(IS_AJAX){
            $this->ajaxReturn($res,'json');
        }else{
            return $res;
        }
    }
    public function newzixun($limit='',$name='wap_gg,wap_zixun',$map=[]){
        $map['sys_category.name']=array('in',$name);//显示公告和资讯
        $map['sys_document.status']=1;
        $map['sys_document.display']=1;
        $map['deadline']=array(array('gt',time()),array('eq',0),'or');
        $data=M('document')
                ->field('sys_category.name,cover_id,category_id,sys_document.description,sys_document.title,sys_document.update_time,sys_document.id')
                ->join('sys_category on sys_document.category_id = sys_category.id and sys_category.pid = 53')
                ->where($map)
                ->limit($limit)
                ->order('level desc,id desc')
                ->select();
        foreach ($data as $key => $value) {
            if($value['name']=='wap_huodong'){
                $huodong[]=$value;
                unset($data[$key]);
            }
            if($value['name']=='wap_gg'){
                $media_gg[]=$value;
                unset($data[$key]);
            }
            if($value['name']=='wap_zixun'){
                $media_zx[]=$value;
                unset($data[$key]);
            }
        }
        $this->assign('firhuodong',$huodong[0]);
        unset($huodong[0]);  
        $this->assign('huodong',$huodong);
        $this->assign('media_gg',$media_gg);
        $this->assign('media_zx',$media_zx);
        $this->assign('newzixun',$data);
    }
    //开服
    public function server($issy,$p=1,$row=100){
        $model = new ServerModel();
        $user = is_login();
        if($issy==1){
            $data['yi'] = $model->server('1,2',0,$p,$row,$user);
            $data['new'] = $model->server('1,2',1,$p,$row,$user);
        }else{
            $data['yi'] = $model->server('3',0,$p,$row,$user);
            $data['new'] = $model->server('3',1,$p,$row,$user);
        }
        return $data;
    }
    //开服通知
    public function setServerNotice($server,$type){
        $user = is_login();
        $model = new ServerModel();
        $result = $model->set_server_notice($user,$server,$type);
        if($result!==false){
            $res['code'] = 1;
        }else{
            $res['code'] = 0;
        }
        $this->ajaxReturn($res,'json');
    }
    //获取活动
    public function get_article_lists($p=1,$category=6){
        switch ($category) {
            case 4://资讯
                $category_name = "wap_zx";
                $row = 10;
                break;
            case 5://公告
                $category_name = "wap_gg";
                $row = 5;
                break;
            case 6://活动
                $category_name = "wap_huodong";
                $row = 10;
                break;
        }
        $model = new DocumentModel();
        if(is_login()){
            $hdmark = $model->hdmarkrec(is_login());
        }else{
            $hdmark = false;
        }
        $data = $model->getArticleListsByCategory('',$category_name,$p,$row);
        if($data===false){
            $res['status'] = 0;
            $res['hdmark'] = 1;
        }else{
            $res['status'] = 1;
            $res['data'] = $data;
            $res['hdmark'] = $hdmark===false?0:1;
        }
        return $res;
    }

    public function download(){
        $android = M('app','tab_')->field('file_url,file_size')->where(array('app_version'=>1))->find();
        $android['file_url'] = 'http://'.$_SERVER['HTTP_HOST'].substr($android['file_url'],1);
        $ios = M('app','tab_')->field('plist_url,file_size')->where(array('app_version'=>0))->find();
        $ios['plist_url'] = "itms-services://?action=download-manifest&amp;url=https://".$_SERVER['HTTP_HOST'].substr($ios['plist_url'],1);
        $type = get_devices_type();
        $this->assign('android',$android);
        $this->assign('ios',$ios);
        $this->display();
    }
}  
