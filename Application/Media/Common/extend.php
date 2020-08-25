<?php

function is_login_user(){
    $user = session('user_auth');
    if (empty($user)) {
        return 0;
    } else {
        return session('user_auth_sign') == data_auth_sign($user) ? $user['user_id'] : 0;
    }
}

function user_play($user_id = 0)
{
    $user_play = M("play","tab_user_");
    $map["user_id"] = $user_id;
    $data = $user_play->where($map)->find();
    if(empty($data)){
        return false;
    }
    return $data;
}

function get_uid()
{
    return session("user_auth.uid");
}

function get_uname(){
    return session("user_auth.account");
}
function index_show($param=array()){
    $paramcount=count($param);
    if($paramcount>0){
        $paramm[0][]=$param[0];
        $paramm[0][]=$param[1];
    }
    if($paramcount-2>0){
        $paramm[1][]=$param[2];
        $paramm[1][]=$param[3];
    }
    if($paramcount-4>0){
        $paramm[2][]=$param[4];
        $paramm[2][]=$param[5];
    }
    foreach ($paramm as $key => $value) {
        foreach ($value as $k => $v) {
            if($v==''){
                unset($paramm[$key][$k]);
            }
        }
    }
    return $paramm;
}
function get_cover_id($game_name=''){
    if($game_name==''){
        return false;
    }
    $map['game_name']=$game_name;
    $icon = M('Game','tab_')->field('icon')->where($map)->find();
    return $icon['icon'];
}
function downloadFile($filename,$allowDownExt=array ( 'rar','zip','apk','ipa')){
   //获取文件的扩展名
   //获取文件信息
   $fileext=pathinfo($filename);
   //检测文件类型是否允许下载
   if(!in_array($fileext['extension'],$allowDownExt)) {
      return false;
   }
   switch( $fileext['extension']) {
      case "pdf": $ctype="application/pdf"; break;
      case "exe": $ctype="application/octet-stream"; break;
      case "zip": $ctype="application/zip"; break;
      case "doc": $ctype="application/msword"; break;
      case "xls": $ctype="application/vnd.ms-excel"; break;
      case "ppt": $ctype="application/vnd.ms-powerpoint"; break;
      case "gif": $ctype="image/gif"; break;
      case "png": $ctype="image/png"; break;
      case "jpeg":
      case "jpg": $ctype="image/jpg"; break;
      case "mp3": $ctype="audio/mpeg"; break;
      case "wav": $ctype="audio/x-wav"; break;
      case "mpeg":
      case "mpg":
      case "mpe": $ctype="video/mpeg"; break;
      case "mov": $ctype="video/quicktime"; break;
      case "avi": $ctype="video/x-msvideo"; break;
      //The following are for extensions that shouldn't be downloaded (sensitive stuff, like php files)
      // case "php":
      // case "htm":
      // case "html":
      // case "txt": die("<b>Cannot be used for ". $file_extension ." files!</b>"); break;
 
      default: $ctype="application/force-download";
    }
    $header_array = get_headers($filename, true);
    $len = $header_array['Content-Length'];
    //Begin writing headers
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: public"); 
    header("Content-Description: File Transfer");
     
    //Use the switch-generated Content-Type
    header("Content-Type: $ctype");
 
    //Force the download
    $header="Content-Disposition: attachment; filename=".$fileext['basename'].";";
    header($header );
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: ".$len);
    @readfile($filename);
    exit;
}