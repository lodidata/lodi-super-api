<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * 第三方支付 -SKBank支付
 */
class SKBANK extends BASES
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
            'amount' => $this->money/100,
            'outOrderNo' => $this->orderID,
            'orderDesc' => 'GOODS',
            'timestamp' => $this->getMillisecond(),
            'nonceStr' => rand(1, 9999),
            'returnUrl' => $this->returnUrl,
            'notifyUrl' => $this->notifyUrl,
            'appId' => $this->partnerID,
            'userUnqueNo' => md5($this->data['client_ip']),
            'payType' => $this->payType,
            'attach' => 'GOODS'
        ];

        $this->parameter['signature'] = $this->_sign($this->parameter, $this->key);
    }

    /**
     * 组装前端数据
     */
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if ($re && $re['code'] == 1) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['data'];
        } else {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SKBank:' . $re['msg'] ?? '未知异常';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

// 毫秒级时间戳
    function getMillisecond()
    {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }

    /**
     * 回调验证处理
     */
    public function returnVerify($data)
    {
        $res = [
            'status' => 1,
            'order_number' => $data['outOrderNo'],
            'third_order' => $data['orderNo'],
            'third_money' => $data['money']*100,
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($data['outOrderNo']);

        //无此订单
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '订单号不存在';
            return $res;
        }

        //校验sign
        $sign = $data['signature'];

        if (!$this->_verifySign($data, $config, $sign)) {
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
        $data = array("outOrderNo" => $params['outOrderNo'], "amount" => $params['amount'], "payType" => $params['payType'], "attach" => $params['attach']);
        ksort($data);//把key，从小到大排序
        $paramUrl = "?" . http_build_query($data, '', '&');//封装成url参数
        //print_r($paramUrl);
        $md5Value = strtolower(md5($paramUrl . $params['appId'] . $params['timestamp'] . $params['nonceStr']));
        return strtoupper(md5($md5Value . $key));
    }

    /**
     * 验证sign
     */
    private function _verifySign($pieces, $config, $thirdSign)
    {

        $data = array("outOrderNo" => $pieces['outOrderNo'], "amount" => $pieces['money'], "payType" => $pieces['payType'], "attach" => $pieces['attach']);
        ksort($data);//把key，从小到大排序
        $paramUrl = "?" . http_build_query($data, '', '&');//封装成url参数
        //print_r($paramUrl);
        $md5Value = strtolower(md5($paramUrl . $config['partner_id'] . $pieces['timestamp'] . $pieces['nonceStr']));

        $sign=strtoupper(md5($md5Value . $config['key']));
        return $thirdSign == $sign;
    }
}