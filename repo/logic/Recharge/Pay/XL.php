<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 10:10
 */

namespace Logic\Recharge\Pay;


 use Logic\Recharge\Bases;
 use Logic\Recharge\Recharge;
 use Utils\Client;

 class XL extends Bases
{
    //与第三方交互
    public function start(){
        $this->initParam();
        if(!$this->payUrl)
            $this->payUrl = 'http://api.jnspay.com/api/pay/create_order';
        $this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam(){
        $this->parameter['mchId'] = $this->partnerID;
        $this->parameter['channelId'] = $this->data['bank_data'];  //支付方式为支付宝扫码
        $this->parameter['amount'] = $this->money;  //单位为分
        $this->parameter['mchOrderNo'] = $this->orderID;
        $this->parameter['notifyUrl'] = $this->notifyUrl;    //返回 success
        $this->parameter['subject'] = 'title';
        $this->parameter['body'] = 'desc';
        $this->parameter['clientIp']=Client::getIp();
        $this->parameter['sign'] = $this->currentMd5('key=');
    }


    //返回参数
    public function parseRE(){
        $re = json_decode($this->re,true);
        if(isset($re['payUrl'])){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['payUrl'];
        }else{
            $this->return['code'] = 49;
            $this->return['msg'] = 'XL:'.$re['msg'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status' => 1,
            'order_number' => $parameters['mchOrderNo'],
            'third_order' => $parameters['payOrderId'],
            'third_money' => 0,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['mchOrderNo']);

        if(!$config){
            $res['status']=0;
            $res['error']='未有该订单';
        }
        $result=$this->returnVail($parameters,$config);
        if($result){
            $res['status']=1;
        }else{
            $res['status']=0;
            $res['error']='验签失败！';
        }
        return $res;
    }

    public function returnVail($parameters,$config){
        $tenpaySign = strtolower($parameters['sign']);
        unset($parameters['sign']);
        $signPars ='';
        ksort($parameters);
        foreach($parameters as $k => $v) {
            //  字符串0  和 0 全过滤了，所以加上
            if(!empty($v) || $v == "" ) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .= 'key='.$config['config'];
        $sign = strtolower(md5($signPars));
        return $sign == $tenpaySign;
    }
}