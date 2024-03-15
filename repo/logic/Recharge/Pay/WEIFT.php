<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 微付通支付
 * @author chuanqi
 */
class WEIFT extends BASES
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
            'shop_id' => $this->partnerID,
            'user_id' => time(),
            'money' => $this->money/100,
            'type' => $this->payType,
            'shop_no' => $this->orderID,
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl,
        );
        $this->parameter['sign'] = md5($this->partnerID.$this->parameter['user_id'].$this->parameter['money'].$this->payType.$this->key);
    }

    public function parseRE()
    {
        $re = json_decode($this->re,true);
        if(isset($re['pay_url']) && $re['pay_url'] && $re['qrcode_url']) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $this->data['show_type'] == 'code' ? $re['qrcode_url'] : $re['pay_url'];
        }else {
            $this->return['code'] = 61;
            $this->return['msg'] = 'WEIFT:'. $re['message'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    public  function returnVerify($parameters)
    {
        $parameters = json_decode($parameters,true);
        $res = [
            'status' => 0,
            'order_number' => $parameters['shop_no'],
            'third_order' => $parameters['order_no'],
            'third_money' => $parameters['money']*100,
            'error' => '',
        ];
        if($parameters['status'] == 0){
            $config = Recharge::getThirdConfig($parameters['shop_no']);
            $sign = md5($config['partner_id'].$parameters['user_id'].$parameters['order_no'].$config['key'].$parameters['money'].$parameters['type']);
            if(strtolower($sign) == strtolower($parameters['sign'])){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';
        return $res;
    }

}
