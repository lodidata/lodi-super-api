<?php
namespace Utils\Work;

class Action {

    protected $ci;

    /**
     * 前置操作方法列表
     * @var array $beforeActionList
     * @access protected
     */
    protected $beforeActionList = [];

    public function init($ci) {
        $this->ci = $ci;
        if (strtolower($this->request->getMethod()) == 'get') {
            $data = $this->request->getQueryParams();
            $data['page'] = isset($data['page']) ? $data['page'] : 1;
            $data['page_size'] = isset($data['page_size']) ? $data['page_size'] : 10;
            $this->ci->request = $this->ci->request->withQueryParams($data);
        }
    }

    public function before(){
        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method) {
                call_user_func([$this, $method]);
            }
        }
    }

    public function __get($field) {
        if (!isset($this->$field)) {
            return $this->ci->$field;
        } else {
            return $this->$field;
        }
    }

    function isSignLogin(){
        if(!$_REQUEST){
            $_REQUEST = json_decode(file_get_contents('php://input'),true);
        }
        if(!isset($_REQUEST['token'])) {
            $newResponse = $this->response->withStatus(401);
            $newResponse = $newResponse->withJson([
                'status' => 401,
                'message' => '请重新登陆',
                'ts' => time(),
            ]);
            throw new \Lib\Exception\BaseException($this->request, $newResponse);
        };
        $name = $this->redis->get('SignUserLoginToken:'.$_REQUEST['token']);
        $token = $this->redis->get('SignUserLogin:'.$name);
        if($_REQUEST['token'] == $token){
            return true;
        }
        $newResponse = $this->response->withStatus(401);
        $newResponse = $newResponse->withJson([
            'status' => 401,
            'message' => '请重新登陆',
            'ts' => time(),
        ]);
        throw new \Lib\Exception\BaseException($this->request, $newResponse);
    }


}
