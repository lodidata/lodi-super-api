<?php
/**
 * author      : ben <956841620@qq.com>
 * createTime  : 2018/4/09 23:25
 * description :
 */

namespace Lib\Validate\Admin;

use Lib\Validate\BaseValidate;

class LoginValidate extends BaseValidate {

    // 验证规则
    protected $rule = [
        "token"    => "require|length:32",
        "code"     => "require|length:4",
        "name"     => "require|length:5,15",
        "password" => "require|length:6,32",
    ];

    protected $field = [
        "token"    => "验证码",
        "code"     => "验证码",
        "name"     => "用户名",
        "password" => "密码",
    ];

    protected $message = [

    ];

    protected $scene = [
        'post' => [
            'token', 'code', 'name', 'password',
        ],
    ];
}