<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/4/8
 * Time: 13:58
 */
namespace Lib\Validate;

use Lib\Validate\BaseValidate;

class IDMustBePositiveInteger extends BaseValidate
{
    // 验证规则
    protected $rule = [
        'id' => 'isNotEmpty|isPositiveInteger',
    ];

    protected $message = [
        'id.isPositiveInteger' => 'id必须是正整数',
    ];
}