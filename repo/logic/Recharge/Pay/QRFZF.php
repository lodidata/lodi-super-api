<?php
/**
 * 全日付支付
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class QRFZF extends BASES
{

    /**
     * 生命周期
     */
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $time = time();
        $this->parameter = array(
            'nonceStr' => rand(1, 9999),
            'startTime' => date('YmdHis', $time),
            'merchantNo' => $this->partnerID,
            'outOrderNo' => $this->orderID,
            'amount' => $this->money,
            'client_ip' => $this->data['client_ip'],
            'timestamp' => $time,
            'productCode' => $this->payType,
            'notifyUrl' => $this->notifyUrl,
            'returnUrl' => $this->notifyUrl,
        );
        $this->parameter["sign"] = $this->_sign($this->parameter, $this->key);
    }

    /**
     * 组装前端数据,输出结果，使用go.php方法，自动post到支付
     */
    public function parseRE()
    {
        foreach ($this->parameter as &$item) {
            $item = urlencode($item);
        }
        $this->parameter = $this->arrayToURL();
        $this->parameter .= '&url=' . urlencode($this->payUrl);
        $this->parameter .= '&method=POST';

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL . '?' . $this->parameter;

        //给re赋值，记录到数据库
        $this->re = $this->return;
    }


    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($parameters)
    {
        global $app;
        $parameters = $app->getContainer()->request->getParams();
        unset($parameters['s']);

        $res = [
            'order_number' => $parameters['outOrderNo'],//商户订单号
            'third_order' => $parameters['orderNo'],//平台流水号
            'third_money' => $parameters['amount'],//支付金额，以分为单位
            'error' => '',
            'status' => 0,
        ];
        $config = Recharge::getThirdConfig($res['order_number']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if ($parameters['orderStatus'] != 1) {//00:支付成功/01:失败
            $res['status'] = 0;
            $res['error'] = '付款失败';
            return $res;
        }
        $result = $this->returnVail($parameters, $config['key']);
        if (!$result) {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
            return $res;
        }
        $res['status'] = 1;
        return $res;
    }

    /**
     * 生成sign
     */
    private function _sign($pieces, $tkey)
    {
        ksort($pieces);
        $string = [];
        foreach ($pieces as $key => $val) {
            if ($key != 'sign' && $val != '') {
                $string[] = $key . '=' . $val;
            }
        }
        $params = join('&', $string);

        $sign_str = $params . '&' . 'key=' . $tkey;
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