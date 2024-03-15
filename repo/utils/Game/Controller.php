<?php

namespace Utils\Game;

use Slim\Http\Response;
use Logic\Define\ErrMsg;

Class Controller
{

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

    public function __construct($path, $ci)
    {
        $this->path = $path;
        $this->ci = $ci;
        $this->ci->db->getConnection('default');
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
                     'data' => $data,
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
                'data' => $data,
                'attributes' => $attributes,
                'state' => $state,
                'message' => $message,
                // 'ts' => time(),
            ]);
        return $response;
    }

    /**
     * 解析url
     * @return [type] [description]
     */
    protected function parseUri()
    {
        $uri = $this->request->getUri()->getPath();
        $uris = explode('/', trim($uri, '/'));
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
            //默认版本
            if (!in_array($uris2[0], $this->supportVersions)) {
                $uris2 = array_merge([$this->defaultVersion], $uris2);
            }
            //游戏回调
            if (isset($uris2[1]) && $uris2[1] == 'callback') {
                $data = $this->request->getQueryParams();
                $data['game'] = $uris2[2] ?? '';
                $data['action'] = $uris2[3] ?? '';
                $this->ci->request = $this->request->withQueryParams($data);
                $uris2 = [
                    $uris2[0],
                    $uris2[1],
                ];
            }
        }

        $dir = array_merge([$this->path, $this->dir], $uris2);
        $dir = join(DIRECTORY_SEPARATOR, $dir);
        if (in_array('callback', $uris2)) {
            $file = $dir . DIRECTORY_SEPARATOR . 'post.php';
        } else {
            $file = $dir . DIRECTORY_SEPARATOR . strtolower($this->request->getMethod()) . '.php';
        }
        $succ = is_file($file);
        return [str_replace('//', '/', $dir), str_replace('//', '/', $file), $succ, $args];
    }

    public function run()
    {
        $website = $this->ci->get('settings')['website'];
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
                    foreach ($this->db->getConnection()->getQueryLog() ?? [] as $val) {
                        $this->logger->info('DBLog', $val);
                    }
                }

            } catch (Exception $e) {
                return $this->withRes(500, -1, 'action not found error!' . $e->getMessage());
            }

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
            $this->logger->error('action not found error!', [$dir, $file, $succ, $args, $this->request->getUri()->getPath()]);
            return $this->withRes(404, -4, 'action not found error!');
        }
    }

    public function __get($field)
    {
        if (!isset($this->$field)) {
            return $this->ci->$field;
        } else {
            return $this->$field;
        }
    }
}