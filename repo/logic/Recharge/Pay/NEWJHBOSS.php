<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 聚合BOSS
 * @author viva
 */


class NEWJHBOSS extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter['mchNo'] = $this->partnerID;
        $this->parameter['money'] = $this->money/100;
        $this->parameter['notify_url'] = $this->notifyUrl;
        $this->parameter['paytype'] = $this->payType;
        $this->parameter['remark'] = 'goods';
        $this->parameter['returnurl'] = $this->returnUrl;
        $this->parameter['tradeno'] = $this->orderID;
        $this->parameter['time'] = time();
        $this->sort = false;
        $this->parameter['sign'] = $this->currentMd5('key=');
    }

    public function parseRE(){
        if($this->payType == 'alipayapp') {
            $this->basePost();
            $re = json_decode($this->re,true);
            if(isset($re['data']['payurl']) && $re['data']['payurl']) {
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->showType;
                $this->return['str'] = 'https://ds.alipay.com/?from=mobilecodec&scheme='.$re['data']['payurl'];
            }else {
                $this->return['code'] = 0;
                $this->return['msg'] = $re['msg'];
                $this->return['way'] = $this->showType;
                $this->return['str'] = '';
            }
        }else {
            $this->parameter['notify_url'] = urlencode($this->notifyUrl);
            $this->parameter['returnurl'] = urlencode($this->returnUrl);
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $this->payUrl.'?'.$this->arrayToURL();
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
            'order_number' => $parameters['tradeno'],
            'third_order' => $parameters['orderid'],
            'third_money' => $parameters['money'] * 100,
            'error' => '',
        ];
        if($parameters['status'] == 'success'){
            $config = Recharge::getThirdConfig($parameters['tradeno']);
            $tenpaySign = strtolower($parameters['sign']);
            $signPars ="attach={$parameters['attach']}&mchNo={$parameters['mchNo']}&money={$parameters['money']}&orderid={$parameters['orderid']}&paytype={$parameters['paytype']}&status={$parameters['status']}&tradeno={$parameters['tradeno']}&key={$config['pub_key']}";
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
