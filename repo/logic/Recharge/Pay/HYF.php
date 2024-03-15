<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 畅汇
 * @author viva
 */

class HYF extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter = array(
            'mer_no'     => $this->partnerID,# 支付请求，固定值"Buy"
            'orderno'	 => $this->orderID,				 		#测试使用
            'cip'   => $this->data['client_ip'],
            'amount'	 => $this->money,
            'subject'     => 'Goods'.time(),
            'type'	 => $this->payType,
            'notify_url'	 => $this->notifyUrl,
            'return_url'	 => $this->returnUrl,
        );
        $this->parameter['sign'] = $this->currentMd5('key=');

    }

    public function parseRE(){
        $re = json_decode($this->re,true);
        if(isset($re['data']) && $re['data']){
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->showType;
            $this->return['str']  = $re['data'];
        }else{
            $re['msg'] = $re['msg'] ?? '未知错误';
            $this->return['code'] = 886;
            $this->return['msg']  = 'HYF:'.$re['msg'];
            $this->return['way']  = $this->showType;
            $this->return['str']  = '';
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
    public function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => $parameters['data']['orderno'] ?? '',
            'third_order'  => $parameters['data']['orderno'] ?? '',
            'third_money'  => $parameters['data']['amount'] ?? 0,
            'error'        => '',
        ];
        if ($parameters['status'] == 10000) {
            $config = Recharge::getThirdConfig($parameters['data']['orderno']);
            if ($config && $this->verifyData($parameters['data'], $config['pub_key'])) {
                $res['status'] = 1;
            } else
                $res['error'] = '该订单验签不通过或已完成';
        } else
            $res['error'] = '该订单未支付';
        return $res;
    }

    public function verifyData($data,$key) {
//        (mer_no={0}&amount={1}&orderno={2}&key={3}
        $tenpaySign = md5("mer_no={$data['mer_no']}&amount={$data['amount']}&orderno={$data['orderno']}&key={$key}");
        return strtolower($data['sign']) == strtolower($tenpaySign);
    }


}






