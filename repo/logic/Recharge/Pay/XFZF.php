<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 鑫发支付
 */
class XFZF extends BASES {

    //与第三方交互
    public function start() {
        $this->initParam();
        $this->parseRE();
    }

    //组装数组
    public function initParam() {
        $this->parameter = [
            'version'       => 'V3.3.0.0',
            'merchNo'       => $this->partnerID,
            'payType'       => $this->data['bank_data'],
            'randomNum'     => time(),
            'orderNo'       => $this->orderID,
            'amount'        => $this->money,
            'goodsName'     => 'goods',
            'notifyUrl'     => $this->notifyUrl,
            'notifyViewUrl' => $this->returnUrl,
            'charsetCode'   => 'UTF-8',
        ];

        $this->parameter['sign'] = $this->sytMd5($this->parameter);
        $json = json_encode($this->parameter);

        $body = 'data=' . urlencode($this->data_encode($json)) . '&merchNo=' . $this->partnerID;

        $this->curlPost($body);
    }

    function curlPost($referer = null) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->payUrl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $referer);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($referer),
        ]);

        $response = curl_exec($ch);

        $this->curlError = curl_error($ch);
        $this->re = $response;
    }

    //签名加密方法
    public function sytMd5($pieces) {
        ksort($pieces);
        $pieces = array_map('strval', $pieces);
        $string = json_encode($pieces, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . $this->data['app_id'];
        $sign = strtoupper(md5($string));
        return $sign;
    }

    public function parseRE() {
        $re = json_decode($this->re, true);

        if ($re['stateCode'] != '00') {
            $this->return['code'] = 5;
            $this->return['msg'] = 'xfzf:' . $re['msg'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
            return;
        }

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];
        $this->return['str'] = strtr($re['qrcodeUrl'], ['HTTPS' => 'https']);
    }

    public function returnVerify($parameters) {


        $config = Recharge::getThirdConfig($parameters['orderNo']);

        $this->key = $config['key'];
        $this->data = $config;

        $data = $this->data_decode($parameters['data']);
        $data = json_decode($data, true);

        $res = [
            'status'       => 0,
            'order_number' => $data['orderNo'],
            'third_order'  => $data['orderNo'],
            'third_money'  => $data['amount'],
            'error'        => '',
        ];

        if ($data['payStateCode'] != '00') {
            $res['error'] = '该订单未支付';
            return $res;
        }

        $sign = $data['sign'];
        unset($data['sign']);

        if ($sign != $this->sytMd5($data)) {
            $res['error'] = '该订单验签不通过';
            return $res;
        }

        $res['status'] = 1;
        return $res;
    }

    /**
     * 请求报文加密方法
     * 需要用到 $this->pubKey
     *
     * @param array $data
     *
     * @return bool|string
     */
    public function data_encode($data) {
        $public_key = "-----BEGIN PUBLIC KEY-----\r\n";
        foreach (str_split($this->pubKey, 64) as $str) {
            $public_key = $public_key . $str . "\r\n";
        }
        $public_key = $public_key . "-----END PUBLIC KEY-----";

        $public_key = openssl_get_publickey($public_key);

        if ($public_key == false) {
            return false;
        }

        $encrypt_data = '';
        $crypto = '';

        foreach (str_split($data, 117) as $chunk) {
            openssl_public_encrypt($chunk, $encrypt_data, $public_key);
            $crypto = $crypto . $encrypt_data;
        }

        $crypto = base64_encode($crypto);
        return $crypto;
    }

    /**
     * 响应报文解密方法
     * 需要用到 $this->key
     *
     * @param string $data
     *
     * @return bool|string
     */
    public function data_decode($data) {
//        $private_key = "-----BEGIN RSA PRIVATE KEY-----\r\n";
//        foreach (str_split($this->key, 64) as $str) {
//            $private_key = $private_key . $str . "\r\n";
//        }
//        $private_key = $private_key . "-----END RSA PRIVATE KEY-----";
        $private_key = openssl_get_privatekey($this->key);
        if ($private_key == false) {
            return false;
        }

        $data = base64_decode($data);
        $crypto = '';

        foreach (str_split($data, 128) as $chunk) {
            openssl_private_decrypt($chunk, $decryptData, $private_key);
            $crypto .= $decryptData;
        }
        return $crypto;
    }
}
