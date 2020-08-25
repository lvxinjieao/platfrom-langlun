<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;
/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class GameTypeController extends ThinkController {
    const model_name = 'GameType';

    public function lists(){
        parent::lists(self::model_name,$_GET["p"],$extend);
    }

    public function add($model='')
    {
        if(IS_POST){
            action_log('add_game_type','gametype',I('type_name',''),UID);
        }
        parent::add($model);
    }
    
    public function edit($model='',$id=0)
    {
        if(IS_POST){
            $type_name = M('game_type','tab_')->where(['id'=>$id])->getField('type_name');
            action_log('edit_game_type','gametype',$type_name,UID);
        }
        parent::edit($model,$id);
    }

    public function del($model = null, $ids=null)
    {
        if(empty($ids))$this->error('请选择要操作的数据');
        if(!is_array($ids)){
            $type_name = M('game_type','tab_')->where(['id'=>$ids])->getField('type_name');
            action_log('del_game_type','gametype',$type_name,UID);
        }else{
            action_log('del_game_type_batch','gametype',UID,UID);
        }
        $model = M('Model')->getByName(self::model_name); /*通过Model名称获取Model完整信息*/
        parent::del($model["id"],$ids);
    }

    public function set_status()
    {
        parent::set_status(self::model_name);
    }
}
