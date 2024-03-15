<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * JANEPAY
 * 一支付
 */
class JANEPAY extends BASES
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
        $money = $this->money / 100;
        //参与签名字段
        $data = [
            'merchantNo' => $this->partnerID,
            'signType' => 'md5',
            'outTradeNo' => $this->orderID,
            'notifyUrl' => $this->notifyUrl,
            'fontUrl' => $this->returnUrl,
            'amount' => $money,
            'payment' => $this->payType, //支付类型
            'sence' => "wap",//PAGE PC网页, WAP 移动网页, APP 移动APP, SCAN 扫码, PUB 公众号 -- 全是小写
            'member' => rand(1, 88888),//会员标识
        ];

        //有传入sence情况
        if (strpos($this->payType, '|')) {
            $args = explode('|', $this->payType);
            if (sizeof($args) == 2) {
                $data['payment'] = $this->payType = $args[0];
                $data['sence'] = $args[1];
            }
        }

        //不参与签名字段
        $pub_params = [
            'clientIp' => $this->clientIp,
            'sign' => $this->getSign($data, $this->key)
        ];
        $this->parameter = array_merge($data, $pub_params);
    }

    public function getSign($pieces, $api_key)
    {
        ksort($pieces);
        $string = '';
        foreach ($pieces as $key => $val) {
            $string = $string . $key . '=' . $val . '&';
        }

        $string = rtrim($string, '&');
        $string .= $api_key;
        $sign = md5($string);
        return $sign;
    }

    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['data']) && $re['code'] == '1000') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            if ($this->data['return_type'] == 'sdk') {
                $this->return['str'] = $re['data']['orderInfo'];
            } else {
                $this->return['str'] = $re['data']['result'];
            }
        } else {
            $this->return['code'] = 23;
            $this->return['msg'] = 'JANEPAY：' . $re['message'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = null;
        }
    }

    public function returnVerify($data)
    {
        global $app;
        $data = $app->getContainer()->request->getParams();
        unset($data['s']);

        if (!isset($data['outTradeNo']) || !isset($data['transactNo']) || !isset($data['amount'])) {
            return false;
        }

        $res = [
            'status' => 1,
            'order_number' => $data['outTradeNo'],
            'third_order' => $data['transactNo'],
            'third_money' => $data['amount'] * 100,
            'error' => '',
        ];

//        if ($data['tradeStatus'] != 2) {
//            $res['status'] = 0;
//            $res['error'] = '订单号未成功支付';
//            return $res;
//        }

        $config = Recharge::getThirdConfig($res['order_number']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '订单号不存在';
            return $res;
        }

        if (!$this->_verifySign($data, $data['signValue'], $config)) {
            $res['status'] = 0;
            $res['error'] = '签名验证失败';
            return $res;
        }

        return $res;
    }

    private function _verifySign($data, $signOld, $config)
    {
        unset($data['signValue']);
        $sign = $this->getSign($data, $config['key']);
        return $sign == $signOld;
    }


}