<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 中付
 * @author viva
 */


class ZHONGF extends BASES {

    //与第三方交互
    public function start(){
            $this->initParam();
            $this->basePost();
            $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter['transId'] = $this->payType;
        $this->parameter['serialNo'] = $this->orderID;
        $this->parameter['merNo'] = $this->partnerID;
        $this->parameter['merKey'] = $this->data['app_secret'];
        $this->parameter['merIp']=$this->data['client_ip'];
        $this->parameter['orderNo'] = $this->orderID;
        $this->parameter['transAmt'] = $this->money/100;
        $this->parameter['orderDesc'] = 'desc';
        $this->parameter['transDate'] = date('Ymd');
        $this->parameter['transTime'] = date('YmdHis');
        $this->parameter['overTime'] = 20;
        $this->parameter['notifyUrl'] = $this->notifyUrl;
        $this->parameter['sign'] = strtoupper($this->currentMd5('paySecret='));
    }

    public function parseRE(){
        $re = json_decode($this->re,true);
        if(isset($re['authCode'])&&$re['authCode']){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = strtolower($re['authCode']);
        }else{
            $msg = $re['respDesc'] ?? $this->curlError;
            $this->return['code'] = 886;
            $this->return['msg'] = 'ZHONGF:'.$msg;
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
            'order_number' => $parameters['orderNo'],
            'third_order' => $parameters['trxNo'],
            'third_money' => $parameters['transAmt']*100,
            'error' => '',
        ];

        if($parameters['respCode'] == '0000'){
            $config = Recharge::getThirdConfig($parameters['orderNo']);
            if($this->verifyData($parameters,$config['key'])){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';
        return $res;
    }


    /**
     * 返回地址验证
     *
     * @param
     * @return boolean
     */
    public function verifyData($parameters,$key) {
        $tenpaySign = strtolower($parameters['sign']);
        unset($parameters['sign']);
        $signPars ='';
        ksort($parameters);
        foreach($parameters as $k => $v) {
            //  字符串0  和 0 全过滤了，所以加上
            if(!empty($v) || $v == "" ) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .= 'paySecret='.$key;
        $sign = strtolower(md5($signPars));
        return $sign == $tenpaySign;
    }

}
