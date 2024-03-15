<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 融信丰
 * @author viva
 */


class YXF extends BASES {

    static function instantiation(){
        return new YXF();
    }

    //与第三方交互
    public function start(){
        $this->initParam();
        if (!$this->payUrl)
            $this->payUrl = 'https://payapi.3vpay.net/pay';
        $this->basePost();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter['partnerId'] = $this->partnerID;
        $this->parameter['channelOrderId'] = $this->orderID;
        $this->parameter['timeStamp'] = time();
        $this->parameter['body'] = 'Goods';
        $this->parameter['totalFee'] = $this->money/100;
        $this->parameter['payType'] = $this->data['bank_data'];
        $this->parameter['notifyUrl'] = $this->notifyUrl;
        $this->parameter['returnUrl'] = $this->returnUrl;
        $str = "partnerId={$this->partnerID}&timeStamp={$this->parameter['timeStamp']}&totalFee={$this->parameter['totalFee']}&key={$this->key}";
        $this->parameter['sign'] = strtoupper(md5($str));
    }

    public function parseRE(){
        $re = json_decode($this->re,true);
        if($re['return_code'] == '0000'){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['payParam']['pay_info'];
        }else{
            $this->return['code'] = 61;
            $this->return['msg'] = 'YXF:'.$re['return_msg'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }


    /**
     * @param $parameters
     * @return array
     */
    public  function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => $parameters['channelOrderId'],
            'third_order' => $parameters['orderId'],
            'third_money' => $parameters['totalFee']*100,
            'error' => '',
        ];
        if($parameters['retcode'] == '0000'){
            $config = Recharge::getThirdConfig($parameters['channelOrderId']);
            $sign_status = $this->signVerify($parameters,$config['pub_key']);
            if($sign_status){
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
    public  function signVerify($p,$key) {
        $signPars="channelOrderId={$p['channelOrderId']}&key={$key}&orderId={$p['orderId']}&timeStamp={$p['timeStamp']}&totalFee={$p['totalFee']}";

        return strtoupper($p['sign']) == strtoupper(md5($signPars));
    }

}
