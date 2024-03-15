<?php

namespace Utils\Work;

use Slim\Http\Response;
use Logic\Define\Lang;
use Utils\Client;
use Slim\Http\Request;
// use Awurth\SlimValidation\Validator;
use Logic\Define\ErrMsg;
use Slim\Exception\SlimException;

Class Controller {
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
        $this->ci->db->getConnection('default');//设置默认数据库连接
    }

    public function withRes($status = 200, $state = 0, $message = '操作成功', $data = null, $attributes = null) {
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
                    // 'data' => $data,
                    'attributes' => $attributes,
                    'state' => $state,
                    'message' => $message,
                    'headers' => [
                        'pl' => isset($headers['pl']) ?? '',
                        'mm' => isset($headers['mm']) ?? '',
                        'av' => isset($headers['av']) ?? '',
                        'sv' => isset($headers['sv']) ?? '',
                        'uuid' => isset($headers['uuid']) ?? '',
                        'token' => isset($headers['HTTP_AUTHORIZATION']) ?? '',
                    ],
                    'cost' => round(microtime(true) - COSTSTART, 4)
                ]);
            }
        }
        if(is_array($attributes)) {
            isset($attributes['number']) && $attributes['number'] = (int)$attributes['number'];
            isset($attributes['size']) && $attributes['size'] = (int)$attributes['size'];
            isset($attributes['total']) && $attributes['total'] = (int)$attributes['total'];
        }else{
            isset($attributes->number) && $attributes->number = (int)$attributes->number;
            isset($attributes->size) && $attributes->size = (int)$attributes->size;
            isset($attributes->total) && $attributes->total = (int)$attributes->total;
        }
        $this->newResponse = $response = $this->response
            // ->withHeader('Access-Control-Allow-Origin', '*')
            // ->withHeader("Content-Type", 'charset=utf-8')
            // ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, pl, mm, av, sv, uuid')
            // ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withStatus($status)
            ->withJson([
                'data' => $data ? $data : null,
                'attributes' => $attributes ? $attributes : null,
                'status' => $state == 0 ? 200 : $state,
                'msg' => $message,
                // 'ts' => time(),
            ]);
        return $response;
    }

    /**
     * 解析url
     * @return [type] [description]
     */
    protected function parseUri() {
        $uri = $this->request->getUri()->getPath();
        $uris = explode('/', $uri);
        $uris2 = $uris;

        $args = [];
        $uris2 = [];
        foreach ($uris as $v) {
            if (is_numeric($v)) {
                $args[] = $v;
            } else {
                $uris2[] = $v;
            }
        }

        $dir = array_merge([$this->path, $this->dir], $uris2);
        $dir = join(DIRECTORY_SEPARATOR, $dir);
        $file = $dir.DIRECTORY_SEPARATOR.strtolower($this->request->getMethod()).'.php';
        $succ = is_file($file);
        return [str_replace('//', '/', $dir), str_replace('//', '/', $file), $succ, $args];
    }

    public function run() {
        $website = $this->ci->get('settings')['website'];
        \DB::enableQueryLog();

        // 打印sql
        if (isset($website['DBLog']) && $website['DBLog']) {
            $this->db->getConnection()->enableQueryLog();
        }

        list($dir, $file, $succ, $args) = $this->parseUri();

        // 增加网页options请求
        if (strtolower($this->request->getMethod()) == 'options' && is_dir($dir)) {
            return $this->response
                // ->withHeader('Access-Control-Allow-Origin', '*')
                // ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                // ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->withStatus(200)->write('Allow Method GET, POST, PUT, PATCH, DELETE');
        }

        if ($succ) {
            $this->obj = $obj = require $file;
            try {
                $obj->init($this->ci);
                $this->initObj = $obj;
                if (empty($args)) {
                    $res = $obj->run();
                } else {
                    $res = call_user_func_array([$obj, 'run'], $args);
                }
                // 写入sql
                if (isset($website['DBLog']) && $website['DBLog']) {
                    foreach ($this->db->getConnection()->getQueryLog() ?? [] as $val) {
                        $this->logger->info('DBLog', $val);
                    }
                }
            } catch (\Exception $e) {
                if ($e instanceof SlimException) {
                    // This is a Stop exception and contains the response
                    return $this->newResponse = $e->getResponse();
                }
                return $this->withRes(500, -1, 'action not found error!'.$e->getMessage());
            }
            $this->newResponse = $this->response;
            if ($res instanceof \Awurth\SlimValidation\Validator || $res instanceof \Respect\Validation\Validator) {
                $errors = $res->getErrors();
                return $this->withRes(400, -4, current(current($errors)), null);
            } else if ($res instanceof ErrMsg) {
                list($status, $state, $msg, $data, $attributes) = $res->get();
                return $this->withRes($status, $state, $msg, $data, $attributes);
            } else if (is_array($res) || is_string($res) || empty($res)) {
                return $this->withRes(200, 0, '操作成功', $res);
            } else if ($res instanceof Response) {
                return $res;
            } else {
                return $this->withRes(500, -2, 'action not found error!');
            }
        } else {
            return $this->withRes(404, -3, 'action not found error!'.print_r([$dir, $file, $succ, $args, $this->request->getUri()->getPath()], true));
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