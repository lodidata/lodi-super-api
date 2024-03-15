<?php

namespace Utils\Www;

use Slim\Http\Response;
use Logic\Define\Lang;
use Utils\Client;

Class Controller {

    /**
     * 支持版本
     * @var [type]
     */
    public $supportVersions = ['v1', 'v2', 'v3'];

    /**
     * 默认版本号
     * @var string
     */
    public $defaultVersion = 'v1';

    /**
     * 目录
     * @var string
     */
    public $dir = 'entries';

    public $path;

    public $ci;

    public function __construct($path, $ci) {
        $this->path = $path;
        $this->ci = $ci;
        $this->ci->db->getConnection('default');
    }

    public function withRes($status = 200, $data = null, $json = false) {
        $website = $this->ci->get('settings')['website'];
        // 写入访问日志
        if (in_array($this->request->getMethod(), ['GET', 'POST', 'PUT', 'PATCH', "DELETE"])) {
            // 头 pl平台(pc,h5,ios,android) mm 手机型号 av app版本 sv 系统版本  uuid 唯一标识 
            $headers = $this->request->getHeaders();
            if (isset($website['ALog']) && $website['ALog']) {
                $this->logger->info("ALog", [
                    'ip' => \Utils\Client::getIp(),
                    'method' => $this->request->getMethod(),
                    'params' => $this->request->getParams(),
                    'httpCode' => $status,
                    'data' => $data,
                    'headers' => [
                        'pl' => isset($headers['HTTP_PL']) ?? '',
                        'mm' => isset($headers['HTTP_MM']) ?? '',
                        'av' => isset($headers['HTTP_AV']) ?? '',
                        'sv' => isset($headers['HTTP_SV']) ?? '',
                        'uuid' => isset($headers['HTTP_UUID']) ?? '',
                        'token' => isset($headers['HTTP_AUTHORIZATION']) ?? '',
                    ],
                    'cost' => round(microtime(true) - COSTSTART, 4),
                ]);
            }
        }
        return $this->response
            ->withStatus($status)
            ->withJson($data,null,JSON_UNESCAPED_SLASHES);
    }

    /**
     * 解析url
     * @return [type] [description]
     */
    protected function parseUri() {
        $uri = $this->request->getUri()
                             ->getPath();
        $uris = explode('/', $uri);
        $args = [];
        $uris2 = [];
        foreach ($uris as $v) {
            if (is_numeric($v)) {
                $args[] = $v;
            } else {
                $uris2[] = $v;
            }
        }

        $uris2 = array_filter($uris2);
        if ($uris2) {
            if($uris2[2] == 'callback'){
                define('CHECKCODE', $uris2[3]);
                unset($uris2[3]);
            }
            if (!in_array($uris2[1], $this->supportVersions)) {
                define('CUSTOMER', $uris2[1]);   //若请求支付则是客户
                define('CALLBACK', strtoupper($uris2[1]));   //若回调则表示是第三方CODE
                unset($uris2[1]);
                $uris2 = array_merge([$this->defaultVersion], $uris2);
            } else {
                define('CUSTOMER', $uris2[2]);   //若请求支付则是客户
                define('CALLBACK', strtoupper($uris2[2]));   //若回调则表示是第三方CODE
                unset($uris2[2]);
            }
        }
        $dir = array_merge([$this->path, $this->dir], $uris2);
        $dir = join(DIRECTORY_SEPARATOR, $dir);
        if (in_array('callback', $uris2) || in_array('callbacks', $uris2))
            $file = $dir . DIRECTORY_SEPARATOR . 'post.php';
        else
            $file = $dir . DIRECTORY_SEPARATOR . strtolower($this->request->getMethod()) . '.php';
        $succ = is_file($file);
        return [str_replace('//', '/', $dir), str_replace('//', '/', $file), $succ, $args, $uris2];
    }

    public function run() {
        $website = $this->ci->get('settings')['website'];
        // 打印sql
        if (isset($website['DBLog']) && $website['DBLog']) {
            $this->db->getConnection()
                     ->enableQueryLog();
        }

        list($dir, $file, $succ, $args, $uris2) = $this->parseUri();
        if (empty($uris2)) {
            return $this->withRes(405, 'Not on the list of customer lists!');
        }

        //IP黑名单 回调数据除外| pay公共跳转下单
        if (!in_array('callback', $uris2) && !in_array('callbacks', $uris2) && !in_array('pay', $uris2)) {
            $ip = Client::getIp();
            $t = explode('.', $ip);
            array_pop($t);
            $ip_t = implode('.', $t) . '.*';
            $allow_ips = $website['ip'];
            $ips = \DB::table('customer')
                      ->where('customer', CUSTOMER)
                      ->first(['ip', 'id']);
            if ($ips) {
                define('CUSTOMERID', $ips->id);
                $allow_ips = array_merge(explode(',', $ips->ip), $allow_ips);
            } else {
                return $this->withRes(405, 'Not on the list of IP white lists!');
            }
            $website['safety'] = 'md5';
            switch ($website['safety']) {
                case 'ip':
                    if (!in_array($ip, $allow_ips) && !in_array($ip_t, $allow_ips)) {
                        return $this->withRes(405, 'Not on the list of IP white lists!');
                    }
                    break;
                case 'md5':
                    $param = $this->request->getParams();
                    if (isset($param['tc']) && $param['tc'] == 'sayahao') {
                        break;
                    }

                    if (!isset($param['sign'])) {
                        return $this->withRes(405, "You don't have sufficient rights.");
                    }

                    $sign = $param['sign'];
                    unset($param['sign']);
                    if ($sign != md5(http_build_query($param) . $website['app']['app_secret'])) {
                        return $this->withRes(405, "You don't have sufficient rights.");
                    }

                    break;
                default :
                    return $this->withRes(405, "You don't have sufficient rights.");
            }
        }
        // 增加网页options请求
        if (strtolower($this->request->getMethod()) == 'options' && is_dir($dir)) {
            return $this->response
                ->withStatus(200)
                ->write('Allow Method GET, POST, PUT, PATCH, DELETE');
        }
        if ($succ) {
            $obj = require $file;
            try {
                $res = $obj->init($this->ci);
                if (!$res) {
                    // no do something
                } else if (empty($args)) {
                    $res = $obj->run();
                } else {
                    $res = call_user_func_array([$obj, 'run'], $args);
                }
                // 写入sql
                if (isset($website['DBLog']) && $website['DBLog']) {
                    foreach ($this->db->getConnection()
                                      ->getQueryLog() ?? [] as $val) {
                        $this->logger->info('DBLog', $val);
                    }
                }
            } catch (Exception $e) {
                return $this->withRes(500, 'action not found error!' . $e->getMessage());
            }
            if ($res instanceof \Awurth\SlimValidation\Validator || $res instanceof \Respect\Validation\Validator) {
                $errors = $res->getErrors();
                return $this->withRes(400, current(current($errors)), null);
            } else if (is_array($res)) {
                return $this->withRes(200, $res);
            } else if (is_string($res) || empty($res)) {
                return $this->withRes(200, $res);
            } else if ($res instanceof Response) {
                return $res;
            } else {
                return $this->withRes(500, 'action not found error!');
            }
        } else {
            return $this->withRes(404, 'action not found error!' . print_r([
                    $dir, $file, $succ, $args, $this->request->getUri()
                                                             ->getPath(),
                ], true));
        }
    }

    public function __get($field) {
        if (!isset($this->$field)) {
            return $this->ci->$field;
        } else {
            return $this->$field;
        }
    }
}