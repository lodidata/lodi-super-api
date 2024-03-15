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
use Utils\Client;

class DY extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->post();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = [
            //基本参数
            'service' => $this->data['bank_data'],
            'mch_id' => $this->partnerID,
            'out_trade_no' => $this->orderID,
            'total_fee' => $this->money,
            'mch_create_ip' => $this->data['client_ip'],
            'notify_url' => $this->notifyUrl,
        ];
        $this->parameter['sign'] = $this->currentMd5('key=');
//        $this->parameter = $this->toXml($this->parameter);
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re,true);
        if(isset($re['result_code']) && $re['result_code']== 0) {
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = $re['code_url'];
        }else {
            $this->return['code'] = $re['result_code'];
            $this->return['msg']  = 'DY:'.$re['result_msg'];
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = $re['code_url'];
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['out_trade_no'],
            'third_order' => $pieces['transaction_id'],
            'third_money' => 0,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['out_trade_no']);
        if(!$config){
            $res['status']=0;
            $res['error']='订单不存在';
        }
        if(self::returnVail($pieces,$config) && $pieces['result_code'] == 0){
            $res['status']=1;
        }else{
            $res['status']=0;
            $res['error']='验签失败！';
        }
        return $res;
    }

    public function returnVail($pieces,$config){
        ksort($pieces);
        $string = '';
        foreach ($pieces as $key => $value) {
            if ($value != '' && $value != null && $key != 'sign') {
                $string = $string . $key . '=' . $value . '&';
            }
        }
        $string = $string . 'key=' . $config['pub_key'];

        $mySign = strtoupper(md5($string));
        $pieces['sign'] = strtoupper($pieces['sign']);

        return $pieces['sign'] == $mySign;
    }
}