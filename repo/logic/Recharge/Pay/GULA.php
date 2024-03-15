<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;


/**
 * 咕啦支付
 * @author lzx
 */


class GULA extends BASES {

    static function instantiation(){
        return new GULA();
    }
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
            'appid' => $this->key,
            'custNo' => $this->partnerID,
            'attach' => $this->orderID,
            'model' => '00',
            'money' => $this->money/100,
            'callBackUrl'=> $this->notifyUrl,
        );
    }

    public function parseRE(){
        $re = json_decode($this->re,true);
        if($re['code'] == 1){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['pay_url'];
        }else{
            $this->return['code'] = 886;
            $this->return['msg'] = 'GULA:'.$re['msg'];
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
            'order_number' => $parameters['attach'],
            'third_order' => $parameters['trade_no'],
            'third_money' => $parameters['money'] * 100,
            'error' => '',
        ];
        //该支付不需要验签
        if($parameters['pay_status'] == 'success'){
            $res['status'] = 1;
        }else
            $res['error'] = '该订单未支付';

        return $res;
    }
}
