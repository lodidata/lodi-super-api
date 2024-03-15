<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/19
 * Time: 10:09
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * Class DEDAO  得到支付
 * @package Logic\Recharge\Pay
 */
class DEDAO extends BASES {
    //与第三方交互
    public function start() {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam() {
        $this->parameter = [
            'mch_id'           => $this->partnerID,
            'trade_type'       => $this->data['bank_data'],
            'nonce'            => mt_rand(time(), time() + rand()),
            'timestamp'        => strtolower(date('Y-m-d')),
            'subject'          => 'GOODS',
            'out_trade_no'     => $this->orderID,
            'total_fee'        => $this->money,
            'spbill_create_ip' => $this->data['client_ip'],//终端ip
            'notify_url'       => $this->notifyUrl,
            'return_url'       => $this->returnUrl,
        ];

        if ($this->data['bank_data'] == 'GATEWAY') {
            $this->parameter['issuer_id'] = $this->data['bank_code'] ?: '';
        }

        $this->parameter['sign'] = $this->sytMd5($this->parameter);
    }

    public function sytMd5($params) {
        ksort($params);
        $data = "";
        foreach ($params as $key => $value) {
            if ($value === '' || $value == null) {
                continue;
            }
            $data .= $key . '=' . $value . '&';
        }
        $sign = md5($data . 'key=' . $this->key);
        return strtoupper($sign);
    }


    //返回参数
    public function parseRE() {
        $re = json_decode($this->re, true);

//        var_dump($re);die;
        if ($re['result_code'] == 'SUCCESS') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['qrcode'] ?? $re['pay_url'];

        } else {
            $this->return['code'] = $re['code'] ?? 1;
            $this->return['msg'] = 'DEDAO:' . $re['result_msg'] ?? '请求错误';
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status'       => 1,
            'order_number' => $parameters['out_trade_no'],
            'third_order'  => $parameters['trade_no'],
            'third_money'  => $parameters['total_fee'],
            'error'        => '',
        ];
        $config = Recharge::getThirdConfig($parameters['out_trade_no']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '未有该订单';
        }

        ksort($parameters);
        $data = "";
        foreach ($parameters as $key => $value) {
            if ($value === '' || $value == null || $key == 'sign') {
                continue;
            }
            $data .= $key . '=' . $value . '&';
        }

        $data = preg_replace("/&$/", '', $data);
        $plaintext = md5($data);
        $result = self::verifySign($plaintext, $parameters['sign'], $config['pub_key']);

        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }


    /**
     * 验证签名
     *
     * @param string $data 数据
     * @param string $sign 签名
     * @param string $publicKey 公钥
     *
     * @return bool
     */
    public static function verifySign($data = '', $sign = '', $publicKey = '') {
        if (!is_string($sign) || !is_string($sign)) {
            return false;
        }
        return (bool)openssl_verify($data, base64_decode($sign), $publicKey, OPENSSL_ALGO_SHA256);
    }
}