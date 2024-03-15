<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 新豆豆支付
 * @author viva
 */
class NEWDD extends BASES
{

    //与第三方交互
    public function start()
    {

        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //组装数组
    public function initParam()
    {

        $this->parameter = [
            'partner_id' => $this->data['app_id'],
            'service' => $this->data['action'],
            'sign_type' => 'RSA',
            'rand_str' => $this->getRandstr(),
            'version' => 'v1',
            'merchant_no' => $this->partnerID,
            'merchant_order_sn' => $this->orderID,
            'paychannel_type' => $this->data['bank_data'],
            'trade_amount' => $this->money,
            'merchant_notify_url' => $this->notifyUrl,
            'ord_name' => 'DD',
            'interface_type' => 1,
            'merchant_return_url' => $this->returnUrl
        ];

        $bank_data = $this->data['bank_data'];
        if ($bank_data == 'weixin_wap' || $bank_data == 'alipay_wap' || $bank_data == 'qq_wap' || $bank_data = 'jd_wap') {
            $this->parameter['client_ip'] = '127.0.0.1';
        }

        if ($bank_data == 'gateway') {
            $this->parameter['bank_code'] = $this->data['bank_code'];
        }
        $this->parameter['sign'] = $this->_sign($this->parameter);
//        print_r($this->parameter);die;

    }

    public function getRandstr()
    {
        return strtolower(md5(uniqid(mt_rand(), true)));
    }

    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['errcode']) && $re['errcode'] == '0') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['data']['out_pay_url'];
        } else {
            $re['msg'] = $re['msg'] ?? '第三方未知错误';
            $this->return['code'] = 886;
            $this->return['msg'] = 'DD:' . $re['msg'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    /**
     * 返回地址验证
     *
     */
    //[status=1 通过  0不通过,
    //order_number = '订单',
    //'third_order'=第三方订单,
    //'third_money'='金额',
    //'error'='未有该订单/订单未支付/未有该订单']
    public function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => $parameters['merchant_order_sn'],
            'third_order' => $parameters['order_sn'],
            'third_money' => $parameters['trade_amount'],
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($parameters['merchant_order_sn']);

        //校验sign
        $publicKeyString = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($config['pub_key'], 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        if ($this->_verifySign($parameters, $publicKeyString, $parameters['sign'])) {
            $res['status'] = 1;
        }else{
            $res['status'] = 0;
            $res['error'] = '验签失败';
        }
        return $res;
    }

    private function _sign($pieces)
    {
        ksort($pieces);
        $string = '';
        foreach ($pieces as $k => $v){
            if ($v != '' && $v != null && $k != 'sign'){
                $string = $string . $k . '=' . $v . '&';
            }
        }
        $string = substr($string, 0, strlen($string) - 1);
        // var_dump($string);exit;
        $private_key = "-----BEGIN RSA PRIVATE KEY-----\r\n";
        foreach (str_split($this->key, 64) as $str) {
            $private_key = $private_key . $str . "\r\n";
        }
        $private_key = $private_key . "-----END RSA PRIVATE KEY-----";

        $sign = '';
        if ($res = openssl_get_privatekey($private_key)) {
            // $sign = $this->rsaEncryptStr($string, $private_key);
            openssl_sign($string, $sign, $res);

            openssl_free_key($res);

            $sign = base64_encode($sign);
        }

        return $sign;
    }



    /**
     * 验证sign
     */
    private function _verifySign($data, $pubkey, $sign)
    {
        ksort($data);
        $arg = '';
        foreach($data as $key => $val) {
            if ($key == 'sign' || $val == '')
                continue;

            $arg .= $key.'='.$val.'&';
        }
        //去掉最后一个&符号
        $arg = substr($arg, 0, strlen($arg) - 1);

        // var_dump($arg);exit;
        //如果带有反斜杠 则转义
        if (get_magic_quotes_gpc()) $arg = stripslashes($arg);
        $res = openssl_get_publickey($pubkey);
        $result = (bool) openssl_verify($arg, base64_decode($sign), $res);
        // var_dump($result);exit;
        openssl_free_key($res);

        return $result;
    }


}
