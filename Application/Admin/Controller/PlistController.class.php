<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;

/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
*/
class PlistController extends ThinkController {
    
 //生成游戏渠道plist文件
    public function create_plist($game_id=0,$promote_id=0,$marking="",$url=""){
        $xml = new \DOMDocument();
        $xml->load('./Uploads/Plist/testdemo.plist');
        $online = $xml->getElementsByTagName('dict');//查找节点
        $asd=$online->item(1)->getElementsByTagName('string');//第二个节点下所有string
        foreach ($asd as $key=>$value) {
            switch ($value->textContent) {
                case 'ipa_url':
                if(preg_match("/Uploads/", $url)){
                  $value->nodeValue="https://".$_SERVER['HTTP_HOST'].ltrim($url,".");//"https://iosdemo.vlcms.com/app/MCHSecretary.ipa";//替换xml对应的值    
                }else{
                $value->nodeValue=$url;
                }
                    break;
                case 'icon':
                $value->nodeValue="https://".$_SERVER["HTTP_HOST"].get_cover(get_game_icon_id($game_id),'path');;                
                    break;
                 case 'com.dell':
                $value->nodeValue=$marking;
                    break;
                case '1.0.0':
                $value->nodeValue=game_version($game_id);
                    break;
                case 'mchdemo':
                $value->nodeValue=get_ios_game_name($game_id);
                    break;

            }
            if($promote_id==0){
            $xml->save("./Uploads/SourcePlist/$game_id.plist");
            }else{
            $pname=$game_id."-".$promote_id;
            $xml->save("./Uploads/GamePlist/$pname.plist");
            }
        }
        if($promote_id==0){
          return "./Uploads/SourcePlist/$game_id.plist";
        }else{
          return "./Uploads/GamePlist/$pname.plist";
        }


    }


    //生成App plist文件
    public function create_plist_app($version="",$promote_id=0,$marking="",$url=""){
        $xml = new \DOMDocument();
        $xml->load('./Uploads/Plist/testdemo.plist');
        $online = $xml->getElementsByTagName('dict');//查找节点
        $asd=$online->item(1)->getElementsByTagName('string');//第二个节点下所有string
        foreach ($asd as $key=>$value) {
            switch ($value->textContent) {
                case 'ipa_url':
                    if(preg_match("/Uploads/", $url)){
                      $value->nodeValue="https://".$_SERVER['HTTP_HOST'].ltrim($url,".");//"https://iosdemo.vlcms.com/app/MCHSecretary.ipa";//替换xml对应的值    
                    }else{
                        $value->nodeValue=$url;
                    }
                    break;
                case 'icon':
                    $value->nodeValue="https://".$_SERVER["HTTP_HOST"].get_cover(C('APP_ICON'),'path');;                
                    break;
                case 'com.dell':
                    $value->nodeValue=$marking==""?'app':$marking;
                    break;
                case '1.0.0':
                    $value->nodeValue=$version==""?'2':$version;
                    break;
                case 'mchdemo':
                    $value->nodeValue="APP";
                    break;

            }

            $pname=$promote_id;
            if($promote_id==0){
                  $xml->save("./Uploads/SourcePlist/$pname.plist");    

            }else{
                  $xml->save("./Uploads/AppPlist/$pname.plist");    
            }
        }

        if($promote_id==0){
            return "./Uploads/SourcePlist/$pname.plist";
        }else{
           return "./Uploads/AppPlist/$pname.plist";
        }
    }

	/**
	 * 重写 create_plist 方法。方便 开放平台 调用
	 * @param int $game
	 * @param int $promote_id
	 * @param string $marking
	 * @param string $url
	 * author: xmy 280564871@qq.com
	 */
	public function createPlist($game = 0, $promote_id = 0, $marking = "", $url = "")
	{
		$xml = new \DOMDocument();
		$xml->load('./Uploads/Plist/testdemo.plist');
		$online = $xml->getElementsByTagName('dict');//查找节点
		$asd = $online->item(1)->getElementsByTagName('string');//第二个节点下所有string
		foreach ($asd as $key => $value) {
			switch ($value->textContent) {
				case 'ipa_url':
					if (preg_match("/Uploads/", $url)) {
						$value->nodeValue = "https://" . $_SERVER['HTTP_HOST'] . ltrim($url, ".");//"https://iosdemo.vlcms.com/app/MCHSecretary.ipa";//替换xml对应的值
					} else {
						$value->nodeValue = $url;
					}
					break;
				case 'icon':
					$value->nodeValue = "https://" . $_SERVER["HTTP_HOST"] . get_cover($game['icon'], 'path');;
					break;
				case 'com.dell':
					$value->nodeValue = $marking;
					break;
				case '1.0.0':
					$value->nodeValue = $game['sdk_version'];
					break;
				case 'mchdemo':
					$value->nodeValue = substr($game['game_name'], 0, strpos($game, "("));
					break;

			}
			if ($promote_id == 0) {
				$xml->save("./Uploads/SourcePlist/{$game['id']}.plist");
			} else {
				$pname = $game['id'] . "-" . $promote_id;
				$xml->save("./Uploads/GamePlist/$pname.plist");
			}
		}
	}
}