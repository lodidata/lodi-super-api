<?php
/**
 * author      : ben <956841620@qq.com>
 * createTime  : 2018/4/09 23:25
 * description :
 */

namespace Lib\Validate\Admin;

use Lib\Validate\BaseValidate;
class CustomerValidate extends BaseValidate
{
    // 验证规则
    protected $rule = [
        "name"=>"require",
        "customer"=>"require",
        "type"=>"in:lottery,game"
    ];
    protected $field = [
        "name"=>"客户名称",
        "customer"=>"客户代码",
        "type"=>"type值只能为：lottery或者game"
    ];

    protected $message = [

    ];

    protected $scene = [

        'update' => [
            'customer', 'name','type'
        ],
        'post' => [
             'customer', 'name'
        ],
        'put' => [
            'customer_id', 'admin_notify', 'www_notify',
        ],
        'putsta' => [
            'status','notify_id'
        ]
    ];


}