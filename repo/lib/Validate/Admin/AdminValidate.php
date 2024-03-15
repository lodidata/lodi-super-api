<?php
/**
 * author      : ben <956841620@qq.com>
 * createTime  : 2018/4/09 23:25
 * description :
 */

namespace Lib\Validate\Admin;

use Lib\Validate\BaseValidate;
class AdminValidate extends BaseValidate
{
    // 验证规则
    protected $rule = [
        "status"   => "require|in:0,1",
        "name"     =>"require|length:5,15|unique:admin,name,,,Admin",
        "password"=>"require|length:6,32",
        "password_confirm"=>"require|length:6,32|confirm:password",
    ];
    protected $field = [
        "status"=>"状态",
        "name"=>"用户名",
        "password"=>"密码",
        "password_confirm"=>"二次密码",
    ];

    protected $message = [

    ];

    protected $scene = [

        'create' => [
            'name', 'password','password_confirm'
        ],
        'update' => [
            'name'=>'require|length:5,15|unique:admin,name^id,,,Admin', 'password'=>'length:6,32'
        ],
        'get' => [
            'name'=>'length:5,15', 'status'=>'in:0,1'
        ],
        'status' => [
            'status'
        ],
    ];


}