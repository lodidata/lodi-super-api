<?php
/**
 * 智能账本V2
 * Author: Taylor 2019-03-11
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class ZNZBV2 extends BASES
{

    //与第三方交互
    public function start()
    {
        $this->initParam();//数据初始化
        $this->basePost();
        $this->parseRE();//处理结果
    }

    //组装数组
    public function initParam()
    {
        /**
         * ALIPAY_PC
         * 2 ALIPAY_MOBILE
         * 3 WECHAT_PC
         * 4 WECHAT_MOBILE
         * 支付宝码支付
         * 支付宝移动端支付
         * 微信扫码支付
         * 微信移动端支付
         */
        $this->parameter['merchantCode'] = $this->partnerID;//商户号
        $this->parameter['method'] = $this->data['bank_data'];//支付类型，支付编号
        $this->parameter['signType'] = 'MD5';
        $this->parameter['dateTime'] = date('YmdHis', time());
        $this->parameter['orderNum'] = $this->orderID;//商户订单号
        $this->parameter['payMoney'] = $this->money;//订单金额，单位为分
        $this->parameter['productName'] = $this->orderID;
        $this->parameter['notifyUrl'] = $this->notifyUrl;//异步通知
        $this->parameter['spbillCreateIp'] = $this->data['client_ip'];

        $this->parameter['sign'] = $this->sign($this->parameter, $this->key);//签名
    }

    //生成签名
    public function sign($data, $key)
    {
        ksort($data);
        $str = join("", $data) . $key;
        return md5($str);
    }

    //处理结果
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['platRespCode']) && $re['platRespCode'] == 'SUCCESS' && !empty($re['payUrl'])) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['payUrl'];
        } else {
            $this->return['code'] = 99;
            $this->return['msg'] = '智能账本:' . (isset($re['platRespMessage']) ? $re['platRespMessage'] : '未知异常');
            $this->return['way'] = $this->showType;
        }
    }

    /*
     * 第三方通知数组
     * */
    public function returnVerify($pieces)
    {
        unset($pieces['s']);

        if (!(isset($pieces['orderNum']) && isset($pieces['platOrderNum']) && isset($pieces['amount']))) {
            return false;
        }

        $res = [
            'status' => 0,
            'order_number' => $pieces['orderNum'],//商户订单号
            'third_order' => $pieces['platOrderNum'],//第三方的支付订单号
            'third_money' => $pieces['amount'],//支付金额为分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($res['order_number']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if ($pieces['status'] != 'SUCCESS') {
            $res['status'] = 0;
            $res['error'] = '支付失败';
            return $res;
        }
        if (self::returnVail($pieces, $config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    //验签
    public function returnVail($array, $signKey)
    {
        $sys_sign = $array['platSign'];
        unset($array['platSign']);
        $my_sign = $this->sign($array, $signKey);
        return $my_sign == $sys_sign;
    }
}