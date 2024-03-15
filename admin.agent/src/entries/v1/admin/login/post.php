<?php
/**
 * vegas2.0
 *
 * @auth *^-^*<dm>
 * @copyright XLZ CO.
 * @package
 * @date 2017/4/7 15:04
 */

use Utils\Admin\Action;
use Logic\Admin\AdminToken;
use Lib\Validate\Admin\LoginValidate;

return new class extends Action {
    const TITLE = "后台登录";
    const HINT = "开发的技术提示信息";
    const DESCRIPTION = "后台登录";
    const QUERY = [];
    const TYPE = 'application/json';
    const PARAMs = [
        'token'    => 'string(required) #验证码token',
        'code'     => 'string(required) #验证码',
        'name'     => 'string(required) #用户名',
        'password' => 'string(required) #密码',
    ];
    const SCHEMAs = [
        'data' => [
            'token' => 'token',
        ],
    ];

    public function run() {
        (new LoginValidate())->paramsCheck('post', $this->request, $this->response);

        $params = $this->request->getParams();

        $jwtconfig = $this->ci->get('settings')['jsonwebtoken'];

        $digital = intval($jwtconfig['uid_digital']);

        $jwt = new AdminToken($this->ci);
        $token = $jwt->createToken($params, $jwtconfig['public_key'], $jwtconfig['expire'], $digital);

        return $token;
    }
};
