<?php

namespace Callback\Controller;

use Common\Api\GameApi;


/**
 * 银联调控制器
 * @author 小纯洁
 */
class UnionController extends BaseController
{
    /**
     *通知方法
     */
    public function notify()
    {

        if (IS_POST && !empty($_POST)) {
            $notify = $_POST;
        } elseif (IS_GET && !empty($_GET)) {
            $notify = $_GET;
        }
        $date = date('Ymd');
        error_log(print_r([$notify,I('get.'),date('Y-m-d H:i:s')],true),3,"notify-{$date}");
    }
}
