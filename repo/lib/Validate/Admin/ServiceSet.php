<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/18
 * Time: 18:27
 */

namespace Lib\Validate\admin;

use Lib\Validate\BaseValidate;
class ServiceSet extends BaseValidate
{
    // 验证规则
    protected $rule = [
        "access_way"   => "require|in:1,2",
        "name"     =>"require",
        //"link"=>"require",
    ];
    protected $field = [
        "access_way"=>"客服接入方式，默认1 客服系统。2 在线客服代码链接",
        "name"=>"客户名称（厅主名称）",
        "link"=>"链接地址",
    ];

    protected $message = [

    ];

    protected $scene = [

        'create' => [
            'access_way', 'name','link'
        ],
        'update' => [
            'access_way'=>'require|in:1,2', 'name'=>'require','require'=>'require'
        ],
        'get' => [
            'name'=>'length:0,255'
        ],
    ];
}