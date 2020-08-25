<?php
// 应用入口文件

// 开启调试模式 建议开发阶段开启 部署阶段注释或者设为false
define('APP_DEBUG', TRUE);

//CLI
define('APP_MODE','cli');
// 采用CLI运行模式运行
define('MODE_NAME','cli');
// 定义应用目录
define('APP_PATH',dirname(__FILE__).'/Application/');
define('ROOTTT',dirname(__FILE__).'/');
//绑定模块
define('BIND_MODULE','Admin');//模块绑定根据自己的来

/**
 * 缓存目录设置
 * 此目录必须可写，建议移动到非WEB目录
 */
define ( 'RUNTIME_PATH', './Runtime/' );

// 引入ThinkPHP入口文件
require dirname( __FILE__).'/ThinkPHP/ThinkPHP.php';