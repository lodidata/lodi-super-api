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
 * 天下付
 * Class TXF
 * @package Logic\Recharge\Pay
 */
class TXF extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        //不同的支付方式需要的参数不同
        if ($this->data['bank_data'] == '1100') {
            //网银快捷支付
            $this->_initUnionParam();
        } else {
            //扫码支付
            $this->_initScanParam();
        }
    }

    /**
     * 网银快捷支付参数初始化
     *
     * @return void
     */
    private function _initUnionParam()
    {

        $this->parameter = [
            'versionId' => 'V001',
            'businessType' => '1100',
            'insCode' => '',
            'merId' => $this->partnerID,
            'orderId' => $this->orderID,
            'transDate' => date('Ymdhis', $this->data['order_time']),
            'transAmount' => $this->money/100,
            'transCurrency' => '156',
            'transChanlName' => 'UNIONPAY',
            'openBankName' => '6222022002004648972',
            'pageNotifyUrl' => $this->returnUrl,
            'backNotifyUrl' => $this->notifyUrl,
            'orderDesc' => 'TXF_GOODS',
            'dev' => '',
        ];

        $sign = $this->_getUnionSign($this->parameter,$this->key);

        $this->parameter = array_merge($this->parameter, [
            'signType' => 'MD5',
            'signData' => $sign,
        ]);

    }

    /**
     * QQ扫码支付参数初始化
     *
     * @return void
     */
    private function _initScanParam()
    {

        $this->parameter = [
            'versionId' => '001',
            'businessType' => '1100',
            'transChanlName' => $this->data['bank_data'],
            'merId' => $this->partnerID,
            'orderId' => $this->orderID,
            'transDate' => date('Ymdhis'),
            'transAmount' => $this->money,
            'backNotifyUrl' => $this->notifyUrl,
            'orderDesc' => 'TXF_GOODS',
            'dev' => '',
        ];

        $sign = $this->_getScanSign($this->parameter,$this->key);

        $this->parameter = array_merge($this->parameter, [
            'signType' => 'MD5',
            'signData' => $sign,
        ]);
    }

    //返回参数
    public function parseRE()
    {
        //不同的支付方式返回参数的格式不同

        if ($this->data['bank_data'] == '1100') {
            $this->_parseUnionRE();
        } else {
            $this->_parseScanRE();
        }
    }

    private function _parseUnionRE() {

        $this->parameter['signData'] = urlencode($this->parameter['signData']);
        $this->parameter = urldecode(http_build_query($this->parameter));
        $this->parameter .= '&url=' . $this->payUrl;
        $this->parameter .= '&method=POST';

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL . '?' . $this->parameter;

    }

    private function _parseScanRE() {

        $this->basePost();

        $result = urldecode($this->re);

        //第三方接口返回接口，有时候是UTF-8, 有时候是GBK，GBK时json_decode会返回null

        $data = json_decode($result, true);

        //异常
        if ($data && isset($data['status']) && $data['status'] != '00') {
            $this->return['code'] = 23;
            $this->return['msg'] = 'TXF：支付错误，请联系客服。';
            $this->return['way'] = $this->showType;
            $this->return['str'] = null;

            return;
        }

        //GBK编码
        $data = json_decode(iconv('GB2312', 'UTF-8', $result), true);

        //异常
        if ($data && isset($data['status']) && $data['status'] != '00') {
            $this->return['code'] = 23;
            $this->return['msg'] = 'TXF：支付错误，请联系客服。';
            $this->return['way'] = $this->showType;
            $this->return['str'] = null;

            return;
        }

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $data['codeUrl'];
    }


    /**
     * 创建快捷签名字符串
     * 扫码的签名和快捷支付的签名方式不一样
     *
     * @access private
     *
     * @param $input 参与签名参数
     *
     * @return string 签名字符串
     */
    private function _getUnionSign($input,$key) {
        $sign = strtoupper(md5(urldecode(http_build_query($input)) . $key));

        return $sign;
    }

    /**
     * 创建扫码签名字符串
     * 扫码的签名和快捷支付的签名方式不一样
     *
     * @access private
     *
     * @param $input 参与签名参数
     *
     * @return string 签名字符串
     */
    private function _getScanSign($input,$key) {
        ksort($input);

        $input = array_filter($input);

        $sign = strtoupper(md5(urldecode(http_build_query($input)) . '&key=' . $key));

        return $sign;
    }


    //签名验证
    public function returnVerify($parameters)
    {
        $res = [
            'status' => 1,
            'order_number' => $parameters['orderId'],
            'third_order' => $parameters['ksPayOrderId'],
            'third_money' => $parameters['transAmount']*100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['orderId']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '未有该订单';
        }
        $sign=$parameters['signData'];
        unset($parameters['signData']);
        $result= $sign == $this->_getScanSign($parameters,$config['key']);
        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }
}