<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 龙腾付支付
 * @author chuanqi
 */
class LTF extends BASES
{

    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->post();
        $this->parseRE();
    }

    //组装数组
    public function initParam()
    {
        $this->parameter = array(
            //基本参数
            'down_num' => $this->partnerID,
            'pay_service' => $this->payType,
            'amount' => $this->money,
            'order_down' => $this->orderID,
            'subject' => 'GOODS-',
            'client_ip' => $this->data['client_ip'],
            'version' => '1.0',
            'callback_url' => $this->returnUrl,
            'notify_url' => $this->notifyUrl,
        );
        $this->parameter['sign'] = strtoupper($this->currentMd5('key='));
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
        $re = json_decode($this->re,true);
        if($re['rst_status'] == 'SUCCESS') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $this->showType == 'code' ? $re['pay_qrcode'] : $re['pay_info'];
        }else {
            $this->return['code'] = 61;
            $this->return['msg'] = 'LTF:'.$re['rst_msg'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    public  function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => $parameters['order_down'],
            'third_order' => $parameters['order_up'],
            'third_money' => $parameters['amount'],
            'error' => '',
        ];
        if($parameters['order_status'] == 1){
            $config = Recharge::getThirdConfig($parameters['order_down']);
            $sign = strtolower($parameters['sign']);
            unset($parameters['sign']);
            $this->key = $config['key'];
            $this->parameter = $parameters;
            $tmpSign = strtolower($this->currentMd5('key='));
            if($sign == $tmpSign){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';
        return $res;
    }

}
