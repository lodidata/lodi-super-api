<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 聚合BOSS
 * @author viva
 */


class JHBOSS extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter['version'] = '3.0';
        $this->parameter['method'] = 'Gt.online.interface';
        $this->parameter['partner'] = $this->partnerID;
        $this->parameter['banktype'] = $this->data['bank_data'];
        $this->parameter['paymoney'] = $this->money/100;
        $this->parameter['ordernumber'] = $this->orderID;
        $this->parameter['callbackurl'] = $this->notifyUrl;
        $this->sort = false;
        $this->parameter['sign'] = md5($this->arrayToURL().$this->key);
        $this->parameter['hrefbackurl'] = $this->returnUrl;
    }

    public function parseRE(){
        $this->parameter['url'] = $this->payUrl;
        $this->parameter['method2'] = 'POST';
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL.'?'.$this->arrayToURL();
        $this->return['money'] = $this->money;
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
            'order_number' => $parameters['ordernumber'],
            'third_order' => $parameters['sysnumber'],
            'third_money' => $parameters['paymoney'] * 100,
            'error' => '',
        ];
        if($parameters['orderstatus'] == 1){
            $config = Recharge::getThirdConfig($parameters['ordernumber']);
            $tenpaySign = strtolower($parameters['sign']);
            $signPars ="partner={$parameters['partner']}&ordernumber={$parameters['ordernumber']}&orderstatus={$parameters['orderstatus']}&paymoney={$parameters['paymoney']}{$config['pub_key']}";
            $sign = strtolower(md5($signPars));
            if($sign == $tenpaySign){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';

        return $res;
    }
}
