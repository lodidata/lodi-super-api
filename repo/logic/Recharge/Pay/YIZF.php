<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class YIZF extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = array(
            'payKey' => $this->partnerID,
            'productName' => 'Goods'.time(),
            'orderNo' => $this->orderID,
            'orderPrice' => $this->money/100,
            'payWayCode' => $this->data['action'],
            'payTypeCode' => $this->payType,
            'orderIp' => $this->data['client_ip'],
            'orderDate' => date('Ymd'),
            'orderTime' => date('YmdHis'),
            'returnUrl' => $this->returnUrl,
            'notifyUrl' => $this->notifyUrl,
            'orderPeriod' => 60,
        );
        $this->parameter['sign'] = strtoupper($this->currentMd5('paySecret='));
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if ($re['result'] == 'success') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'success';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['code_url'];
        } else {
            $msg = $re['msg'] ?? '第三方未知错误';
            $this->return['code'] = 48;
            $this->return['msg'] = 'YIZF:' .$msg;
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => $parameters['orderNo'],
            'third_order' => $parameters['trxNo'],
            'third_money' => $parameters['orderPrice']*100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['orderNo']);
        if (!$config) {
            $res['error'] = '未有该订单或已完成';
            return $res;
        }
        $result = $this->returnVail($parameters, $config);
        if ($result) {
            $res['status'] = 1;
        } else {
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    /**
     * 验证签名
     */
    public function returnVail($pieces, $config)
    {
        $sign = $pieces['sign'];
        unset($pieces['sign']);
        $str = $this->arrayToURLALL($pieces).'&paySecret='.$config['key'];
        return strtolower($sign) == strtolower(md5($str));
    }
}