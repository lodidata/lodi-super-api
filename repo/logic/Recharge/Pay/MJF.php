<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 第三方支付 - 明捷付
 * @author Jacky.Zhuo
 * @date 2018-06-22 17:30
 */


class MJF extends BASES {

    public function start() {
        $this->initParam();

        $this->get();

        $this->parseRE();
    }

    public function returnVerify($data) {
        $res = [
            'status' => 1,
            'order_number' => $data['orderNum'],
            'third_order' => $data['orderNum'],
            'third_money' => 0,
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($data['orderNum']);

        //无此订单
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '订单号不存在';

            return $res;
        }

        //消息加解密需要使用公私钥
        $this->key = $config['key'];
        $this->pubKey = $config['pub_key'];
        //消息需要解密
        if($this->data_decode($data['data']))
            $result = json_decode($this->data_decode($data['data']), true);
        else{
            $res['status'] = 0;
            $res['error'] = '解密失败';
            return $res;
        }
        $sign = $result['sign'];
        unset($result['sign']);
        ksort($result);
        //校验失败
        if (!$this->signVerify($result, $config['token'], $sign)) {
            $res['status'] = 0;
            $res['error'] = '签名验证失败';

            return $res;
        }

        if ($result['payStateCode'] != '00') {
            $res['status'] = 0;
            $res['error'] = '支付失败';

            return $res;
        }

        $res['third_money'] = $result['amount'];

        return $res;
    }

    /**
     * 提交参数组装
     *
     * @access public
     */
    public function initParam() {
        $data = [
            'version' => 'V3.0.0.0',
            'merchNo' => $this->partnerID,
            'netwayCode' => $this->data['bank_data'],
            'randomNum' => strval(mt_rand(10000000, 99999999)),
            'orderNum' => $this->orderID,
            'amount' => strval($this->money),
            'goodsName' => 'MJF_GOODS',
            'callBackUrl' => $this->notifyUrl,
            'callBackViewUrl' => $this->returnUrl,
            'charset' => 'UTF-8',
        ];

        $data['sign'] = $this->_getSign($data, $this->data['token']);

        ksort($data);

        $this->parameter = [
            'data' => urlencode($this->data_encode(json_encode($data, JSON_UNESCAPED_UNICODE))),
            'merchNo' => $this->partnerID,
            'version' => 'V3.0.0.0',
        ];
    }

    /**
     * 返回前端数据组装
     *
     * @access public
     */
    public function parseRE() {
        $result = json_decode($this->re, true);

        if (!$result || $result['stateCode'] != '00') {
            $this->return['code'] = 23;
            $this->return['msg'] = 'MJF：' . $result['msg'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = null;

            return;
        }

        //实时响应验签，防止数据传输过程中被修改
        $sign = $result['sign'];
        unset($result['sign']);
        ksort($result);
        if (!$this->signVerify($result, $this->data['token'], $sign)) {
            $this->return['code'] = 23;
            $this->return['msg'] = 'MJF：支付错误，请联系客服。';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = null;

            return;
        }

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];
        $this->return['str'] = $result['qrcodeUrl'];
    }

    /**
     * 回调签名验证方法
     *
     * @param array $input 参与签名参数
     * @param string $key 密钥
     * @param string $sign 签名
     *
     * @return bool 验证结果
     */
    public function signVerify($input, $key, $sign) {
        return $this->_getSign($input, $key) == $sign;
    }

    /**
     * 创建签名字符串
     *
     * @access private
     *
     * @param array $input 参与签名参数
     * @param string $key
     *
     * @return string 签名字符串
     */
    private function _getSign($input, $key) {
        ksort($input);

        $input = json_encode($input, 320);

        return strtoupper(md5($input . $key));
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
        $private_key = "-----BEGIN RSA PRIVATE KEY-----\r\n";
        foreach (str_split($this->key, 64) as $str) {
            $private_key = $private_key . $str . "\r\n";
        }
        $private_key = $private_key . "-----END RSA PRIVATE KEY-----";

        $private_key = openssl_get_privatekey($private_key);
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