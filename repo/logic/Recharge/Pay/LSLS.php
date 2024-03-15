<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 劳斯莱斯
 * @author benchan
 */
class LSLS extends BASES
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

        $this->parameter = array(
            //基本参数
            'agentNo' => $this->partnerID,
            'amount' => $this->money,
            'paymentType' =>$this->data['bank_data'],
            'notifyUrl' => $this->notifyUrl,
            'orderNo' => $this->orderID,
            'timestamp' => $this->msectime()
        );

        $this->parameter['sign'] = $this->getSign($this->parameter, $this->key);

    }

    //返回当前的毫秒时间戳
    function msectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }

    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['success']) && $re['success'] == true) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['data'];
        } else {
            $this->return['code'] = 0;
            $this->return['msg'] = '劳斯莱斯:' . $re['msg'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    public function getSign($params, $tKey)
    {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            if ($v != '' && $v != null && $k != 'sign') {
                $string = $string . $k . '=' . $v . '&';
            }
        }
        $sign = md5($string . 'key=' . $tKey);
        return $sign;
    }


    public function returnVerify($parameters)
    {
        $res = [
            'status' => 1,
            'order_number' => $parameters['orderNo'],
            'third_order' => $parameters['tradeNo'],
            'third_money' => $parameters['amount'],
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['orderNo']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }

        if (!$this->returnVail($parameters, $config['key'], $parameters['sign'])) {
            $res['status'] = 0;
            $res['error'] = '该订单验签不通过或已完成';
            return $res;
        }
        return $res;
    }

    public function returnVail($params, $tkey, $thirdSign)
    {
        $params['payTime'] = urldecode($params['payTime']);
        $sign = $this->getSign($params, $tkey);

        return $thirdSign == $sign;
    }
}
