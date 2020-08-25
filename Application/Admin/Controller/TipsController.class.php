<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;
/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class TipsController extends ThinkController {
    public function real_name_auth(){
        if(IS_POST){
            $data = $_POST;
            if(isset($data['end_time'])&&$data['end_time'] == ''){
                $this->error('请设置截止日期');
            }
            $a = new ToolController();
            $re = $a->save($data);
            $this->success('保存成功');
        }else{
            $data = I('version') == 1? C('h5_age'):C('sy_age');
            $this->assign('data',$data);
            $this->meta_title = '实名认证设置';
            $this->display();
        }
    }
    public function addiction_auth(){
        $data = C('sy_age_prevent');
        $this->assign('data',$data);
        $this->meta_title = '防沉迷设置';
        $this->display();
    }
    public function set_status()
    {
        parent::set_status(self::model_name);
    }
}
