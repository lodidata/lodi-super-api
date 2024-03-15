<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 发家支付
 */
class FJZF extends BASES
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
        $money = $this->money / 100;
        $this->parameter = [
            'shopAccountId' => $this->partnerID,
            'shopUserId' => rand(1, 88888),
            'amountInString' => $money,
            'shopNo' => $this->orderID,
            'payChannel' => $this->payType
        ];

        $this->parameter['sign'] = $this->_sign($this->parameter);
        $this->parameter['shopCallbackUrl'] = $this->notifyUrl;
        $this->parameter['returnUrl'] = '';
        $this->parameter['target'] = 3; //返回json
        $this->parameter['apiVersion'] = '2';
    }

    public function parseRE()
    {
        $re = json_decode($this->re, true);

        if (isset($re['page_url']) && $re['page_url'] != null) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'success';
            $this->return['way'] = $this->data['return_type'];
            if ($this->showType == 'code' && isset($re['url'])) {
                $this->return['str'] = $re['url'];
            }
            if ($this->showType == 'sdk' && isset($re['sdk_data']) && $re['sdk_data'] != '') {
                $this->return['str'] = $re['sdk_data'];
            } else {
                $this->return['str'] = $re['page_url'];
            }
        } else {
            $this->return['code'] = 23;
            $this->return['msg'] = '发家：' . $re['message'] ?? '通道返回有误';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }


    /**
     * 生成sign
     */
    private function _sign($pieces)
    {
        $string = '';
        foreach ($pieces as $keys => $value) {
            if ($value != '' && $value != null) {
                $string = $string . $value . '&';
            }
        }

        $string = $string . $this->key;
        $sign = md5($string);
        return $sign;
    }

    /**
     * 返回地址验证
     *
     */
    public function returnVerify($parameters)
    {
        global $app;
        $parameters = $app->getContainer()->request->getParams();
        unset($parameters['s']);

        if (!isset($parameters['shop_no']) || !isset($parameters['order_no']) || !isset($parameters['money'])) {
            return false;
        }

        $res = [
            'status' => 0,
            'order_number' => $parameters['shop_no'],
            'third_order' => $parameters['order_no'],
            'third_money' => $parameters['money'] * 100,
            'error' => '',
        ];

        if ($parameters['status'] != 0) {
            $res['status'] = 0;
            $res['error'] = '渠道商返回支付失败';
            return $res;
        }

        $config = Recharge::getThirdConfig($parameters['shop_no']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '订单号不存在';
            return $res;
        }
        //校验sign
        if (!$this->_verifySign($parameters, $config, $parameters['sign'])) {
            $res['status'] = 0;
            $res['error'] = '签名验证失败';
            return $res;
        }
        $res['status'] = 1;
        return $res;
    }

    /**
     * 验证sign
     */
    private function _verifySign($data, $config, $sign)
    {
        /**
         * 创建订单 apiVersion=1 时，不传默认为1，注意：此时 回调签名&是不参与md5计算： md5(shopAccountId&user_id&trade_no&KEY&money&type)
         * 创建订单apiVersion =2时，注意： 此时&符号是参与md5 计算的：md5(shopAccountId&status&trade_no&shop_no&money&type&KEY)
         */

        $sign_array = array(
            $config['partner_id'],
            $data['status'],
            $data['trade_no'],
            $data['shop_no'],
            $data['money'],
            $data['type'],
            $config['key']
        );

        $string = '';
        foreach ($sign_array as $keys => $value) {
            if ($value !== '') {
                $string = $string . $value . '&';
            }
        }
        $string = rtrim($string, '&');
        $re_sign = md5($string);
        if ($re_sign != $sign) {
            return false;
        }
        return true;
    }

}
