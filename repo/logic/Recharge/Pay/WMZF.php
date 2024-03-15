<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/19
 * Time: 10:09
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class WMZF extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = array(
            'service' => $this->data['bank_data'],
            'version' => '1.0',
            'charset' => 'UTF-8',
            'sign_type' => 'MD5',
            'merchant_id'=>$this->partnerID,
            'out_trade_no'=>$this->orderID,
            'goods_desc'=>'GOODS',
            'total_amount'=>$this->money/100,
            'nonce_str'=>$this->getRandstr(),
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl
        );
        $this->parameter['sign'] = strtoupper($this->currentMd5('key='));
    }

    public function getRandstr()
    {
        return strtolower(md5(uniqid(mt_rand(), true)));
    }


    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re,true);
        if($re['status'] == 0 && $re['result_code'] == 0){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data->return_type;
            $this->return['str'] = $re['pay_info'];
        }else{
            $msg = $re['message'] ?? $re['err_msg'];
            $this->return['code'] = 61;
            $this->return['msg'] = 'WMZF:'.$msg;
            $this->return['way'] = $this->data->return_type;
            $this->return['str'] = '';
        }
    }

    //签名验证
    //不需要验签
    public function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => $parameters['orderId'],
            'third_order' => $parameters['ksPayOrderId'],
            'third_money' => $parameters['transAmount']*100,
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($parameters['orderid']);

        $signPars = '';
        $tmpSign = $parameters['sign'];
        unset($parameters['sign']);
        foreach($parameters as $k => $v) {
            //  字符串0  和 0 全过滤了，所以加上
            if(!empty($v) || $v === 0  || $v === "0" ) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .= 'key='.$config[''];

        if (strtoupper($tmpSign) == strtoupper(md5($signPars))){
            $res['status'] = 1;
        }else{
            $res['error'] = '验签失败！';
        }
        return $res;

    }
}