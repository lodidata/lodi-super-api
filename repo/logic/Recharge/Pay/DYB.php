<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 威富通
 * @author viva
 */
class DYB extends BASES {

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
            'method' => 'mbupay.alipay.sqm',
            'appid'  => $this->data['app_id'],//支付平台分配的APPID
            'mch_id' => $this->partnerID,//必填项，商户号
            'nonce_str' => mt_rand(time(),time()+rand()), //随机字符串，不长于 32 位
            'out_trade_no' => $this->orderID, //订单号
            'body' => 'dyb',//描述
            'total_fee' => $this->money,//订单金额
            'notify_url' => $this->notifyUrl, //回调地址
        );
        $this->parameter['sign'] = $this->createSign($this->parameter,$this->key);
        $this->parameter = $this->toXml($this->parameter);
    }

    public function parseRE(){
        $re = $this->parseXML($this->re);
        if($re['return_code'] == 'SUCCESS' && $re['result_code'] == 'SUCCESS'){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            if($this->data['return_type']=='code' && $this->data['show_type']=='code'){
                $this->return['str'] = $re['s_code_url'];
            }else{
                $this->return['str'] = $re['code_url'];
            }
        }else{
            $msg = $re['return_msg'] ?? '';
            $this->return['code'] = 886;
            $this->return['msg'] = 'DYB:'.$msg;
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
            'order_number' => $parameters['out_trade_no'],
            'third_order' => $parameters['transaction_id'],
            'third_money' => $parameters['total_fee'],
            'error' => '',
        ];
        if($parameters['return_code'] == 'SUCCESS' && $parameters['result_code'] == 'SUCCESS'){
            $config = Recharge::getThirdConfig($parameters['out_trade_no']);
            if($this->verifyData($parameters,$config['pub_key'])){
                $res['status']  = 1;
            }else{
                $res['error'] = '该订单验签不通过或已完成';
            }
        }else
            $res['error'] = '该订单未支付或者支付失败';
        return $res;
    }

    public function verifyData($parameters,$key) {
        $signPars = "";
        ksort($parameters);
        foreach($parameters as $k => $v) {
            if("sign" != $k && "" != $v) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .= "key=" . $key;
        $sign = strtoupper(md5((string)$signPars));
        $tenpaySign = $parameters['sign'];
        return $sign == $tenpaySign;

    }
    /**
     * 生成密钥
     */
   public function createSign($parameters,$key){
       $signPars = "";
       ksort($parameters);
       foreach($parameters as $k => $v) {
           if("sign" != $k && "" != $v) {
               $signPars .= $k . "=" . $v . "&";
           }
       }
       $signPars .= "key=" . $key;
       $sign = strtoupper(md5((string)$signPars));
       return $sign;
    }
}
