<?php
use Workerman\Worker;
require_once "../vendor/autoload.php";
$worker = new Worker();
$worker->name='registerService';
$worker->onWorkerStart = function (){
    $redis = new \Redis();
    $redis->connect( REDIS_HOST, REDIS_PORT);
    $redis->select(14);
    $scopes_results = $redis->get("scopes_results");
    $account_info = $redis->get("account_info");
    $scopes_results = json_decode($scopes_results,true);
    $account_info = json_decode($account_info,true);
    $topic = [];
    $mqtt_ip = "";
    $port = "";
    foreach ($scopes_results as $value){
        $mqtt_ip = $value['host'];
        $port = $value['port'];
        if($value["scope"] != "ACK"){
            $topic[$value["topic"]] = 1;
        }else{
            $ack_topic = $value["topic"];
        }
    }
    $p = [
        'username' => $account_info["uname"],
        'password' => $account_info["pwd"],
        'client_id' => $account_info["clientId"],
        'qos'=>1,
//        "debug"=>1
    ];
    //连接mqtt主题
    $mqtt = new Workerman\Mqtt\Client('mqtts://' . $mqtt_ip . ':'.$port, $p);
    //订阅主题
    $mqtt->onConnect = function($mqtt) use ($topic){
        $mqtt->subscribe($topic,['qos'=>1]);//订阅的主题
    };
    //收到主题推送的消息
    $mqtt->onMessage = function ($topic, $content) use ($account_info,$redis){
        $sdk_config = $redis->get("sdk_config");
        $sdk_config = json_decode($sdk_config,true);
        error_log(date("Y-m-d H:i:s",time()).PHP_EOL."收到主题推送的消息".PHP_EOL.var_export($content,true). PHP_EOL, 3, $sdk_config["log_url"]);
        if($sdk_config["is_source_feedback"]){
            //反馈内容[演练没有反馈]
        }
        switch ($sdk_config["callback_type"]){
            // 1:http回调
            case 1:
                api_post($sdk_config["callback_url"], json_decode($content,true));
                break;
            //redis方式
            case 2:
                $redis->set("sdk_callback",json_encode($content));
                break;
            //mysql方式
            case 3:
                try {
                    $db = new \Workerman\MySQL\Connection(DB_HOST,DB_PORT,DB_USER,DB_PASS,DB_NAME);
                    $content = json_decode($content,true);
                    $insertData = [];
                    if(str_contains($topic, 'eewmsg')) {
                        $insertData = [
                            'unique' =>  $content[20],
                            'topic' =>  $topic,
                            'msg' =>  json_encode($content,JSON_UNESCAPED_UNICODE),
                            'clientid' =>  $account_info["clientId"],
                            'origin_event_main' =>  $content[1],
                            'event_main' =>  $content[7],
                            'event_num' =>  $content[8],
                            'locName'=>$content[13],
                            'lng'=>$content[14],
                            'lat'=>$content[15],
                            'magnitude'=>$content[17],
                            'intensity'=>$content[18],
                            'depth'=>$content[16],
                            'ack_status' =>  $sdk_config["is_source_feedback"],
                            'ack_msg' => empty($respon) ? "" : json_encode($respon),
                            'createtime' =>  time(),
                        ];
                        $db->insert('fa_mqtt_msg')->cols($insertData)->query();
                    }elseif (str_contains($topic, 'eqrmsg')){
                        $insertData = [
                            'unique' =>  $content["19"],
                            'topic' =>  $topic,
                            'msg' =>  json_encode($content,JSON_UNESCAPED_UNICODE),
                            'clientid' =>  $account_info["clientId"],
                            'origin_event_main' =>  $content[1],
                            'event_main' =>  $content[7],
                            'event_num' =>  $content[8],
                            'locName'=>$content[13],
                            'lng'=>$content[14],
                            'lat'=>$content[15],
                            'magnitude'=>$content[17],
                            'depth'=>$content[16],
                            'ack_status' =>  $sdk_config["is_source_feedback"],
                            'ack_msg' => empty($respon) ? "" : json_encode($respon),
                            'createtime' =>  time(),
                        ];
                        $db->insert('fa_mqtt_msg')->cols($insertData)->query();
                    }
                    error_log(date("Y-m-d H:i:s",time()).PHP_EOL."存入数据库成功".PHP_EOL.var_export($insertData,true). PHP_EOL, 3, $sdk_config["log_url"]);
                } catch(PDOException $e) {
                    echo "Error: " . $e->getMessage();
                }
                break;
                //文件存储方式
            case 4:
                $dir_path = __DIR__."/sdk_callback/";
                if (!is_dir($dir_path)) {
                    mkdir($dir_path, 0777, true);
                }
                $file_path = __DIR__."/sdk_callback/".date("YmdHis")."_".$account_info["clientId"].".json";
                file_put_contents($file_path,date("Y-m-d H:i:s",time()).PHP_EOL."存入数据库成功".PHP_EOL.var_export(json_decode($content,true),true). PHP_EOL);
                break;
        }
    };
    $mqtt->connect();
};
/**
 * 通过API请求获取PEM格式的证书
 *
 * 本函数通过发送POST请求到指定URL，来获取用于签名的PEM格式证书。
 * 请求中包含必要的访问配置ID和客户端ID，以识别并获取正确的证书。
 *
 * @param string $url API的URL，用于获取签名证书。
 * @param array $data
 * @return array|null 返回从API获取的PEM格式证书的JSON响应。
 */
function api_post(string $url, array $data): ?array {
    // 数据验证逻辑（示例，根据实际情况调整）
    if (empty($data)) {
        throw new InvalidArgumentException("Data must be a non-empty array.");
    }
    try {
        $ch = curl_init($url);
        $jsonData = json_encode($data);
        if (!$jsonData) {
            throw new Exception("Failed to encode data into JSON.");
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 启用SSL证书验证
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ));
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            // 使用日志记录错误而不是直接输出
            error_log("cURL Error: $error");
            throw new Exception("cURL Request Failed: " . $error);
        }
        curl_close($ch);
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // 使用日志记录JSON解码错误
            error_log("JSON Decode Error: " . json_last_error_msg());
            throw new Exception("JSON Decode Failed.");
        }
        return $responseData;
    } catch (Exception $e) {
        // 可以进一步处理异常，例如记录日志、返回错误信息等
        error_log("Exception caught in api_post: " . $e->getMessage());
        return null;
    }
}
Worker::runAll();