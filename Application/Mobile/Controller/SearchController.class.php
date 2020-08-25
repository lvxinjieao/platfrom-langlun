<?php
namespace Mobile\Controller;
use Think\Controller;
use Common\Model\GameModel;
use Common\Model\DocumentModel;
use Common\Model\GiftbagModel;
use Com\WechatAuth;
class SearchController extends BaseController {
	//搜索首页 
	public function index() {
		$hotmap['recommend_status'] = 2;
        $hotmap['g.sdk_version'] = array('in',array(get_devices_type(),3));
        $map['g.sdk_version'] = array('in',array(get_devices_type(),3));
        $model = new GameModel();
        $hotgame = $model->getGameLists($hotmap,'g.sort desc ,g.id desc',1,9);
        $allgame = $model->getGameLists($map,'g.sort desc ,g.id desc',1,1000);
        $this->assign('titlegame',$hotgame[0]);
        $this->assign('allgame',$allgame);
        $this->assign('hotgame',$hotgame);
		$this->display();
	}
	
	// 异步返回
	public function search($keyword='') {
		empty($keyword)&&$this->ajaxReturn(array('code'=>-1,'msg'=>'未输入任何字符'));
		$gamemod = new GameModel();
		$gamemap['g.game_name'] = array('like','%'.$keyword.'%');
        $gamemap['g.sdk_version'] = array('in',array(get_devices_type(),3));
		$game=$gamemod->searchgame($gamemap,session('user_auth.user_id'));
        if(!session('union_host')){
            if(C('SMALL_PROGRAM_IS_SHOW') == 1){
                $samllgame = $this->getSmalllist($keyword);
            }
        }
		if(!empty($game)) {
            $docmodel = new DocumentModel();
            $docmap['d.belong_game'] = array('in', array_column($game, 'id'));
            $article = $docmodel->searchArticle(array('in', array('mobile_huodong', 'mobile_gg','mobile_zx','mobile_rule')), $docmap, 100);
            $article = $article ? $article : array();

            $giftmodel = new GiftbagModel();
            $giftgame = array('in', implode(',', array_column($game, 'id')));
            $gift = $giftmodel->getGiftLists(false,$giftgame, 1, 100);
            $gift = $gift ? $gift : array();
        }
        $game = $game ? $game : [];
        $gift = $gift ? $gift : [];
        $article = $article ? $article : [];
        $samllgame = $samllgame ? $samllgame : [];
        if($game || $samllgame ){
            $this->ajaxReturn(array('code'=>1,'msg'=>'搜索成功','url'=>U('index',array('kw'=>$keyword)),'data'=>array('game'=>$game,'gift'=>$gift,'article'=>$article,'smallgame'=>$samllgame)));
		}else{
			$this->ajaxReturn(array('code'=>0,'msg'=>'搜索失败','data'=>''));
		}
	}
    public function getSmalllist($keyword){
        $model = M("small_game","tab_");
        $map['status'] = 1;
        $map['game_name'] = array('like','%'.$keyword.'%');
        $order = "sort desc,id desc";
        $data = $model
            ->field("id,game_name,scan_num,icon,qrcodeurl,type,qrcode")
            ->where($map)
            ->order($order)
            ->group("id")
            ->select();
        foreach ($data as $key=>$v){
            $data[$key]['icon'] = get_cover($v['icon'],'path');
            if($v['qrcode']){
                $data[$key]['qrcode'] = get_cover($v['qrcode'],'path');
            }
        }
        return $data;
    }
	public function search_list(){
		$this->display();
	}
}