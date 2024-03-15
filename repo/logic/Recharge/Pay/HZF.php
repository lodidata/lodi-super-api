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

class HZF extends BASES
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
            'merId'=>$this->partnerID,
            'appId'=>$this->data['app_id'],
            'merOrderId' => $this->orderID,
            'payerId'=> $this->orderID,
            'reqFee' => $this->money,
            'itemName'=> 'kehu4399',
            'notifyUrl'=> $this->notifyUrl,
            'clientIp'=> Client::getIp(),
        );

        $this->parameter['signValue'] = $this->sytMd5($this->parameter);
    }

    public function sytMd5($pieces){
        ksort($pieces);
        $string='';
        foreach ($pieces as $keys=>$value){
            if($value !='' && $value!=null){
                $string=$string.$keys.'='.$value.'&';
            }
        }
        $string=$string.'key='. $this->key;
        $sign=strtoupper(md5($string));
        //$string=$string.'&sign='.$sign;
        return $sign;
    }


    //返回参数
    public function parseRE(){
        $re = json_decode($this->re,true);
        if($re['retCode'] == 0 ){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['data']['payUrl'];
        }else{
            $this->return['code'] = $re['retCode'] ?? 5;
            $this->return['msg'] = 'hzf:'.$re['message'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status' => 1,
            'order_number' => $parameters['merOrderId'],
            'third_order' => $parameters['orderId'],
            'third_money' => $parameters['amount'],
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['merOrderId']);
        if(!$config){
            $res['status']=0;
            $res['error']='没有该订单';
        }
        $result=self::returnVail($parameters,$config);
        if($result){
            $res['status']=1;
        }else{
            $res['status']=0;
            $res['error']='验签失败！';
        }
        return $res;
    }

    public function returnVail($parameters,$config){
        ksort($parameters);
        $string='';
        foreach ($parameters as $key=>$value){
            if($value !='' && $value !=null && $key !='signValue'){
                $string.=$key.'='.$value.'&';
            }
        }
        $string=$string.'key='. $config['pub_key'];

        $mySign=strtoupper(md5($string));
        return $parameters['signValue'] == $mySign;
    }
}