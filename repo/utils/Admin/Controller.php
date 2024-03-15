<?php
namespace Utils\Admin;
use Slim\Http\Request;
use Slim\Http\Response;
// use Awurth\SlimValidation\Validator;
use Logic\Define\ErrMsg;
use Slim\Exception\SlimException;
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

    public $obj;

    public $initObj;

    protected $newResponse;

    public function __construct($path, $ci) {
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

        // $uris2 = array_filter($uris2);
        if (empty($uris2)) {
            $uris2[] = $this->defaultVersion;
        } else {
            if (!in_array($uris2[1], $this->supportVersions)) {
                $uris2 = array_merge([$this->defaultVersion], $uris2);
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

    public function __destruct()
    {
//        return true;
        //操作日志
        $uri = $this->request->getUri()->getPath();
//        if($uri !== '/message/num'){
//            try {
//                if ($this->obj) {
//
//                    $addData = [];
//                    $obj = new \ReflectionClass($this->obj);
//
//                    $addData['api_name'] = $obj->getconstant("TITLE");
//                    $addData['ip'] = \Utils\Client::getIp();
//                    $addData['uri'] = $this->request->getUri()->getPath();
//                    $params = $this->request->getParams();
//                    $addData['params'] = $params ? json_encode($params) : '';
//                    $method = strtolower($this->request->getMethod());
//                    $addData['method'] = $method;
//                    $addData['created'] = date('Y-m-d H:i:s');
//                    $addData['result'] = 'success';
//                    $uris = explode('/', $uri);
//                    if(in_array('user',$uris) && $method !== 'get'){
//                        $addData['user_id'] = is_numeric(end($uris)) ? end($uris) : $user_id = isset($params['id']) ? $params['id'] : '';
//                    }
//                    $status = 200;
//                    if ($this->newResponse) {
//
//                        $status = $this->newResponse->getStatusCode();
//
//                        if ($status >= 400) {
//
//                            $addData['result'] = 'fail';
//                            $body = $this->newResponse->getBody();
//                            if ($body->isSeekable()) {
//                                $body->rewind();
//                            }
//                            $settings = $this->ci->get('settings');
//                            $chunkSize = $settings['responseChunkSize'];
//
//                            $data = $body->read($chunkSize);
//                            $addData['error_message'] = $data;
//                            if (is_json($data)) {
//                                $arrData = json_decode($data, true);
//                                $addData['error_message'] = isset($arrData['message']) ? $arrData['message'] : $data;
//                            }
//
//                        }
//
//                    }
//
//                    if ($status !== 401) {
//                        $admin = \DB::table('admin_user')->where('id', $this->initObj->playLoad['uid'])->value('username');
//                        $addData['created_uid'] = $this->initObj->playLoad['uid'];
//                        $addData['created_uname'] = $admin;
//
//                    }
//
////                    print_r($addData);exit;
//
//                    \DB::table('logs')->insert($addData);
//                }
//            }catch (\Exception $e){
//                //TODO:
////                throw $e;
//            }
//        }


    }

}