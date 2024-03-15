<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 小微支付
 * @author chuanqi
 */
class XWZF extends BASES
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
            'mch_num' => $this->partnerID,
            'order_num' => $this->orderID,
            'pay_money' => $this->money/100,
            'pay_type' => $this->data['bank_data'],
            'user_ip' => $this->data['client_ip'],
            'notify_url' => $this->notifyUrl,
        );

        $this->parameter['sign'] = $this->currentMd5();
    }

    public function parseRE()
    {
        $re = json_decode($this->re,true);
        if (isset($re['return_code']) && $re['return_code'] == '0000') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['pay_url'];
        } else {
            $this->return['code'] = 47;
            $this->return['msg'] = 'XWZF:' . $re['return_msg'] ?? '';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    public  function returnVerify($parameters)
    {
        if(!is_array($parameters)){
            $parameters = json_decode($parameters,true);
        }
        $res = [
            'status' => 0,
            'order_number' => $parameters['order_num'],
            'third_order' => $parameters['order_num'],
            'third_money' => $parameters['pay_money'] * 100,
            'error' => '',
            
        ];
        if($parameters['order_status'] == 'SUCCESS'){
            $config = Recharge::getThirdConfig($parameters['order_num']);
            $this->key = $config['key'];
            $sign = strtolower($parameters['sign']);
            unset($parameters['sign']);
            $this->parameter = $parameters;
            $tmpSign = strtolower($this->currentMd5());
            if($sign == $tmpSign){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';

        return $res;
    }

}
