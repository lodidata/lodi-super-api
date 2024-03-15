<?php
/**
 * 坚果支付
 * Created by PhpStorm.
 * Date: 2019/2/11
 * Time: 14:15
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class JGZF extends BASES
{

    /**
     * 生命周期
     */
    public function start()
    {
        $this->initParam();
        $this->payJson2();
        $this->parseRE();
    }

    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $this->parameter = array(
            'merchantCode' => $this->partnerID,
            'interfaceVersion' => '1.0',
            'serviceType' => $this->payType,
            'notifyUrl' => $this->notifyUrl,
            'orderId' => $this->orderID,
            'amount' => sprintf("%.2f",$this->money/100),
        );
        $this->parameter["sign"] = $this->_sign($this->parameter, $this->key);
    }

    /**
     * 组装前端数据,输出结果，使用go.php方法，自动post到支付
     */
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['status']) && $re['status'] == 'SUCCESS') {
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = $re['payUrl'];
        }else {
            $this->return['code'] = 1;
            $this->return['msg']  = '坚果支付:'.$re['message'];
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = '';
        }
    }



    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($parameters)
    {
        $res = [
            'order_number' => $parameters['orderId'],//商户订单号
            'third_order' => $parameters['sysOrderId'],//平台流水号
            'third_money' => $parameters['amount']*100,//支付金额，以分为单位
            'error' => '',
            'status' => 1,
        ];
        $config = Recharge::getThirdConfig($parameters['orderId']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if ($parameters['status'] != 'SUCCESS') {//00:支付成功/01:失败
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
            if ($key != 'sign') {
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