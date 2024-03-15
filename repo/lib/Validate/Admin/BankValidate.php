<?php
/**
 * author      : ben <956841620@qq.com>
 * createTime  : 2018/4/09 23:25
 * description :
 */

namespace Lib\Validate\Admin;

use Lib\Validate\BaseValidate;
class BankValidate extends BaseValidate
{
    // 验证规则
    protected $rule = [
        "id"=>"require|integer",
        "name"=>"require",
        "h5_logo"=>"require",
        'shortname'=>'require',
        'code'=>"require",
        "status"=>"in:enabled,disabled"
    ];
    protected $field = [
        "id"=>"数据id",
        "name"=>"银行卡名称",
        "code"=>"银行卡代号",
        "shortname"=>"银行卡简称",
        "h5_logo"=>"LOGO",
        "status"=>"状态值只能为：enabled或者disabled"
    ];

    protected $message = [

    ];

    protected $scene = [

        'post' => [
             'name', 'h5_logo','code','status'
        ],
        'put' => [
             'name','h5_logo','code','status'
        ],
        'delete' => [
            'id'
        ],
        'patch'=>[
            'status'
        ]
    ];


}