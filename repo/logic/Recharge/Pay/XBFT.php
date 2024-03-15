<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 新百付通
 * @author viva
 */
class XBFT extends BASES
{

    //与第三方交互
    public function start()
    {

        $this->initParam();
        $this->parseRE();
    }

    //组装数组
    public function initParam()
    {
        //
        //merchantId(商户号),notifyUrl(同步通知URL),sign(签名RSA),outOrderId(合作伙伴交易号),subject(订单名称),body(订单描述),transAmt(交易金额),
        //scanType(扫码类型支付宝: 10000001；微信: 20000002；QQ钱包: 30000003)
        $this->parameter = [
            'merchantId' => $this->partnerID,//merchantId(商户号)
            'outOrderId' => $this->orderID,//outOrderId(合作伙伴交易号)
            'scanType' => $this->data['bank_data'],//scanType(扫码类型支付宝
            'transAmt' => $this->money/100,//transAmt(交易金额)
            'notifyUrl' => $this->notifyUrl,
            'subject' => 'XBFT',
            'body' => 'testOrder',
        ];

        $this->parameter['sign'] = $this->currentOpenssl(OPENSSL_ALGO_SHA1);

    }


    public function parseRE()
    {
        if($this->showType == 'code'){
            $this->basePost();
            $re = json_decode($this->re, true);
//            print_r($this->curlError);
//            print_r($this->re);
            if (isset($re['respCode']) && $re['respCode'] == '99') {
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->showType;
                $this->return['str'] = $re['payCode'];
            } else {
                $re['respMsg'] = $re['respMsg'] ?? '第三方未知错误';
                $this->return['code'] = 886;
                $this->return['msg'] = 'XBFT:' . $re['respMsg'];
                $this->return['way'] = $this->showType;
                $this->return['str'] = '';
            }
        }else{
            $this->parameter['url'] = $this->payUrl;
            $this->parameter['method'] = 'POST';
            $this->parameter['sign'] = urlencode($this->parameter['sign']);

            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $this->jumpURL . '?' . $this->arrayToURL();
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
            'order_number' => $parameters['outOrderId'],
            'third_order' => $parameters['localOrderId'],
            'third_money' => $parameters['transAmt']*100,
            'error' => '',
        ];

        if($parameters['respType'] == 'S'){
            $config = Recharge::getThirdConfig($parameters['outOrderId']);
            $sign_status = $this->signVerify($parameters,$config['pub_key'],$config['partner_id']);
            if($sign_status){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';

        return $res;
    }


    /**
     * 返回地址验证
     *
     * @param
     * @return boolean
     */
    public  function signVerify($p,$key,$merchNo) {
        $data = array();
        foreach ($p as $k => $v) {
            if ($k == 'sign' || $k == 'signType' || $v == '') {
                continue;
            }
            $data[$k] = $v;
        }
        $sign = $this->array_to_querystr($data);
        return $this->rsaRecvSign($sign, $p['sign'], $merchNo, $key);
    }

    public function getRecvSignature($parm, $merchNo, $key) {
        $data = array();
        foreach ($parm as $k => $v) {
            if ($k == 'sign' || $k == 'signType' || $v == '') {
                continue;
            }
            $data[$k] = $v;
        }
        $sign = $this->array_to_querystr($data);
        return $this->rsaRecvSign($sign, $parm['sign'], $merchNo, $key);
    }

    public function array_to_querystr($parm, $enc = false) {
        $ary = [];
        foreach ($parm as $k => $v) {
            $val     = ($enc) ? urlencode($v) : $v;
            $ary[$k] = $k . '=' . $val;
        }
        ksort($ary);
        return implode('&', $ary);
    }
    public function rsaRecvSign($data, $sign, $merid, $key) {
        $key    = openssl_get_publickey($key);
        $verify = openssl_verify($data, base64_decode($sign), $key, OPENSSL_ALGO_SHA1);
        return $verify;
    }


}
