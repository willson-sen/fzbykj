PHP版SDK 基于PHP7.4进行开发，使用者提供注册mqtt所需的参数：

license：授权码（管理员发放）。 owner：所有者，准入过程登记的所属机构。 type：终端类型。 scope：地震消息类型，[EEW：地震预警]、[][][EQR：地震速报]。 callback_type：回调方式类型；[http:回调地址方式]、[redis:redis方式]、[mysql:mysql方式]、[file:文件方式]，默认为4。 callback_url：选择回调方式类型为1时填写，即设置一个http接口地址。 is_source_feedback：是否在收到地震信息之后进行反馈，[1:反馈]、[2:不反馈]；使用场景介绍在收到地震预警后需要给地震信息源进行消息反馈；如果该参数传参1那么SDK在收到需要反馈的地震产品信息后就会直接进行反馈，如果使用者传参为0，那么SDK则不进行信息反馈。 log_url：SDK日志保存位置，输入绝对文件位置；如无设置日志会保存在SDK根目录下的logs文件夹下；注：请确保文件夹有读写权限。 注：以上参数，以字符串类型进行传参。

请求示例：

使用者提供以上参数后，如无出现报错反馈，SDK会开启mqtt连接并根据传入的scope参数进行地震产品的订阅，且在收到订阅信息之后根据提供参数callback_type选择的回调方式类型进行返回。

更新composer下载必要的依赖包：

解除禁用php函数： shell_exec,pcntl_alarm,pcntl_fork,pcntl_wait，pcntl_signal,pcntl_signal_dispatch

使用示例
require_once "../vendor/autoload.php";

// 创建一个注册对象，用于向服务注册中心注册服务。 $client = new Register(

//授权码（管理员发放）。 "202409140957558qfGoAW1", // 所有者，准入过程登记的所属机构。

"SDK", // 终端类型。

"SDK", // 地震消息类型，[EEW：地震预警]、[][][EQR：地震速报]。

"EEW,EQR", // 回调方式类型；[http:回调地址方式]、[redis:redis方式]、[mysql:mysql方式]、[file:文件方式]，默认为4。

1, // 选择回调方式类型为1时填写，即设置一个http接口地址。

"http://tools.fzbykj.com/test", // 是否在收到地震信息之后进行反馈，[1:反馈]、[2:不反馈]；使用场景介绍在收到地震预警后需要给地震信息源进行消息反馈；如果该参数传参1那么SDK在收到需要反馈的地震产品信息后就会直接进行反馈，如果使用者传参为0，那么SDK则不进行信息反馈。 1,

// SDK日志保存位置，输入绝对文件位置；如无设置日志会保存在SDK根目录下的logs文件夹下；注：请确保文件夹有读写权限。 "/www/wwwroot/118.195.254.2_672/src/logs/registerService.log"

); // 注册服务并获取服务状态 $service = $client->registerService();

// 启用下面这行代码可以停止服务，但根据业务需求选择使用 // $service = $client->stopService();

二.回调支持的方法 （1）设置回调地址​ 创建一个接收地震的数据的接口，sdk中的mqtt在收到订阅的地震信息后向该接口推送数据，使用者通过接口收到的数据自行进行处理。

​ JSON格式：

（2）通过redis的方式 提供并配置好连接redis需要的参数，sdk中的mqtt在收到订阅的地震信息后会向redis推送这些数据；参数有：

host port db password sdk中redis的配置位置：toolsdk/sdk_config/RedisConfig.py

JSON格式：

（3）通过Mysql数据库的方式​ 提供并配置好连接mysql需要的参数，dk中的mqtt在收到订阅的地震信息后会向mysql推送这些数据；参数有：

host port username password db sdk中mysql的配置位置：toolsdk/sdk_config/MysqlConfig.py

存储mysql的表名：

存储mysql的表创建语句：

​

JSON格式：

（4）从文件中读取的方式 三.附件 (1) 服务对象接入账号API接口 接口地址 http://27.151.72.162:8888/xxfb_strategy/register/tm_register

请求方式 HTTP1.1/POST x-www-form-urlencoded

请求参数 序号 参数名 类型 是否必须 说明 1 license string 是 授权码(管理员发放) 2 owner string 是 所有者，准入过程登记的所属机构 3 type string 是 终端类型 响应参数 序号 参数名 类型 说明 1 clientId string 终端id(EMQX终端连接信息接口中获得) 2 uname string 终端用户名(EMQX终端连接信息接口中获得) 3 pwd string 终端密码(EMQX终端连接信息接口中获得) (2) 服务对象接入主题API接口 接口地址 http://27.151.72.162:8888/xxfb_strategy/register/tm_authorize

请求方式 HTTP1.1/POST x-www-form-urlencoded

请求参数 序号 参数名 类型 是否必须 说明 1 clientId string 是 终端id 2 scope string 是 地震消息类型[EEW:地震预警][EQR:参数速报][EIR:烈度速报][MT:地震矩张量][EL:余震分布] 3 uname string 是 终端用户名 4 pwd string 是 终端密码 响应参数 序号 参数名 类型 说明 1 mqtthost string mqtt的主机ip 2 port string mqtt的服务端口 3 topic string 终端监听主题 4 scope string 地震消息类型[EEW:地震预警] 5 sendTopic string 终端监听主题,与topic相同 6 ackTopic string 终端应答主题 (3) 预警格式 (4) 速报格式 (5) SDK日志 日志默认会保存在SDK根目录下的logs文件夹下。

四.调用方式记录 1.日志调用

文件2——用来单纯的存储接收到的地震信息
from utils.Logger import Logger

logger_creator2 = Logger(name='2',log_file='app2.log') log2 = logger_creator2.get_logger() log2.info('This is an info message') log2.warning('This is a warning message') log2.error('This is an error message') log2.debug('This is a debug message') 执行效果：

2024-09-13 14:43:12,704 - 2 - INFO - This is an info message 2024-09-13 14:43:12,705 - 2 - WARNING - This is a warning message 2024-09-13 14:43:12,705 - 2 - ERROR - This is an error message 2024-09-13 14:43:12,707 - 2 - DEBUG - This is a debug message ——————————————————————————————————————————————————

是否简化日志格式命令：

logger_save.set_detailed_format(detailed_format=False) 执行效果：

2024-09-13 14:43:12,704 - 2 - INFO - This is an info message 日志分两种：

（1）name="record"，存储全流程SDK日志记录

（2）name=”saveData“，存储订阅到的地震消息