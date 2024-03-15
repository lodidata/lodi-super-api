<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 一支付
 * @author viva
 */


class YZF extends BASES {

    //与第三方交互
    public function start(){

        $this->initParam();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter['merchantId'] = $this->partnerID;
        $this->parameter['notifyUrl'] = $this->notifyUrl;
        $this->parameter['outOrderId'] = $this->orderID;
        $this->parameter['subject'] = 'goods';
        $this->parameter['body'] = 'desc';
        $this->parameter['transAmt'] = $this->money/100;
        $this->parameter['scanType'] = $this->payType;
        $this->parameter['sign'] = $this->currentOpenssl(OPENSSL_ALGO_SHA1);
    }

    public function parseRE(){
        //H5直接跳转
        if($this->data['show_type'] != 'code'){
            $this->parameter['url'] = $this->payUrl;
            $this->parameter['method'] = 'POST';
            $this->parameter['sign'] = urlencode($this->parameter['sign']);

            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $this->jumpURL . '?' . $this->arrayToURL();
        }else {
            $this->basePost();
            $re = json_decode($this->re, true);
            if (isset($re['respType']) && $re['respType'] == 'R') {
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] =  $this->showType;
                $this->return['way'] = 'code';
                $this->return['str'] = $re['payCode'];
            } else {
                $msg = isset($re['respMsg']) ? $re['respMsg'] : $this->re;
                $this->return['code'] = 75;
                $this->return['msg'] = 'YZF:' . $msg;
                $this->return['way'] =  $this->showType;
                $this->return['str'] = '';
            }
        }
    }

    /**
     * @param $parameters
     * @return array
     */
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
        $ary = array();
        foreach ($parm as $k => $v) {
            $val     = ($enc) ? urlencode($v) : $v;
            $ary[$k] = $k . '=' . $v;
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
