<?php
class Register
{
    private $license;
    private $owner;
    private $type;
    private $scope;
    private $log_url;
    private $error;
    private $redis;

    public function __construct($license = null,$owner = null,$type = null,$scope = null,$callback_type = 4,$callback_url = null,$is_source_feedback = 1,$log_url = null)
    {
        $this->license = $license;
        $this->owner = $owner;
        $this->type = $type;
        $this->scope = $scope;
        $this->log_url = $log_url;
        $this->redis = new \Redis();
        try {
            $this->redis->connect(REDIS_HOST, REDIS_PORT);
            $this->redis->select(14);
            $sdk_config = [
                "callback_type"=>$callback_type,
                "callback_url"=>$callback_url,
                "is_source_feedback"=>$is_source_feedback,
                "log_url"=>$log_url,
            ];
            $this->redis->set("sdk_config",json_encode($sdk_config));
        } catch (RedisException $e) {
            return 'Redis连接失败';
        }
    }

    /**
     * 注册服务函数
     * 该函数负责与外部服务通信，以注册服务并获取必要的账户信息和授权范围
     */
    public function registerService()
    {

        try {
            // 检查必需的参数是否完整
            if (empty($this->license)) {
                return "缺少授权码参数";
            }
            if (empty($this->owner)) {
                return "缺少所属机构";
            }
            if (empty($this->type)) {
                return "缺少终端类型";
            }
            // 向指定URL发送POST请求以获取账户信息
            $response = $this->byHttpRequest(GET_ACCOUNT_URL, [
                'license' => $this->license,
                'owner' => $this->owner,
                'type' => $this->type
            ]);
            // 解析获取的账户信息
            $account_info = json_decode($response, true);
            error_log(date("Y-m-d H:i:s",time()).PHP_EOL."获取账号调用接口".GET_ACCOUNT_URL.PHP_EOL."接收到的信息:".var_export($account_info,true). PHP_EOL, 3, $this->log_url);
            if (!empty($account_info["Result"])) {
                $account_details = $account_info['Result'];
            } else {
                if (!empty($account_info['error'])) {
                    return $account_info['error'];
                }
            }
            // 如果账户信息获取失败，则输出错误信息并返回
            if (empty($account_details)) {
                return "获取账号信息失败";
            }
            $this->redis->set("account_info",json_encode($account_details));
            error_log(date("Y-m-d H:i:s",time()).PHP_EOL."将结果存入account_info".PHP_EOL.var_export($account_details,true). PHP_EOL, 3,  $this->log_url);
            // 将权限范围字符串分割成数组
            $scopes = explode(',', $this->scope);
            // 如果权限范围为空，则输出错误信息并返回
            if (empty($scopes)) {
                return "缺少权限范围";
            }
            // 用于存储所有权限范围的结果
            $scopes_results = [];
            // 遍历每个权限范围并请求授权
            foreach ($scopes as $scope) {
                // 跳过空权限
                if (empty($scope)) {
                    continue;
                }
                // 向指定URL发送POST请求以获取授权信息
                $authorize_response = $this->byHttpRequest(GET_AUTHORIZE_URL, [
                    'clientId' => $account_details["clientId"],
                    'uname' => $account_details["uname"],
                    'pwd' => $account_details["pwd"],
                    'scope' => $scope,
                ]);
                // 解析授权信息
                $authorize_info = json_decode($authorize_response, true);
                // 如果授权信息有效，则添加到结果数组中
                if (isset($authorize_info['Result']) && is_array($authorize_info['Result'])) {
                    $scopeList = array_column($authorize_info['Result'], 'scope');
                    $ack = array_search('ACK', $scopeList);
                    foreach ($authorize_info['Result'] as $value) {
                        if ($ack !== false && $value['scope'] !== 'ACK') {
                            $scopes_results[] = [
                                'scope' => $value['scope'],
                                'host' => $value['mqtthost'],
                                'port' => $value['port'],
                                'topic' => $value['topic'],
                                'bind' => $authorize_info['Result'][$ack]['topic'],
                            ];
                        } else {
                            $scopes_results[] = [
                                'scope' => $value['scope'],
                                'host' => $value['mqtthost'],
                                'port' => $value['port'],
                                'topic' => $value['topic'],
                            ];
                        }
                    }
                } else {
                    if (!empty($authorize_info['error'])) {
                        return $authorize_info['error'];
                    }
                }
            }
            $this->redis->set("scopes_results",json_encode($scopes_results));
            error_log(date("Y-m-d H:i:s",time()).PHP_EOL."scopes_results".PHP_EOL.var_export($scopes_results,true). PHP_EOL, 3, $this->log_url);
            return $this->startService();
        }catch (Exception $e) {
            return  $e->getMessage();
        }
    }

    /**
     * @return
     * 执行脚本
     */
    public function startService()
    {
        $result = $this->runCommandInDirectory(__DIR__, "php script.php start -d");
        if (strpos($result, 'success') === false && strpos($result, 'success') === false) {
            return "脚本启动失败: " . $result;
        }
        var_dump($result);
        return "启动成功";
    }


    /**
     * @return
     * 执行脚本
     */
    public function stopService()
    {
        $result = $this->runCommandInDirectory(__DIR__, "php script.php stop");
        if (strpos($result, 'success') === false && strpos($result, 'success') === false) {
            return "脚本暂停失败: " . $result;
        }
        return "暂停成功";
    }

    function runCommandInDirectory($directory, $command) {
        if (!chdir($directory)) {
            return false;
        }
        return shell_exec($command);
    }

    /**
     * HTTP请求
     * @param string $url 请求的URL
     * @param bool $params 请求的参数内容
     * @param int $ispost 是否POST请求
     * @return bool|string 返回内容
     */
    protected function byHttpRequest(string $url,array $params, $method = "POST" , $header = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 禁用SSL证书验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 不验证证书主机名
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if($header){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }else{
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
        $response = curl_exec($ch);
        if ($response === FALSE) {
            echo "cURL Error: " . curl_error($ch);
            return false;
        }
        curl_close($ch);
        return $response;
    }

    public function error()
    {
        return $this->error;
    }

}