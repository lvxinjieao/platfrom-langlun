<?php
/**
 * 系统检查
 * User: lwx
 * Date: 2018/4/17
 */
namespace Admin\Controller;

use Think\Controller;

class CheckController extends ThinkController
{

    const model_name = 'check';

    /*
     * 列表
     */
    public function index()
    {

        if (!empty($_REQUEST['type'])) {
            $extend['type'] = $_REQUEST['type'];
            unset($_REQUEST['type']);
        }

        if (!empty($_REQUEST['ctype'])) {
            $ctype = I('ctype');
            $extend['type'] = ['like',["$ctype%"]];
            unset($_REQUEST['ctype']);
        }


        if (!empty($_REQUEST['start']) && !empty($_REQUEST['end'])) {

            $extend['create_time'] = array('BETWEEN', array(strtotime(I('start')), strtotime(I('end')) + 24 * 60 * 60 - 1));

        } elseif (!empty($_REQUEST['start'])) {

            $extend['create_time'] = array('egt', strtotime(I('start')));

        } elseif (!empty($_REQUEST['end'])) {

            $extend['create_time'] = array('elt', strtotime(I('end')) + 24 * 60 * 60 - 1);

        }

        $extend['status'] = ['neq',2];

        $this->m_title = '系统检查';

        parent::flists(self::model_name, $_GET["p"], $extend, 'url');
    }

    /*
     * 更改状态
     */
    public function status()
    {

        parent::set_status(self::model_name);

    }

    /*
     * 检测用户相关
     */
    public function checkUser()
    {

        $check = D(self::model_name);

        //清空数据
        $res = M('check','tab_')->where(['type'=>['like','1%']])->delete();

        if ($check->checkUser() > 0) {

            $this->success($check->getError());

        } else {

            $this->error($check->getError());

        }

    }

    /*
     * 检测提现相关
     */
    public function checkWithdraw()
    {

        //清空数据
        M('check','tab_')->where(['type'=>['like','2%']])->delete();

        $check = D(self::model_name);

        if ($check->checkWithdraw() > 0) {

            $this->success($check->getError());

        } else {

            $this->error($check->getError());

        }

    }

    /*
     * 检测推广员相关
     */
    public function checkPromote()
    {

        //清空数据
        M('check','tab_')->where(['type'=>['like','3%']])->delete();

        $check = D(self::model_name);

        if ($check->checkPromote() > 0) {

            $this->success($check->getError());

        } else {

            $this->error($check->getError());

        }

    }

    /*
     * 一键检测
     */
    public function checkOne()
    {

        //清空数据
        $res = M('check','tab_')->where(1)->delete();
        
        $check = D(self::model_name);

        if ($check->checkOne() > 0) {

            $this->success($check->getError());

        } else {

            $this->error($check->getError());

        }

    }

}