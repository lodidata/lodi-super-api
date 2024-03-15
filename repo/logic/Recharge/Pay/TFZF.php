<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 腾飞支付
 * Class TFZF
 * @package Logic\Recharge\Pay
 */
class TFZF extends BASES
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
        $money = $this->money / 100;
        $this->parameter = array(
            'mch_id' => $this->partnerID,
            'out_trade_no' => $this->orderID,
            'pay_type' => $this->payType,
            'order_amount' => sprintf("%.2f", $money),
            'notify_url' => $this->notifyUrl,
            'user_code'=>  md5(rand(1000,9999)),
        );
        $this->parameter['sign'] = $this->sytMd5New($this->parameter,$this->key);
        ksort($this->parameter);
    }

    public function sytMd5New($pieces,$key)
    {
        unset($pieces['sign']);

        ksort($pieces);

        $string='';
        foreach ($pieces as $keys=>$value){
            if($value !='' && $value!=null){
                $string.=$keys.'='.$value.'&';
            }
        }
        $string=rtrim($string,'&'). $key;

        $sign=md5($string);
        return $sign;
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re,true);
        if($re['success'] == 0) {
            $this->return['code'] = 0;
            $this->return['msg'] = '';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['pay_url'];
        }else {
            $this->return['code'] = 39;
            $this->return['msg'] = 'TFZF:'.$re['msg'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
        global $app;
        $pieces = $app->getContainer()->request->getParams();
        unset($pieces['s']);
        if(!isset($pieces['out_order_no'])){
            $res['status'] = 0;
            $res['error'] = '非法数据';
            return $res;
        }

        $res = [
            'status' => 1,
            'order_number' => $pieces['out_order_no'],
            'third_order' => $pieces['mch_id'],
            'third_money' => $pieces['pay_amount'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['out_order_no']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }

        if($pieces['order_state']!=1){
            $res['status']=0;
            $res['error']='失败！';
            return $res;
        }

        if (self::retrunVail($pieces['sign'],$pieces, $config)) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($sign,$pieces, $config)
    {
        unset($pieces['sign']);
        return $sign == $this->sytMd5New($pieces,$config['key']);
    }




}