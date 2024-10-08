# PHP版SDK 基于PHP8.0进行开发，使用者提供注册mqtt所需的参数：
    license：授权码（管理员发放） 
    owner：所有者，准入过程登记的所属机构
    type：终端类型。 scope：地震消息类型，[EEW：地震预警]、[EQR：地震速报]
    callback_type：回调方式类型[http:回调地址方式]、[redis:redis方式]、[mysql:mysql方式]、[file:文件方式]默认为4
    callback_url：选择回调方式类型为1时填写，即设置一个http接口地址
    is_source_feedback：是否在收到地震信息之后进行反馈[1:反馈]、[2:不反馈]使用场景介绍在收到地震预警后需要给地震信息源进行消息反馈；如果该参数传参1那么SDK在收到需要反馈的地震产品信息后就会直接进行反馈，如果使用者传参为0，那么SDK则不进行信息反馈
    log_url：SDK日志保存位置，输入绝对文件位置；如无设置日志会保存在SDK根目录下的logs文件夹下，注：请确保文件夹有读写权限。 注：以上参数，以字符串类型进行传参。

# 请求示例：
    在sdk中的test文件夹中test.php有具体的调用示例
    解除禁用php函数： shell_exec、pcntl_alarm、pcntl_fork、pcntl_wait、pcntl_signal、pcntl_signal_dispatch
    使用者提供以上参数后，如无出现报错反馈，SDK会开启mqtt连接并根据传入的scope参数进行地震产品的订阅，且在收到订阅信息之后根据提供参数callback_type选择的回调方式类型进行返回。

# 使用示例
    使用composer 下载依赖包  composer require fzbykj/quake-sdk若无法下载
    请尝试创建composer.json文件指定源地址
    {
        "repositories": [
            {
                "type": "vcs",
                "url": "https://github.com/willson-sen/fzbykj"
            },
            {
                "type": "composer",
                "url": "https://mirrors.aliyun.com/composer/"
            }
        ],
        "require": {
            "fzbykj/quake-sdk": "dev-main",
            "ext-json": "*",
            "ext-redis": "*",
            "ext-curl": "*",
            "ext-mbstring": "*",
            "ext-pdo": "*"
        }
    }
    执行 composer update

# 在需要引用SDK的php文件前，先引入autoload.php文件
    // 创建一个注册对象，用于向服务注册中心注册服务。 
        $client = new Register(
            "202409140957558qfGoAW1",
            "SDK",
            "SDK",
            "EEW,EQR",
            1,
            "http://tools.fzbykj.com/test",
            "/www/wwwroot/test/test/logs/registerService.log"
    );
    注册服务并获取服务状态 
    $service = $client->registerService();
    LINUX用下面这行代码可以停止服务，但根据业务需求选择使用,WIN系统下只需要关闭bat弹窗即可
    $service = $client->stopService();

# 回调支持的方法 
    （1）设置回调地址 创建一个接收地震的数据的接口，sdk中的mqtt在收到订阅的地震信息后向该接口推送数据，使用者通过接口收到的数据自行进行处理。
    JSON格式：
    （2）通过redis的方式 提供并配置好连接redis需要的参数，sdk中的mqtt在收到订阅的地震信息后会向redis推送这些数据；参数有：
    host port db password sdk中redis的配置位置：fjbykj/quake-sdk/src/config.php
    JSON格式：
    （3）通过Mysql数据库的方式 提供并配置好连接mysql需要的参数，dk中的mqtt在收到订阅的地震信息后会向mysql推送这些数据；参数有：
    host port username password db sdk中mysql的配置位置：fjbykj/quake-sdk/src/config.php
    存储mysql的表名：
    fa_mqtt_msg
    存储mysql的表创建语句：
    CREATE TABLE `fa_mqtt_msg`  (
        `id` int(10) NOT NULL AUTO_INCREMENT,
        `unique` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '事件唯一ID',
        `topic` varchar(250) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'mqtt主题',
        `msg` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT 'mqtt内容',
        `clientid` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '终端ID',
        `origin_event_main` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '预警事件ID+报数,以下划线分割',
        `event_main` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '地震事件',
        `event_num` varchar(5) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '0' COMMENT '第几报,1为首报,255为终报',
        `locName` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '震中地名',
        `lng` decimal(9, 3) NULL DEFAULT NULL COMMENT '经度,精度：小数点后三位，单位：度',
        `lat` decimal(9, 3) NULL DEFAULT NULL COMMENT '纬度,精度：小数点后三位，单位：度',
        `magnitude` decimal(5, 1) NULL DEFAULT NULL COMMENT '地震等级,精度：一位小数',
        `intensity` int(10) NULL DEFAULT 0 COMMENT '震中烈度',
        `depth` int(10) NULL DEFAULT 0 COMMENT '深度，单位：km',
        `ack_status` tinyint(1) NULL DEFAULT 0 COMMENT '是否消息反馈',
        `ack_msg` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '反馈消息',
        `createtime` bigint(16) NULL DEFAULT 0 COMMENT '创建时间',
        PRIMARY KEY (`id`) USING BTREE
        ) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;
    JSON格式：
    （4）从文件中读取的方式 
    默认存储在fjbykj/quake-sdk/src/sdk_callback文件夹中
# 常见问题：
    如果mqtt提示链接成功1秒后重连，请确定您的MQTT客户端是否有在其他地方连接。或更换客户端ID后重试。
    linux 系统下，直接执行$service = $client->registerService(); 注册服务并获取服务状态
    Win系统下需手动执行run_script.bat文件,若启动失败，请指定run_script.bat中的PHP_PATH路径