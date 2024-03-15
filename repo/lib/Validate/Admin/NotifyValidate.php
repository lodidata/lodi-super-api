<?php
/**
 * author      : ben <956841620@qq.com>
 * createTime  : 2018/4/09 23:25
 * description :
 */

namespace Lib\Validate\Admin;

use Lib\Validate\BaseValidate;
class NotifyValidate extends BaseValidate
{
    // 验证规则
    protected $rule = [
        "notify_id"=>"require|integer",
        "customer_id"=>"require|integer",
        "admin_notify"=>"require|url|length:10,100",
        "www_notify"=>"require|url|length:10,100",
        "status"=>"in:enabled,disabled"
    ];
    protected $field = [
        "notify_id"=>"数据id",
        "customer_id"=>"客户id",
        "admin_notify"=>"admin回调地址",
        "www_notify"=>"www回调地址",
        "status"=>"状态值只能为：enabled或者disabled"
    ];

    protected $message = [

    ];

    protected $scene = [

        'update' => [
            'notify_id', 'customer_id', 'admin_notify', 'www_notify',
        ],
        'post' => [
             'customer_id', 'admin_notify', 'www_notify',
        ],
        'put' => [
            'customer_id', 'admin_notify', 'www_notify',
        ],
        'putsta' => [
            'status','notify_id'
        ],
        'get' => [
            'status'
        ],
    ];


}