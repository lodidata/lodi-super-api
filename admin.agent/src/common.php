<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/4/8
 * Time: 13:41
 */

function createRsponse($response,$status = 200, $state = 0, $message = 'ok', $data = null, $attributes = null){

    return $response
        ->withStatus($status)
        ->withJson([
            'data' => $data,
            'attributes' => $attributes,
            'state' => $state,
            'message' => $message,
            'ts' => time(),
        ]);
}

/**
 * 判断值是否是大于0的正整数
 *
 * @param $value
 * @return bool
 */
function isPositiveInteger($value)
{
    if (is_numeric($value) && is_int($value + 0) && ($value + 0) > 0) {
        return true;
    } else {
        return false;
    }
}


/**
 * 使用正则验证数据
 *
 * @access public
 * @param string $value
 *            要验证的数据
 * @param string $rule
 *            验证规则
 * @return boolean
 */
function regex($value, $rule)
{
    $validate = array(
        'require' => '/\S+/',
        'email' => '/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',
        'mobile' => '/^(((13[0-9]{1})|(14[5,7]{1})|(15[0-35-9]{1})|(17[0678]{1})|(18[0-9]{1}))+\d{8})$/',
        'phone' => '/^((\(\d{2,3}\))|(\d{3}\-))?(\(0\d{2,3}\)|0\d{2,3}-)?[1-9]\d{6,7}(\-\d{1,4})?$/',
        'url' => '/^http(s?):\/\/(?:[A-za-z0-9-]+\.)+[A-za-z]{2,4}(:\d+)?(?:[\/\?#][\/=\?%\-&~`@[\]\':+!\.#\w]*)?$/',
        'currency' => '/^\d+(\.\d+)?$/',
        'number' => '/^\d+$/',
        'zip' => '/^\d{6}$/',
        'integer' => '/^[-\+]?\d+$/',
        'double' => '/^[-\+]?\d+(\.\d+)?$/',
        'english' => '/^[A-Za-z]+$/',
        'bankcard' => '/^\d{14,19}$/',
        'safepassword' => '/^(?=.*\\d)(?=.*[a-z])(?=.*[A-Z]).{8,20}$/',
        'chinese' => '/^[\x{4e00}-\x{9fa5}]+$/u',
        'oddsid' => '/^([+]?\d+)|\*$/',//验证赔率设置id
        'qq' => '/^[1-9]\\d{4,14}/',//验证qq格式
    );
    // 检查是否有内置的正则表达式
    if (isset ($validate [strtolower($rule)]))
        $rule = $validate [strtolower($rule)];
    return 1 === preg_match($rule, $value);
}

function is_json($string)
{
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}