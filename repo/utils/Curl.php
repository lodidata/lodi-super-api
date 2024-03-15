<?php

namespace Utils;

/**
 * vegas2.0
 *
 * @auth *^-^*<dm>
 * @copyright XLZ CO.
 * @package
 * @date 2017/4/7 16:06
 */
class Curl
{

    const TIME_OUT = 6;

    const DEFAULT_NUM = 30;

    const USER_AGENT = 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)';

    const GET = 'GET';

    const POST = 'POST';

    const PUT = 'PUT';

    const PATCH = 'PATCH';

    const DELETE = 'DELETE';

    /**
     * curl_multi_init
     *
     * @var resource $mc
     */
    private $mc;

    /**
     * 原始数据集
     *
     * @var array $source
     */
    private $source = [];

    /**
     * 任务池
     *
     * @var array $pool
     */
    private $pool = [];

    public $certificate;

    /**
     * get header ?
     *
     * @var bool $header
     */
    public $header;

    /**
     * closure
     *
     * @var callable $callback
     */
    public $callback;

    /**
     * 错误任务数
     *
     * @var int $errorNum
     */
    public $errorNum = 0;

    public $successNum = 0;

    public $runtime = 0;

    /**
     * 远程获取数据，POST模式
     * fixme: 这种方法只适应json头的post，普通的post请求使用commonPost()
     * 注意：
     * 1.使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"就行了
     * 2.文件夹中cacert.pem是SSL证书请保证其路径有效，目前默认路径是：getcwd().'\\cacert.pem'
     *
     * @param string $url 指定URL完整路径地址
     * @param string $cacert_url 指定当前工作目录绝对路径
     * @param array $para 请求的数据
     * @param string $method 自定义方法
     * @param bool $getHeader
     * @param array $header 头部信息
     * @param array $proxy 是否使用代理
     * @return string|array 远程输出的数据
     */
    public static function post(
        string $url,
        string $cacert_url = null,
        array $para = null,
        $method = null,
        $getHeader = false,
        $header = null,
        $proxy = null
    )
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $cacert_url ? true : false);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $cacert_url ? 2 : 0);//严格认证
        //证书地址
        if ($cacert_url && file_exists($cacert_url)) {
            curl_setopt($curl, CURLOPT_CAINFO, $cacert_url);
        }
        if (in_array($method, ['PUT', 'DELETE', 'PATCH'])) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        }
        $headers = [
            'Content-Type: application/json;charset=UTF-8',
        ];
        if ($header) {
            $headers = array_merge($headers, $header);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $para = json_encode($para, JSON_UNESCAPED_UNICODE);
        curl_setopt($curl, CURLOPT_HEADER, 0); // 过滤HTTP头
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 转向
        curl_setopt($curl, CURLOPT_POST, true); // post传输数据
        curl_setopt($curl, CURLOPT_POSTFIELDS, $para);// post传输数据
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        //香港代理
        if($proxy){
            curl_setopt($curl, CURLOPT_PROXY, $proxy['CURLOPT_PROXY']); //代理服务器地址
            curl_setopt($curl, CURLOPT_PROXYPORT, $proxy['CURLOPT_PROXYPORT']); //代理服务器端口
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy['CURLOPT_PROXYUSERPWD']); //http代理认证帐号，username:password的格式
            curl_setopt($curl, CURLOPT_PROXYTYPE, $proxy['CURLOPT_PROXYTYPE']); //使用http代理模式
        }
        $responseText = curl_exec($curl);
        if ($getHeader) {
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        }
        curl_close($curl);

        return !$getHeader ? $responseText : ['status' => $httpCode ?? 200, 'content' => $responseText];
    }

    /**
     * 常规post请求
     *
     * todo:多维的数组的post情况没有检查
     *
     * @param string $url 指定URL完整路径地址
     * @param string $cacert_url 指定当前工作目录绝对路径
     * @param array $para 请求的数据
     * @param array $headers 头部信息
     * @param bool $getHeader
     * @return mixed
     */
    public static function commonPost(string $url, string $cacert_url = null, $para = null, $headers = null, $getHeader = false)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $cacert_url ? true : false);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $cacert_url ? 2 : 0);//严格认证
        //证书地址
        if ($cacert_url && file_exists($cacert_url)) {
            curl_setopt($curl, CURLOPT_CAINFO, $cacert_url);
        }

        curl_setopt($curl, CURLOPT_HEADER, 0); // 过滤HTTP头
        if ($headers) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_POST, true); // post传输数据
        curl_setopt($curl, CURLOPT_POSTFIELDS, $para);// post传输数据
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        $responseText = curl_exec($curl);
        if ($getHeader) {
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        }
        curl_close($curl);

        return !$getHeader ? $responseText : ['status' => $httpCode ?? 200, 'content' => $responseText];
    }

    /**
     * 常规带header的post请求
     * @param string $url
     * @param string|null $cacert_url
     * @param array|null $para
     * @return mixed
     */
    public static function post_header(string $url, string $cacert_url = null, $para = null)
    {
        $data = json_decode(base64_decode($para), true);
        $is_json = 0;
        if (!empty($data['header'])) {
            foreach ($data['header'] as $key => $val) {
                if (stristr($val, 'json') !== false) {
                    $is_json = 1;
                }
                $header[] = "{$key}: {$val}";
            }
        }
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $cacert_url ? true : false);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $cacert_url ? 2 : 0);//严格认证
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 超时时间
        //证书地址
        if ($cacert_url && file_exists($cacert_url)) {
            curl_setopt($curl, CURLOPT_CAINFO, $cacert_url);
        }
        //'Accept-Encodinge' => 'gzip',
        if (isset($data['header']['Accept-Encodinge'])) {
            curl_setopt($curl, CURLOPT_ENCODING, $data['header']['Accept-Encodinge']);
        }
