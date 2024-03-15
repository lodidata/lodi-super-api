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

/**
 * 信付通
 * Class XFT
 * @package Logic\Recharge\Pay
 */
class XFT extends BASES
{
    //与第三方交互
    public function start(){
        $this->initParam();
        $this->parseRE();
    }

    //初始化参数
    public function initParam(){
        $this->payUrl .= '/payment/v1/order/' . $this->partnerID . '-' . $this->orderID;

        /**
         * 获取支付方式 WXPAY.H5
         * $bankData[0] 支付类型 WXPAY | ALIPAY | 银行CODE
         * $bankData[1] 支付方式 app | web | H5
         */
        $bankData = explode('.', $this->data['bank_data']);

        $this->parameter = [
            'body' => 'GOOD_XFT',  //商品的具体描述
            'charset' => 'UTF-8',
            'defaultbank' => $bankData[0],  //网银代码
            'isApp' => $bankData[1],  //接入方式
            'merchantId' => $this->partnerID,  //支付平台分配的商户ID
            'notifyUrl' => $this->notifyUrl,  //异步通知信息
            'orderNo' => $this->orderID,  //商户订单号
            'paymentType' => 1,  //支付类型，固定值为1
            'paymethod' => 'directPay',  //支付方式，directPay：直连模式；bankPay：收银台模式
            'returnUrl' => $this->returnUrl,  //支付成功跳转URL
            'service' => 'online_pay',  //固定值online_pay，表示网上支付
            'title' => 'GOOD_XFT',  //商品的名称
            'totalFee' => $this->money/100,  //订单金额
        ];

        $sign = $this->_getSign($this->parameter,$this->key);

        $this->parameter = array_merge($this->parameter, [
            'signType' => 'SHA',
            'sign' => $sign
        ]);
    }



    //返回参数
    public function parseRE(){
        $this->parameter['sign'] = urlencode($this->parameter['sign']);
        $this->parameter = $this->arrayToURL();
        $this->parameter .= '&url=' . $this->payUrl;
        $this->parameter .= '&method=POST';

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL . '?' . $this->parameter;
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status' => 1,
            'order_number' => $parameters['order_no'],
            'third_order' => $parameters['trade_no'],
            'third_money' => $parameters['total_fee']*100,
            'error' => '',
        ];
        $sign=$parameters['sign'];
        unset($parameters['sign'], $parameters['signType']);
        $config=Recharge::getThirdConfig($parameters['order_no']);
        $result=$this->_getSign($parameters,$config['key']) == $sign;
        if($result){
            $res['status']=1;
        }else{
            $res['status']=0;
            $res['error']='验签失败！';
        }
        return $res;
    }

    /**
     * 创建签名字符串
     *
     * @access private
     *
     * @param $input 参与签名参数
     * @return string 签名字符串
     */
    private function _getSign($input,$key) {
        ksort($input);

        $sign = strtoupper(sha1(urldecode(http_build_query($input)) .$key));

        return $sign;
    }
}