<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 金钻支付
 */
class JZUANPAY extends BASES
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
     * 初始化参数
     */
    public function initParam()
    {
        $payData = [
            'jUserId' => rand(100000, 999999),
            'jUserIp' => $this->data['client_ip'],
            'jOrderId' => $this->orderID,
            'orderType' => 1,//1 为充值订单
            'payWay' => $this->payType,
            'amount' => $this->money/100,
            'currency' => 'CNY',
            'notifyUrl' => $this->notifyUrl,
        ];

        $baseData = [
            'merchantId' => $this->partnerID,
            'timestamp' => $this->msectime(),
            'signatureMethod' => 'HmacSHA256',
            'signatureVersion' => 1,
        ];

        $signData = array_merge($payData, $baseData);
        $baseData['signature'] = $this->getSign($signData, $this->key);


        $this->payUrl .= '?' . $this->arrayToURLALL($baseData);
        $this->parameter = $payData;
    }

    //获取毫秒
    private function msectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    }

    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['code']) && isset($re['data']['paymentUrl']) && $re['code'] === 0) {
            //响应结果
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['data']['paymentUrl'];
        } else {
            //响应错误信息
            $this->return['code'] = 1;
            $this->return['msg'] = '金钻支付:' . ($re['message'] ?? 'UnKnow');
            $this->return['way'] = $this->showType;
        }
    }

    public function getSign($arr, $key)
    {
        ksort($arr);
        $signPars = '';
        foreach ($arr as $k => $v) {
            $signPars .= $k . "=" . $v . "&";
        }
        $signPars = rtrim($signPars, '&');
        return strtoupper(hash_hmac('sha256', $signPars, $key));
    }


    public function returnVerify($data)
    {
        unset($data['s']);
        if (!(isset($data['status']) && isset($data['jOrderId']) && isset($data['actualAmount']))) {
            return false;
        }

        $res = [
            'status' => 0,
            'order_number' => $data['jOrderId'],
            'third_order' => $data['orderId'],
            'third_money' => $data['actualAmount']*100,
        ];

        if (!in_array($data['status'], [2, 3])) {
            $res['error'] = '订单未完成';
            return $res;
        }

        $config = Recharge::getThirdConfig($res['order_number']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '订单号不存在';
            return $res;
        }

        if (!$this->_verifySign($data, $config['key'])) {
            $res['status'] = 0;
            $res['error'] = '签名验证失败';
            return $res;
        }
        $res['status'] = 1;
        $res['error'] = '验签通过';
        return $res;
    }

    private function _verifySign($arr, $key)
    {
        $returnSign = $arr['signature'];
        unset($arr['signature']);
        $sign = $this->getSign($arr, $key);
        return $sign == $returnSign;
    }

}