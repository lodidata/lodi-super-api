<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * 来米支付
 * Class LM
 * @package Logic\Recharge\Pay
 */
class LM extends BASES
{
    //与第三方交互
    public function start(){
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam(){
        $this->parameter = array(
            //基本参数
            'mid'=>$this->partnerID,
            'payType'=>$this->data['bank_data'],
            'amount' => $this->money,
            'mchTradeNo' => $this->orderID,
            'notifyUrl'=> $this->notifyUrl,
            'returnUrl'=>$this->returnUrl,
            'nonce'=>mt_rand(time(),time()+rand()),
            'timestamp'=>strtotime('now'),
            'format'=>'json'
        );

        $this->parameter['sign'] = $this->sytMd5($this->parameter,$this->key);
    }

    public function sytMd5($pieces,$key){
        ksort($pieces);
        $string='';
        foreach ($pieces as $keys=>$value){
            if($value !='' && $value!=null){
                $string=$string.$keys.'='.urlencode($value).'&';
            }
        }
        $string=$string.'securityKey='. $key;
        $sign=strtoupper(md5($string));
        return $sign;
    }


    //返回参数
    public function parseRE(){
        $re = json_decode($this->re,true);
        if($re['code'] == 0 ){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['data']['payUrl'];
        }else{
            $this->return['code'] = 886;
            $this->return['msg'] = 'LM:'.$re['msg'] ?? '第三方异常';
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($param) {
        $res = [
            'status' => 1,
            'order_number' => $param['mchTradeNo'],
            'third_order' => $param['tradeNo'],
            'third_money' => $param['realAmount'],
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($param['mchTradeNo']);
        if(!$config){
            $res['status']=0;
            $res['error']='没有该订单';
        }
        $result= $this->sytMd5($param,$config['key']);
        if($result){
            $res['status']=1;
        }else{
            $res['status']=0;
            $res['error']='验签失败！'; 
        }
        return $res;
    }

}