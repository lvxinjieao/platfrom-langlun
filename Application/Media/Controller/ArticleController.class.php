<?php
namespace Media\Controller;
use Think\Controller;
use Common\Model\GameModel;
use Common\Model\ServerModel;
use Think\Model;
use Common\Model\AdvModel;
use Common\Model\GiftbagModel;
use Common\Model\DocumentModel;

class ArticleController extends BaseController {
	public function __construct() {
		parent::__construct();
	}

	public function index($p=1,$category=null) {
		/* 分类信息 */
		$row = 4;
		if($category=="media_zx"){
		    $category = ['title'=>'全部文章'];
		    $data = D('Document')->doc_lists_array('wap_huodong,wap_gg,wap_zixun,wap_glue','',$p,$row);
		    $count = count(D('Document')->doc_lists_array('wap_huodong,wap_gg,wap_zixun,wap_glue'));
		}else {
		    $category = $this->category();
		    $data = D('Document')->doc_lists($category['name'],'',$p,$row);
		    $count = count(D('Document')->doc_lists($category['name']));
		}
		$this->set_page($count,$row);
		$this->assign('data',$data);
		//频道页只显示模板，默认不读取任何内容
		//内容可以通过模板标签自行定制
		/* 模板赋值并渲染模板 */
		$this->assign('category', $category);
		//最新资讯
		$newzz = D('Document')->doc_lists_order('media_zixun','level desc,create_time desc');
		$this->assign('newzz', $newzz);
		//热门手游游戏
		$model = new GameModel();
        $map['recommend_status'] = array('like','%'."2"."%");
        $map['g.sdk_version'] = array('neq',3);
        $syhotgame = $model->getHotGame($map,'g.sort desc,g.id desc');
        $this->assign('syhot',$syhotgame);
        //热门H5
        $map['g.sdk_version'] = array('eq',3);
        $h5hotgame = $model->getHotGame($map,'g.sort desc,g.id desc');
        $this->assign('h5hot',$h5hotgame);
        //热门攻略
		$hotgl = D('Document')->doc_lists_order('media_gonglue','level desc,create_time desc');
		$this->assign('hotgl', $hotgl);
		$this->display();
	}
	
	/* 文档分类检测 */
	public function category($id = 0){
		/* 标识正确性检测 */
		$id = $id ? $id : I('get.category', 0);
		if(empty($id)){
			$this->error('没有指定文档分类！');
		}
		/* 获取分类信息 */
		$category = D('Category')->info($id);
		if($category && 1 == $category['status']){
			switch ($category['display']) {
				case 0:
					$this->error('该分类禁止显示！');
					break;
				//TODO: 更多分类显示状态判断
				default:
					return $category;
			}
		} else {
			$this->error('分类不存在或被禁用！');
		}
	}
	
	public function detail($id=0) {
		
		/* 标识正确性检测 */
		if(!($id && is_numeric($id))){
			$this->error('文档ID错误！');
		}

		/* 获取详细信息 */
		$Document = D('Document');
		$info = $Document->detail($id);
		if(!$info){
			$this->error($Document->getError());
		}
		$this->assign('info', $info);

		/* 获取上下篇*/
		$where['category_id'] = array('in',array('54','55','56','57'));
        $where['status'] = 1;
        $where['deadline'] = array(array('eq',''),array('gt',time()), 'or') ;
		$all = $Document->where($where)->field('id,title')->order('level desc,id desc')->select();
        foreach ($all as $key => $value){
            if ($all[$key]['id'] == $info['id']){
                $prev = $key-1;
                $next = $key+1;
            }
        }
		$this->assign('prev', !empty($all[$prev])?$all[$prev]:'');
		$this->assign('next', !empty($all[$next])?$all[$next]:'');
		
		/* 相关文章 */
		$alike = $Document->alike($id,$info['belong_game'],4);
		$this->assign('alike',$alike);
		
		/* 更新浏览数 */
		$map = array('id' => $id);
		$Document->where($map)->setInc('view');
		
		/*礼包  */
        $gmodel = new GiftbagModel();
        $gdata = $gmodel->getGiftLists(false,false,1,10,[],'','gb.id desc');
        $this->assign('giftlist',$gdata);
		
		/*开服表  */
		$smodel = new ServerModel();
        $smap['game_id'] = $info['belong_game'];
        $server = $smodel->serverOrder($smap);
        $this->assign('server',$server);
		
		

        //热门手游游戏
		$model = new GameModel();
        $gmap['recommend_status'] = array('like','%'."2"."%");
        $gmap['g.sdk_version'] = array('neq',3);
        $syhotgame = $model->getHotGame($gmap,'g.sort desc,g.id desc');
        $this->assign('syhot',$syhotgame);
        //热门H5
        $gmap['g.sdk_version'] = array('eq',3);
        $h5hotgame = $model->getHotGame($gmap,'g.sort desc,g.id desc');
        $this->assign('h5hot',$h5hotgame);

        /*广告  */
        $Advmodel = new AdvModel();
        $Advdata = $Advmodel->getAdv("detail_article_media",1)[0];
        $this->assign('adv',$Advdata);
        
		$this->display();
	}
	

	public function news($type='') {
	    if (empty($type)) {return;}
	    $name = 'wap_'.$type;
	    $category = $this->category($name);
	    $news = M("Document")->field("d.id")->table("__DOCUMENT__ as d")
	    ->join("__CATEGORY__ as c on(c.id=d.category_id and c.name='$name')",'right')
	    ->where("d.status>0 and d.display=1")->find();
	    $doc = new DocumentModel();  
	    $data = $doc->articleDetail($news['id']);
	    $this->assign('data',$data);
	    $this->assign('category',$category);
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