//        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: multipart/form-data"));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        curl_setopt($curl, CURLOPT_HEADER, 0); // 过滤HTTP头
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_POST, true); // post传输数据
        if ($is_json) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data['desParam']));// post json数据
        } else {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data['desParam']));// post 表单数据
        }
        $responseText = curl_exec($curl);

        curl_close($curl);

        return $responseText;
    }

    /**
     * 远程获取数据，GET模式
     * 注意：
     * 1.使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"就行了
     * 2.文件夹中cacert.pem是SSL证书请保证其路径有效，目前默认路径是：getcwd().'\\cacert.pem'
     *
     * @param string $url 指定URL完整路径地址
     * @param string $cacert_url 指定当前工作目录绝对路径
     * @param bool $getHeader
     * @param array $headers 头部信息
     * @param array $proxy 是否使用代理
     * @return mixed
     */
    public static function get(string $url, string $cacert_url = null, $getHeader = false, $headers = null, $proxy = null)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0); // 过滤HTTP头
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $cacert_url ? 1 : 0);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $cacert_url ? 2 : 0);//严格认证
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        //香港代理
        if($proxy){
            curl_setopt($curl, CURLOPT_PROXY, $proxy['CURLOPT_PROXY']); //代理服务器地址
            curl_setopt($curl, CURLOPT_PROXYPORT, $proxy['CURLOPT_PROXYPORT']); //代理服务器端口
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy['CURLOPT_PROXYUSERPWD']); //http代理认证帐号，username:password的格式
            curl_setopt($curl, CURLOPT_PROXYTYPE, $proxy['CURLOPT_PROXYTYPE']); //使用http代理模式
        }

        //证书地址
        if ($cacert_url && file_exists($cacert_url)) {
            curl_setopt($curl, CURLOPT_CAINFO, $cacert_url);
        }
        if ($headers) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        $responseText = curl_exec($curl);
        if ($getHeader) {
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        }
        //print_r( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
        curl_close($curl);

        return !$getHeader ? $responseText : ['status' => $httpCode ?? 200, 'content' => $responseText];
    }

    /**
     * 构建Url中参数列表
     *
     * @param string $url
     * @param array $data
     * @return
     */
    public static function bulidUrlDataStr(string $url, array $data)
    {
        $paramStr = '';
        foreach ($data as $key => $value) {
            $paramStr .= '&' . $key . '=' . $value;
        }

        return $url . substr_replace($paramStr, '?', 0, 1);
    }

    /**
     * 多任务curl
     *
     * @see https://www.crazydb.com/archive/php%E7%9A%84%E5%A4%9A%E7%BA%BF%E7%A8%8Bcurl_multi
     * @param callable $callback 处理日志的回调
     */
    public function multiCurl(callable $callback)
    {
        //创建多个curl语柄
        $this->mc = curl_multi_init();
        $this->callback = $callback;
    }

    /**
     * @param string $url 请求地址
     * @param array $para 请求参数，针对非GET|DELETE请求
     * @param string $method 请求方法
     * @param array $option 额外需要传递给日志函数的参数组(请求完毕原样返回给日志处理函数)
     */
    public function add(string $url, array $para, string $method, array $option = [])
    {
        $k = count($this->source);
        // fixme: 保持$url唯一，作为每个任务的标识
        $this->source[$url] = ['id' => $k, 'url' => $url, 'method' => $method, 'data' => $para, 'option' => $option];
        $this->pool[] = $url;
    }

    /**
     * 初始化方法
     *
     * 目前只处理：将指定数量任务放入任务池
     */
    private function init()
    {
        for ($i = 0; $i < self::DEFAULT_NUM; $i++) {
            $next = $this->next();
            if (!$next) {
                break;
            }
        }
    }

    protected function next()
    {
        $id = array_pop($this->pool);
        if (!$id) {
            return false;
        }
        $task = $this->source[$id];
        //初始化
        $conn = curl_init($task['url']);
        curl_setopt($conn, CURLOPT_TIMEOUT, self::TIME_OUT);
        curl_setopt($conn, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($conn, CURLOPT_MAXREDIRS, 1);//HTTp定向级别 ，7最高
        curl_setopt($conn, CURLOPT_HEADER, false);//这里不要header，加块效率
        curl_setopt($conn, CURLOPT_FOLLOWLOCATION, 1); // 302 redirect
        curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($conn, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($conn, CURLOPT_SSL_VERIFYHOST, $this->certificate ? 2 : 0);//严格认证
        curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, $this->certificate ? true : false);//SSL证书认证

        $header[] = 'X-Request-Origin: php/curl';

        //证书地址
        if ($this->certificate && file_exists($this->certificate)) {
            curl_setopt($conn, CURLOPT_CAINFO, $this->certificate);
        }
        if (in_array($task['method'], [self::PUT, self::DELETE, self::PATCH, self::DELETE])) {
            curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $task['method']);
        }

        if (in_array($task['method'], [self::GET, self::DELETE])) {
            curl_setopt($conn, CURLOPT_HTTPGET, true);
            curl_setopt($conn, CURLOPT_HTTPHEADER, $header);
        } else {
            $header[] = 'Content-Type: application/json';
            curl_setopt($conn, CURLOPT_HTTPHEADER, $header);
            if (is_array($task['data'])) {
                $task['data'] = json_encode($task['data'], JSON_UNESCAPED_UNICODE);
            }
        }
        //            else {
        //                //post
        //                $header[] = 'Content-Type: multipart/form-data';
        //                curl_setopt($conn, CURLOPT_HTTPHEADER, $header);
        //                $para = http_build_query($task['data']);
        //            }
        if (in_array($task['method'], [self::PUT, self::PATCH, self::POST])) {
            curl_setopt($conn, CURLOPT_POST, true);
            curl_setopt($conn, CURLOPT_POSTFIELDS, $task['data']);// post传输数据
        }

        curl_multi_add_handle($this->mc, $conn);

        return true;
    }

    public function run()
    {
        $total = count($this->source);
        $this->init();
        //execute the handles
        do {
            do {
                // 处理在栈中的每一个句柄
                curl_multi_exec($this->mc, $status);
                /**
                 * curl_multi_select 返回-1说明select执行失败，需要阻塞一段时间后再次执行；
                 * 0是没有任务活动链接，应该是底层请求处于阻塞状态，可能是正在解析域名或者timeout进行中，或者mc中所有任务执行完毕；
                 * 正整数表示有正常的活动链接，说明mc中还有未完成的任务。
                 */
                // 阻塞0.5秒，如果正常则跳出阻塞
                if (curl_multi_select($this->mc, 0.5) > 0) {
                    break;
                }
            } while ($status);

            //注意curl_multi_info_read需要多次调用。这个函数每次调用返回已经完成的任务信息，直至没有已完成的任务
            while ($info = curl_multi_info_read($this->mc)) {
                $this->successNum++;
                $ch = $info['handle'];
                $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $this->runtime += curl_getinfo($ch, CURLINFO_TOTAL_TIME);
                $source = $this->source[$url];
                $responseText = curl_multi_getcontent($ch);
                if ($this->header) {
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                }
                // string
                $error = curl_error($ch);
                if ($this->callback) {
                    $option = $source['option'];
                    unset($source['option'], $source['id']);
                    $option = array_merge($source, $option);
                    call_user_func($this->callback, $error, $httpCode ?? null, $responseText, $option);
                }
                curl_multi_remove_handle($this->mc, $ch);
                curl_close($ch);
                unset($ch);
                /**
                 * 前一任务处理完毕，再新建任务，这样使池中的任务数量保持一定
                 */
                if (count($this->pool)) {
                    $this->next();
                    // 手动执行，保证 $status 更新，感谢@rainyluo 反馈，update@2016-04-16。
                    curl_multi_exec($this->mc, $status);
                }
            }

        } while ($status);

        curl_multi_close($this->mc);
        $this->errorNum = $total - $this->successNum;
    }
    //
}
