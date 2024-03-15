<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * E时代
 * @author viva
 */

class ESD extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->post();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter['service'] = $this->payType;
        $this->parameter['paytype'] = $this->data['action'];
        $this->parameter['version'] = 'v2.0';
        $this->parameter['signtype'] = 'MD5';
        $this->parameter['merchantid'] = $this->partnerID;
        $this->parameter['userid'] = 'u'.time();
        $this->parameter['shoporderId'] = $this->orderID;
        $this->parameter['totalamount'] = $this->money/100;
        $this->parameter['productname'] = 'Goods';
        $this->parameter['notify_url'] = $this->notifyUrl;
        $this->parameter['callback_url'] = $this->returnUrl;
        $this->parameter['nonce_str'] = $this->getRandstr();  //随机32位字符串
        if($this->data['action'] == 'Ecurrencypay'){
            $this->parameter['cardType'] = 1;
            $this->parameter['bankAbbr'] = 1000;
        }
        $this->parameter['sign'] = strtoupper($this->currentMd5('key='));
    }

    public function parseRE(){
        $re = json_decode($this->re,true);
        if($re['status'] == 0 && $re['result_code'] == 0){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['code_url'];
        }else{
            $msg = $re['message'] ?? $re['err_msg'];
            $this->return['code'] = 886;
            $this->return['msg'] = 'ESD:'.$msg;
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    /**
     * 返回地址验证
     *
     * @param
     * @return boolean
     */
    //[status=1 通过  0不通过,
    //order_number = '订单',
    //'third_order'=第三方订单,
    //'third_money'='金额',
    //'error'='未有该订单/订单未支付/未有该订单']
    public function returnVerify($parameters) {
        $res = [
            'status' => 0,
            'order_number' => $parameters['shoporderId'],
            'third_order' => $parameters['orderid'],
            'third_money' => $parameters['total_fee']*100,
            'error' => '',
        ];

        if($parameters['result_code'] == 0){
            $config = Recharge::getThirdConfig($parameters['shoporderId']);
            if($this->verifyData($parameters,$config['pub_key'])){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';
        return $res;
    }

    public function verifyData($parameters,$key) {
        $signPars='';
        $tempSign = strtolower($parameters['sign']);
        unset($parameters['sign']);
        foreach($parameters as $k => $v) {
            //  字符串0  和 0 全过滤了，所以加上
            if(!empty($v) || $v === 0  || $v === "0" ) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $sign = strtolower(md5($signPars.'key='.$key));
        return $tempSign == $sign;
    }

    public function getRandstr()
    {
        return strtolower(md5(uniqid(mt_rand(), true)));
    }
}
