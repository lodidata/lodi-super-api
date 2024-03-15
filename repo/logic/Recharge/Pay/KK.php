<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * @author viva
 */
class KK extends BASES {

    //与第三方交互
    public function start() {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //组装数组
    public function initParam() {
        $this->parameter = [
            'orderId'        => $this->orderID,
            'rechargeAmount' => $this->money / 100,
            'type'           => 2,
            'returnUrl'      => $this->returnUrl,
            'notifyUrl'      => $this->notifyUrl,
            'appId'          => $this->partnerID,
            'customerName'   => 'n' . time(),
        ];

        $signPars = implode('', $this->parameter);
        openssl_sign($signPars, $sign_info, $this->key, OPENSSL_ALGO_SHA256);
        $this->parameter['sign'] = base64_encode('' . $sign_info);
    }

    public function parseRE() {
        $re = json_decode($this->re, true);

        if (isset($re['code']) && $re['code'] == 0) {
            $re['data']['payLink'] = str_replace("&amp;", "&", $re['data']['payLink']);
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['data']['payLink'];
        } else {
            $this->return['code'] = 886;
            $this->return['msg'] = 'KK:' . $re['message'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    /**
     * 返回地址验证
     *
     * @param
     *
     * @return array
     */
    public function returnVerify($input) {
        $res = [
            'status'       => 0,
            'order_number' => $input['orderId'],
            'third_order'  => $input['orderId'],
            'third_money'  => $input['paymentAmount'] * 100,
            'error'        => '',
        ];
        $config = Recharge::getThirdConfig($input['orderId']);
        if (!$config) {
            $res['error'] = '订单已完成或不存在';
        } else if ($this->verify($input, $config['pub_key'])) {
            $res['status'] = 1;
        } else {
            $res['error'] = '该订单验签不通过';
        }

        return $res;
    }

    public function verify($input, $puk) {
        $sign = base64_decode($input['sign']);
        unset($input['sign']);
        $this->parameter = $input;
        $original_str = implode('', $input);//得到的签名
        return openssl_verify($original_str, $sign, $puk, OPENSSL_ALGO_SHA256);
    }
}