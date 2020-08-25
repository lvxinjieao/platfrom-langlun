<?php

namespace Admin\Controller;

use Think\Controller;

class CosController extends Controller {

      public function cosupload($src,$dst,$d=1){
         Vendor('Cos.include');
          $Cosapi= new \qcloudcos\Cosapi();
             $Cosapi->setRegion(C('cos_storage.domain'));//设置区域
          if($d==2){
             $ret = $Cosapi->delFile(C('cos_storage.bucket'),$dst,C('cos_storage.AccessKey'),C('cos_storage.SecretId'),C('cos_storage.SecretKey'));
          }else{
            $Cosapi->setTimeout(0);
         
            $ret =  $Cosapi->upload(C('cos_storage.bucket'), $src, $dst,C('cos_storage.AccessKey'),C('cos_storage.SecretId'),C('cos_storage.SecretKey'));
            if($ret['code']==0){
                $url=$ret['data']['source_url'];
                return $url;
            }else{
                return 0;
            }
          }
    }


}
