<?php

namespace Las\Pay;

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 *
 * 安宝付
 * @author Lion
 */
class ABF extends BASES {

    static function instantiation() {
        return new ABF();
    }

    //与第三方交互
    public function start() {
        $this->initParam();  // 数据初始化
        $this->formPost();  // 发送请求
        $this->parseRE();  // 处理结果
    }

    //组装数组
    public function initParam() {
        $this->parameter['business_token'] = $this->pubKey;  //商户令牌
        $this->parameter['business_code'] = $this->partnerID;  //商户编码
        $this->parameter['order_code'] = $this->orderID;  //订单编号
        $this->parameter['total_fee'] = $this->money;  //订单金额
        $this->parameter['pay_type'] = $this->data['bank_data'];  //付款方式
        $this->parameter['notify_url'] = $this->notifyUrl;  //通知URL
        $this->parameter['return_url'] = $this->returnUrl;  //返回URL
        $this->parameter['remark'] = '1.0';  //备注
        $this->parameter['md5'] = $this->sign();  //校验码
    }

    //生成签名
    public function sign() {

        $ms = $this->parameter['pay_type'] . $this->parameter['order_code'] . $this->parameter['business_code'] . $this->parameter['business_token'] . $this->key;
        return md5($ms);

    }

    //处理结果
    public function parseRE() {
        $re = json_decode($this->re, true);

        if ($re['code'] == 0 && $re['data']['code_url']) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['data']['code_url'];
        } else {
            $this->return['code'] = 886;
            $this->return['msg'] = 'ABF:' . $re['msg'] ?? '通道异常';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    public function formPost() {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->payUrl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->arrayToURL($this->parameter));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $response = curl_exec($ch);

        $this->re = $response;

    }


    //回调数据校验
    /*
     * $resArray 第三方通知数组
     * $deposit  系统流水信息
     * $vender   授权信息
     * */
    public function returnVerify($param = []) {
        $res = [
            'status'       => 0,
            'order_number' => $param['order_code'],
            'third_order'  => $param['order_code'],
            'third_money'  => $param['total_fee'],
            'error'        => '',
        ];

        $config = Recharge::getThirdConfig($param['order_code']);

        $ms = $param['pay_type'] . $param['order_code'] . $param['business_code'] . $param['business_token'] . $config['key'];
        if ($param['md5'] == md5($ms)) {
            $res['status'] = 1;
        } else {
            $res['error'] = '该订单验签不通过或已完成';
        }

        return $res;
    }

}
