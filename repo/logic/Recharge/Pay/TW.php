<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;
use Utils\Utils;

/**
 * 第三方支付 - 台湾支付
 */
class TW extends BASES
{

    public function start()
    {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $this->parameter = [
            'merchantid' => $this->partnerID,
            'merchantorder' => $this->orderID,
            'rmb' => $this->money,
            'callback' => $this->notifyUrl,
            'extend'=>'',
            'time' =>Utils::msectime(),
            'paytype' => $this->payType,
            'roleid'=>$this->data['client_ip']
        ];

        $this->parameter['sign'] = $this->_sign($this->parameter, $this->key);
    }

    /**
     * 组装前端数据
     */
    public function parseRE()
    {
            $re = json_decode($this->re, true);
            if ($re && $re['code'] == 0) {
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = $re['url'];
            } else {
                $this->return['code'] = 0;
                $this->return['msg'] = '台湾支付:' . $re['msg'] ?? '未知异常';
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = '';
            }
    }


    /**
     * 回调验证处理
     */
    public function returnVerify($data)
    {
        $res = [
            'status' => 1,
            'order_number' => $data['merchantorder'],
            'third_order' => $data['orderid'],
            'third_money' => $data['rmbreal'],
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($data['merchantorder']);

        //无此订单
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '订单号不存在';
            return $res;
        }

        //校验sign
        $sign = $data['sign'];
        if(array_key_exists('s',$data)){
            unset($data['s']);
        }
        unset($data['sign']);

        if (!$this->_verifySign($data, $config['key'], $sign)) {
            $res['status'] = 0;
            $res['error'] = '签名验证失败';
        }
        return $res;
    }

    /**
     * 生成sign
     */
    private function _sign($params, $key)
    {
        ksort($params);

        $string = '';
        foreach ($params as $keyVal => $param) {
            $string .= $keyVal . '=' . $param . '&';
        }
        $string .= 'key=' . $key;
        $sign = strtolower(md5($string));
        return $sign;
    }

    /**
     * 验证sign
     */
    private function _verifySign($pieces, $key, $thirdSign)
    {

        $sign = $this->_sign($pieces, $key);

        return $thirdSign == $sign;
    }
}