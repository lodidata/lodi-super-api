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
use Psr\Http\Message\ServerRequestInterface;

/**
 * UJU支付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class UJU extends BASES
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
        $money = $this->money / 100;
        $this->parameter = array(
            'mch_id' => $this->partnerID,
            'out_trade_no' => $this->orderID,
            'body' => 'GOODS',
            'callback_url' => $this->returnUrl,
            'notify_url' => $this->notifyUrl,
            'total_fee' => sprintf("%.2f", $money),
            'service' => $this->data['bank_data'],
            'way' => $this->data['action'],
            'format' => "json"
        );
        $this->parameter['sign'] = urlencode($this->sytMd5New($this->parameter));
    }

    public function sytMd5New($pieces)
    {
        unset($pieces['body']);
        $pieces['key'] = $this->key;
        $singStr = implode($pieces);
        return md5($singStr);
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if ($re['success'] == 'true') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['pay_info'];
        } else {
            $this->return['code'] = 886;
            $this->return['msg'] = 'UJU:' .isset($re['msg']) ?$re['msg']:"第三方未知异常";
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }

    }

    //签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['out_trade_no'],
            'third_order' => $pieces['ordernumber'],
            'third_money' => $pieces['total_fee'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['out_trade_no']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if (self::retrunVail($pieces, $config)) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($pieces, $config)
    {
        $arr['mch_id'] = $pieces['mch_id'];
        $arr['device_info'] = $pieces['device_info'];
        $arr['attach'] = $pieces['attach'];
        $arr['time_end'] = $pieces['time_end'];
        $arr['out_trade_no'] = $pieces['out_trade_no'];
        $arr['ordernumber'] = $pieces['ordernumber'];
        $arr['transtypeid'] = $pieces['transtypeid'];
        $arr['transaction_id'] = $pieces['transaction_id'];
        $arr['total_fee'] = $pieces['total_fee'];
        $arr['service'] = $pieces['service'];
        $arr['way'] = $pieces['way'];
        $arr['result_code'] = $pieces['result_code'];
        $arr['key'] = $config['key'];
        return $pieces['sign'] == $this->createVerifyString($arr);
    }

    function createVerifyString($data)
    {
        $sign = '';
        foreach ($data AS $key => $val) {
            $sign .= $val;
        }

        return strtoupper(md5($sign));
    }


}