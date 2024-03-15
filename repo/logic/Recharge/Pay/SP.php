<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * 速派
 * Class GT
 * @package Logic\Recharge\Pay
 */
class SP extends BASES
{

    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->get();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = array(
            'companyId' => $this->partnerID,
            'userOrderId' => $this->orderID,
            'payType'=>$this->data['bank_data'],
            'item' => 'GOODS',
            'fee' => $this->money,
            'callbackUrl'=>$this->returnUrl,
            'syncUrl'=>$this->notifyUrl,
            'ip' => $this->data['client_ip']
        );
        $this->parameter['sign'] = urlencode($this->sytMd5New($this->parameter,$this->key));

    }

    public function sytMd5New($pieces,$key)
    {
        $str=$pieces['companyId'].'_'.$pieces['userOrderId'].'_'.$pieces['fee'].'_'.$key;
        return md5($str);
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['result']) && $re['result'] == 0) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['param'];
        } else {
            $msg = $re['msg'] ?? "未知异常";
            $this->return['code'] = 886;
            $this->return['msg'] = '速派:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

    //签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['userOrderId'],
            'third_order' => $pieces['orderId'],
            'third_money' => $pieces['fee'],
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['userOrderId']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        $sign = $pieces['sign'];
        if (self::retrunVail($sign, $pieces,$config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($sign,$pieces,$key)
    {
        return $sign == $this->sytMd5New($pieces,$key);
    }

}