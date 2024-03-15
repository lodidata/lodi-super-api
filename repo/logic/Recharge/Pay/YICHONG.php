<?php

/**
 *   易充支付
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class YICHONG extends BASES
{

    /**
     * 生命周期
     */
    public function start()
    {

        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $this->returnUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/return_url.php';
        $pub_params = [
            "member_id" => $this->partnerID,
            "submit_order_id" => $this->orderID,
            "amount" => sprintf('%.2f', $this->money / 100),
            "type_name" => $this->payType,
            "notify_url" => $this->notifyUrl,
            "callback_url" => $this->returnUrl,
        ];
        $pub_params['sign'] = $this->_sign($pub_params, $this->key);
        $this->parameter = $pub_params;
    }

    /**
     * 组装前端数据,输出结果，使用go.php方法，自动post到支付
     */
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if ($re && $re['code'] == 200 && $re['data']) {
            //响应结果
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['data']['qrcode'];
        } else {
            $this->return['code'] = 99;
            $this->return['msg'] = 'YICHONG:' . (isset($re['msg']) ? $re['msg'] : '请求失败');
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($parameters)
    {

        if (!(isset($parameters['submit_order_id']) && isset($parameters['amount']))) {
            return false;
        }

        $res = [
            'order_number' => $parameters['submit_order_id'],
            'third_order' => $parameters['submit_order_id'],
            'third_money' => $parameters['amount'] * 100,
            'status' => 0,
            'error' => ''
        ];
        $config = Recharge::getThirdConfig($parameters['submit_order_id']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if ($parameters['return_code'] != '0') {
            $res['status'] = 0;
            $res['error'] = '支付订单状态未成功';
            return $res;
        }
        $result = $this->returnVail($parameters, $config['key']);
        if (!$result) {
            $res['status'] = 0;
            $res['error'] = '验签失败';
            return $res;
        }
        $res['status'] = 1;
        return $res;
    }

    /**
     * 生成sign
     */
    private function _sign($params, $tkey)
    {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            if ($v != '' && $v != null && $k != 'sign') {
                $string = $string . $k . '=' . $v . '&';
            }
        }
        $sign_str = $string . 'key=' . $tkey;
        $sign = md5($sign_str);
        return strtoupper($sign);
    }

    /**
     * 回调后进行业务判断
     * @param $params
     * @param $conf
     * @param $reques_params
     * @return bool
     */
    public function returnVail($params, $tkey)
    {
        $return_sign = $params['sign'];
        unset($params['sign']);
        $sign = $this->_sign($params, $tkey);
        if ($sign != $return_sign) {
            return false;
        }
        return true;
    }
}
