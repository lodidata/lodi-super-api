<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;


/**
 * 第三方支付 - 微联支付
 * @author Jacky.Zhuo
 * @date 2018-06-25
 */


class WL extends BASES {

    public function start() {
        $this->initParam();

//        $this->get();

        $this->parseRE();
    }

    public function returnVerify($data) {
        $res = [
            'status' => 1,
            'order_number' => $data['orderid'],
            'third_order' => $data['sysorderid'],
            'third_money' => 0,
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($data['orderid']);

        //无此订单
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '订单号不存在';

            return $res;
        }

        $sign = $data['sign'];

        //校验失败
        if (!$this->signVerify($data, $config['key'], $sign)) {
            $res['status'] = 0;
            $res['error'] = '签名验证失败';

            return $res;
        }

        $res['third_money'] = $data['ovalue'] * 100;

        return $res;
    }

    /**
     * 提交参数组装
     *
     * @access public
     */
    public function initParam() {
        $this->parameter = [
            'parter' => $this->partnerID,                                           //商户ID
            'type' => $this->data['bank_data'],                                     //银行类型
            'value' => str_replace(',', '', number_format($this->money / 100, 2)),  //金额
            'orderid' => $this->orderID,                                            //商户订单号
            'callbackurl' => $this->notifyUrl,                                      //异步通知地址
            'hrefbackurl' => $this->returnUrl,                                      //同步通知地址
            'payerIp' => Client::getIp(),                                           //支付用户IP
            'attach' => 'WL_GOOD',                                                  //备注消息
            'agent' => null,                                                        //代理ID
        ];

        $sign = urlencode($this->_getPaySign($this->parameter));

        $this->parameter = array_merge($this->parameter, [
            'sign' => $sign,
        ]);
    }

    /**
     * 回调签名验证方法
     * 注意：回调签名字段与支付请求字段不一样
     *
     * @access public
     *
     * @param array $input 参与签名参数
     * @param string $key 支付用户key
     * @param string $sign 签名
     *
     * @return bool 验证结果
     */
    public function signVerify(array $input, string $key, string $sign) {
        $params = ['orderid', 'opstate', 'ovalue'];

        $result = [];

        foreach ($params as $param) {
            $result[] = $param . '=' . $input[$param];
        }

        $result = md5(implode('&', $result) . $key);

        return $result == $sign;
    }

    /**
     * 组装返回前端数据
     *
     * @access public
     */
    public function parseRE() {
        $this->parameter['url'] = $this->payUrl.'?';
        $this->parameter['method'] = 'GET';
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = 'jump';
        $this->return['str'] = $this->jumpURL . '?' . $this->arrayToURL();
    }

    /**
     * 生成支付签名字符串
     *
     * @access private
     *
     * @param array $input 参与签名参数
     *
     * @return string 签名字符串
     */
    private function _getPaySign(array $input) {
        $params = ['parter', 'type', 'value', 'orderid', 'callbackurl'];

        $sign = [];

        foreach ($params as $param) {
            $sign[] = $param . '=' . $input[$param];
        }

        $sign = md5(implode('&', $sign) . $this->key);

        return $sign;
    }
}