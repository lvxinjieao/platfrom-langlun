<?php
/**
 * 清除系统缓存
 * Created by PhpStorm.
 * User: xmy
 * Date: 2017/2/16
 * Time: 9:10
 */
namespace Admin\Controller;

use Think\Controller;

class ClearCacheController extends Controller{

    public function clear(){
        $cache_dirs = RUNTIME_PATH;
        $this->rmdirr ( $cache_dirs );

        @mkdir ( $cache_dirs, 0777, true );
        $this->success("缓存清除成功！");
    }


    private function rmdirr($dirname) {
        if (! file_exists ( $dirname )) {
            return false;
        }
        if (is_file ( $dirname ) || is_link ( $dirname )) {
            return unlink ( $dirname );
        }
        $dir = dir ( $dirname );
        //递归删除
        if ($dir) {
            while ( false !== $entry = $dir->read () ) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }
                $this->rmdirr ( $dirname . DIRECTORY_SEPARATOR . $entry );
            }
        }
        $dir->close ();
        return rmdir ( $dirname );
    }
}