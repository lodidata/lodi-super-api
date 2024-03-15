<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 *
 * 同银
 * @author Lion
 */
class TY extends BASES {

    private $_busMap = [];
    private $_busData = [];
    private $bank_name = [
        '102'=>'工商银行',
        '103'=>'农业银行',
        '104'=>'中国银行',
        '105'=>'建设银行',
        '302'=>'中信银行',
        '303'=>'光大银行',
        '304'=>'华夏银行',
        '306'=>'广发银行',
        '307'=>'平安银行',
        '308'=>'招商银行',
        '309'=>'兴业银行',
        '310'=>'浦发银行',
        '313'=>'北京银行',
        '403'=>'邮储银行',
    ];
    //与第三方交互
    public function start() {
        $this->initParam();       // 数据初始化
        $this->basePost();
        $this->parseRE();         // 处理结果
    }

    //组装数组
    public function initParam() {

        $this->_busData['requestId'] = $this->orderID;           //商户订单号
        $this->_busData['orgId'] = $this->data['app_id'];      //机构号
        $this->_busData['productId'] = $this->data['action'];  //用app_secret存放productID
        $this->_busData['dataSignType'] = '0' . '';                   //环境 1 正式
        $this->_busData['timestamp'] = date('YmdHis', time());

        $this->_busMap['merno'] = $this->partnerID;          //商家号
        $this->_busMap['bus_no'] = $this->data['bank_data'];  //业务编号
        $this->_busMap['amount'] = $this->money;             //交易金额  单位分
        $this->_busMap['goods_info'] = time();                   //商品名称
        $this->_busMap['order_id'] = $this->orderID;           //订单号
        $this->_busMap['return_url'] = $this->returnUrl;         //前端通知地址
        $this->_busMap['notify_url'] = $this->notifyUrl;         //后台通知地址
        if(isset($this->data['bank_code']) && $this->data['bank_code']){
            $this->_busMap['card_type'] = 1;
            $this->_busMap['channelid'] = 1;
            $this->_busMap['cardname'] = $this->bank_name[$this->data['bank_code']] ?? '';
            $this->_busMap['bank_code'] = $this->data['bank_code'];
        }
        $this->sign();
        $this->parameter = $this->_busData;
    }

    //生成签名
    public function sign() {

        $this->_busData['businessData'] = json_encode($this->_busMap);

        ksort($this->_busData);
        $lastvalue = end($this->_busData);
        $b = '';
        foreach ($this->_busData as $key => $value) {
            if ($value == $lastvalue) {
                $b .= $key . '=' . $value;
            } else {
                $b .= $key . '=' . $value . '&';
            }
        }

        $b .= $this->pubKey;

        $signData = md5($b);
        $signData = strtoupper($signData);
        $this->_busData['signData'] = $signData;
    }

    //处理结果
    public function parseRE() {
        $re = json_decode($this->re, true);
        //网银与快捷
        if ($re['respCode'] == '00' && isset($re['result'])) {
            $result = json_decode($re['result'], true);
            $show_type = isset($re['qrtype']) && $re['qrtype'] == 2 ? 'jump' : $this->data['return_type'];
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $show_type;
            $this->return['str'] = $result['url'];
        } else {
            $msg = $re['msg'] ?? $re['respMsg'];
            $this->return['code'] = 886;
            $this->return['msg'] = 'TY:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //回调数据校验
    /*
     * $parameters 第三方通知数组
     * $key  公钥
     * $app_id  商户PID
     * */
    public function returnVerify($parameters) {

        $res = [
            'status'       => 0,
            'order_number' => $parameters['order_id'],
            'third_order'  => $parameters['plat_order_id'],
            'third_money'  => $parameters['amount'],
            'error'        => '',
        ];

        $config = Recharge::getThirdConfig($parameters['order_id']);

        $sign = $parameters['sign_data'];
        unset($parameters['sign_data']);
        ksort($parameters);
        $lastvalue = end($parameters);
        $b = '';
        foreach ($parameters as $key => $value) {
            if ($value == $lastvalue) {
                $b .= $key . '=' . $value;
            } else {
                $b .= $key . '=' . $value . '&';
            }
        }
        $b .= $config['pub_key'];
        $signData = md5($b);

        if ($signData == $sign) {
            $res['status'] = 1;
        } else {
            $res['error'] = '该订单验签不通过或已完成';
        }
        return $res;

    }
}
