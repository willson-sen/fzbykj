<?php
require_once "../vendor/autoload.php";
// 创建一个注册对象，用于向服务注册中心注册服务
$client = new Register(
    //授权码（管理员发放）。
    "20240919113432c38Ou9j7",
    // 所有者，准入过程登记的所属机构。
    "SDK",
    // 终端类型。
    "SDK",
    // 地震消息类型，[EEW：地震预警]、[EQR：地震速报]。
    "EEW,EQR",
    // 回调方式类型；[http:回调地址方式]、[redis:redis方式]、[mysql:mysql方式]、[file:文件方式]，默认为4。
    1,
    // 选择回调方式类型为1时填写，即设置一个http接口地址。
    "http://tools.fzbykj.com/test",
    // 是否在收到地震信息之后进行反馈，[1:反馈]、[2:不反馈]；使用场景介绍在收到地震预警后需要给地震信息源进行消息反馈；如果该参数传参1那么SDK在收到需要反馈的地震产品信息后就会直接进行反馈，如果使用者传参为0，那么SDK则不进行信息反馈。
    1,
    // SDK日志保存位置，输入绝对文件位置；如无设置日志会保存在SDK根目录下的logs文件夹下；注：请确保文件夹有读写权限。
//    "/www/wwwroot/test/logs/registerService.log"
    "/www/wwwroot/test/logs/registerService.log"
);
// 注册服务并获取服务状态
$service = $client->registerService();
var_dump($service);
// 启用下面这行代码可以停止服务，但根据业务需求选择使用
// $service = $client->stopService();
