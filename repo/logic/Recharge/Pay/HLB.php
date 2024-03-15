<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 好丽宝支付
 * @author chuanqi
 */
class HLB extends BASES
{

    //与第三方交互
    public function start()
    {
        $this->initParam();
//        $this->basePost();
        $this->parseRE();
    }

    //组装数组
    public function initParam()
    {
        $this->parameter = array(
            //基本参数
            'inputCharset' => 'UTF-8',
            'notifyUrl' => $this->notifyUrl,
            'pageUrl' => $this->returnUrl,
            'payType' => $this->payType,
            'merchantId' => $this->partnerID,
            'orderId' => $this->orderID,
            'transAmt' => $this->money/100,
            'orderTime' => date('Y-m-d H:i:s'),
        );
        if($this->data['bank_code']){
            $this->parameter['bankCode'] = $this->data['bank_code'];
        }

        if($this->data['action']){
            $this->parameter['isPhone'] = $this->data['action'];
        }
        $this->parameter['sign'] = urlencode($this->signStr($this->parameter,$this->key));
        $this->parameter['orderTime'] = urlencode($this->parameter['orderTime']);
    }

    public function signStr($data = array(),$key)
    {
        ksort($data);
        $o = "";
        foreach ($data as $k => $v) {
            $o .= "$k=" . $v . "&";
        }
        $str = substr($o, 0, -1);
        return MD5($str."&key=". $key);
    }

    public function parseRE()
    {
        $this->parameter['url'] = $this->payUrl;
        $this->parameter['method'] = 'POST';
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];
        $this->return['str'] = $this->jumpURL.'?'.$this->arrayToURL();
    }

    public  function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => $parameters['transId'],
            'third_order' => $parameters['orderId'],
            'third_money' => $parameters['transAmt'] * 100,
            'error' => '',
        ];
        if($parameters['status'] == 1){
            $config = Recharge::getThirdConfig($parameters['transId']);
            $sign = strtolower($parameters['sign']);
            unset($parameters['sign']);
            unset($parameters['returnParams']);
            $tmpSign = strtolower($this->signStr($parameters,$config['key']));
            if($sign == $tmpSign){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';
        return $res;
    }

}
