<?php
namespace Utils\Game;

use Lib\Exception\BaseException;

class Action {

    protected $ci;

    /**
     * 前置操作方法列表
     * @var array $beforeActionList
     * @access protected
     */
    protected $beforeActionList = [];

    protected $ipProtect = true;

    protected $maintaining = false;

    public function init($ci) {

        $this->ci = $ci;

        // 系统维护性开关in_array($this->request->getMethod(), ['PUT', 'PATCH', "DELETE"]) &&
        if ($this->maintaining) {
            return false;
        }

        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method) {
                call_user_func([$this, $method]);
            }
        }
        return true;
    }

    public function __get($field) {
        if (!isset($this->$field)) {
            return $this->ci->$field;
        } else {
            return $this->$field;
        }
    }

    /**
     * 校验请求的TOKEN
     */
    public function verifyApiToken()
    {
        $api_verify_token = $this->ci->get('settings')['app']['api_verify_token'];
        $token = $this->request->getHeaderLine('token');
        $verifyApiToken = $api_verify_token.date("Ymd");
        if ( ($token != $verifyApiToken) || empty($token) || empty($api_verify_token)) {
            $newResponse = $this->response->withStatus(400);
            $newResponse = $newResponse->withJson([
                'state' => -1,
                'message' => '参数不对',
                'ts' => time(),
            ]);
            throw new BaseException($this->request, $newResponse);
        }
    }
}
