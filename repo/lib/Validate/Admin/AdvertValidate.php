<?php
/**
 * author      : ben <956841620@qq.com>
 * createTime  : 2018/4/09 23:25
 * description :
 */

namespace Lib\Validate\Admin;

use Lib\Validate\BaseValidate;
class AdvertValidate extends BaseValidate
{
    // 验证规则
    protected $rule = [
        "name"=>"require|unique:advert,name,,,Admin",
        "pf"=>"require|in:pc,h5",
        "status"=>"require|in:enabled,disabled,deleted",
        "position"=>"require|in:home,egame,live,lottery,sport,agent",
        "picture"=>"require|url",
        "link_type"=>"require|in:1,2",
        "link"=>"requireIf:link_type,1|url",
        "sort"=>"require|integer",
    ];
    protected $field = [
        'name'   =>    '标题',
        'pf'   =>    '使用平台',
        'status'   =>    '状态',
        'position'   =>    '位置',
        'picture'   =>    '图片',
        'link_type'   =>    '关联类型',
        'link'   =>    '跳转链接',
        'sort'   =>    '排序',
    ];

    protected $message = [
//        'name.require' => '标题不能为空',
//        'name.unique' => '标题已经存在',


    ];

    protected $scene = [
        'put' => [
            'name'=>'require|max:150|unique:advert,name,,,Admin',//重写
            'pf', 'status', 'position', 'picture','link_type','sort'=>'require|integer'
        ],

        'patch' => [
            'name'=>'require|unique:advert,name^id,,,Admin',//重写
            'pf', 'status', 'position', 'picture','link_type','sort'=>'integer'
        ],

        'post' => [
            'name'=>'require|max:150|unique:advert,name,,,Admin',//重写
            'pf', 'status', 'position', 'picture','link_type','sort'=>'require|integer'
        ],
    ];


}