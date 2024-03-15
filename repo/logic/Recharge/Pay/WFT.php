<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 威富通
 * @author viva
 */
class WFT extends BASES {

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
            'mch_id' => $this->partnerID,//必填项，商户号
            'service' => $this->payType,//接口类型
            'notify_url' => $this->notifyUrl,
            'version' => '2.0',//固定值：
            'out_trade_no' => $this->orderID,
            'body' => 'goods-',
            'total_fee' => $this->money,
            'mch_create_ip' => $this->data['client_ip'],  //订单生成的机器 IP
            'time_start' => Date('YmdHis'),
            'nonce_str' => mt_rand(time(),time()+rand()), //随机字符串，不长于 32 位
        );
        $this->parameter['sign'] = $this->currentMd5('key=');
        $this->parameter = $this->toXml($this->parameter);
    }

    public function parseRE(){
        $re = $this->parseXML($this->re);
        if($re['status'] == 0 && $re['result_code'] == 0){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['code_url'];
        }else{
            $msg = $re['err_msg'] ?? $re['message'];
            $this->return['code'] = 886;
            $this->return['msg'] = 'WFT:'.$msg;
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
            'third_money' => $parameters['cash_fee'],
            'error' => '',
        ];

        if($parameters['pay_result'] == 0){
            $config = Recharge::getThirdConfig($parameters['out_trade_no']);
            if($this->verifyData($parameters,$config['pub_key'])){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';
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
        $sign = strtolower(md5($signPars));

        $tenpaySign = strtolower($parameters['sign']);
        return $sign == $tenpaySign;

    }
}
