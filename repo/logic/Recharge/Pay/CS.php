<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 彩世
 * @author viva
 */


class CS extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter = array(
            //基本参数
            'outOid' => $this->orderID,
            'merchantCode' => $this->data['app_id'],
            'mgroupCode' => $this->partnerID,
            'payType' => $this->payType,
            'payAmount' => $this->money,
            'goodName' => 'goods',
        );
        if($this->payType == 'YLBILL'){
            unset($this->parameter['payType']);
            unset($this->parameter['payAmount']);
            unset($this->parameter['goodName']);
            $this->parameter['goodsName'] = 'goods';
            $this->parameter['transAmount'] = $this->money;
            $this->parameter['bankCode'] = $this->data['bank_code'];
            $this->parameter['goodsDesc'] = '订单：'.$this->orderID;
            $this->parameter['terminalType'] = 2;
            $this->parameter['userType'] = 1;
            $this->parameter['cardType'] = 1;
        }
        $this->parameter['sign'] = strtoupper(md5($this->arrayToURL().'&key='.$this->key));
        $notifyUrl = $this->notifyUrl;
        if($this->payType != 'YLBILL') {
            $temp = array(
                'notifyUrl' => $notifyUrl,
            );
            if ($this->payType == 37)
                $this->parameter['pageUrl'] = $this->returnUrl;
        }else{
            $temp = array(
                'tradeNotifyUrl' => $notifyUrl,
                'pageNotifyUrl' => $this->returnUrl
            );
        }
        $this->parameter = array_merge($this->parameter,$temp);
    }

    public function parseRE(){
        $re = json_decode($this->re,true);
        if(isset($re['code']) && $re['code'] == '000000'){
            if(isset($re['value']['qrcodeUrl'])){
                $payUrl = $re['value']['qrcodeUrl'];
            }elseif(isset($re['value']['requestUrl'])){
                $url = $re['value']['requestUrl'];
                unset($re['value']['requestUrl']);
                $re['value']['sign'] = urlencode($re['value']['sign']);
                $this->parameter = $re['value'];
                $this->parameter['url'] = $url;
                $this->parameter['method'] = 'POST';
                $payUrl = $this->jumpURL.'?'.$this->arrayToURL();
            }else{
                $payUrl = $re['value']['url'].'?cipher_data='.urlencode($re['value']['cipher_data']);
            }
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $payUrl;
        }else{
            $msg = $re['msg'] ?? $this->curlError;
            $this->return['code'] = 886;
            $this->return['msg'] = 'CS:'.$msg;
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
            'order_number' => $parameters['outOid'],
            'third_order' => $parameters['platformOid'],
            'third_money' => $parameters['tranAmount'],
            'error' => '',
        ];

        if($parameters['orderStatus'] == 2){
            $config = Recharge::getThirdConfig($parameters['outOid']);
            if($this->verifyData($parameters,$config['pub_key'])){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';
        return $res;
    }

    public function verifyData($parameters,$key) {
        $tenpaySign = strtolower($parameters['sign']);
        unset($parameters['sign']);
        unset($parameters['notifyType']);
        $signPars ='';
        ksort($parameters);
        foreach($parameters as $k => $v) {
            //  字符串0  和 0 全过滤了，所以加上
            if(!empty($v) || $v === 0  || $v === "0" ) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .= 'key='. $key;
        $sign = strtolower(md5($signPars));
        return $sign == $tenpaySign;
    }
}






