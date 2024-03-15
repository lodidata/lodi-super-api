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
use Utils\Client;


/**
 * Onego支付
 * Class Onego
 * @package Logic\Recharge\Pay
 */
class ONEGO extends BASES
{

    protected $token='';

    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->post();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = array(
            'out_trade_no' => $this->orderID,
            'amount' => $this->money / 100,
            'notify_url' => $this->notifyUrl
        );
        if ($this->showType == 'h5') {
            $this->parameter['return_url'] = $this->returnUrl;
            $this->token = $this->key;
        } else {
            $this->token = $this->pubKey;
        }
    }


    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        $str = '';
        if (!isset($re['message'])) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            if (isset($re['qrcode'])) {
                $str = $re['qrcode'];
            }
            if (isset($re['uri'])) {
                $str = $re['uri'];
            }
            $this->return['str'] = $str;
        } else {
            $this->return['code'] = 886;
            $this->return['msg'] = 'onego:' . $re['message'].'token:'.$this->token;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($parameters)
    {
        global $app;
        $headers = $app->getContainer()->request->getHeaders();

        $res = [
            'status' => 1,
            'order_number' => $parameters['out_trade_no'],
            'third_order' => $parameters['trade_no'],
            'third_money' => $parameters['amount'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['out_trade_no']);
        if ($parameters['status'] != 'success') {
            $res['status'] = 0;
            $res['error'] = '失败！';
            return $res;
        }
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        $result = hash_equals(base64_encode(hash_hmac('sha256', json_encode($parameters), $config['key'], true)), $headers['HTTP_X_GOPAY_SIGNATURE'][0]);
        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！' . $headers['HTTP_X_GOPAY_SIGNATURE'][0];
        }
        return $res;
    }


    public function post()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->payUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->parameter));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->toHeaders($headers));
        $response = curl_exec($ch);
        $this->curlError = curl_error($ch);
        $this->re = $response;
    }

    private function toHeaders($headers)
    {
        $results = [];
        foreach ($headers as $key => $value) {
            $results[] = $key . ': ' . $value;
        }

        return $results;
    }
}