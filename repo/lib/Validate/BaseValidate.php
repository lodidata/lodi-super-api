<?php
/**
 * author      : ben <956841620@qq.com>
 * createTime  : 2018/4/09 22:34
 * description :
 * 基础校验类库
 *
 * 完成参数校验并实现通用校验方法
 *
 */

namespace Lib\Validate;


use Lib\Exception\ParamsException;
class BaseValidate extends Validate
{
    /**
     * 用于对参数进行批量校验
     *
     * @param string $scene 支持场景教研
     * @param string $method 可指定http方法
     * @return bool
     * @throws ParamsException
     */
    public function paramsCheck($scene = '', $request, $response, $batch = false)
    {
        $params = $request->getParams(); // 获取所有参数

        $result = $this->scene($scene)->batch($batch)->check($params); // 批量校验

        if (!$result) {
            $newResponse = createRsponse($response,400,10,$this->error);

            throw new ParamsException($request,$newResponse);

        }

        return true;
    }

    // 不允许为空
    protected function isNotEmpty($value, $rule = '', $data = '', $field = '')
    {
        if (empty($value)) {
            return $field . '不允许为空';
        } else {
            return true;
        }
    }

    // 必须是正整数
    protected function isPositiveInteger($value, $rule = '', $data = '', $field = '')
    {
        if (isPositiveInteger($value)) {
            return true;
        }
        return $field . '必须是正整数';
    }

    /**
     * 按照正则来判断参数是否合法
     *
     * @param $value
     * @param string $rule
     * @param string $data
     * @param string $field
     */
    protected function checkValueByRegex($value, $rule = '', $data = '', $field = '')
    {
        if (empty($value)) {
            return false;
        }

        return regex($value, $rule);
    }

}