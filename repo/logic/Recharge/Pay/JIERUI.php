<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class JIERUI extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $data = [
            //基本参数
            'action' => $this->payType,
            'txnamt' => $this->money,
            'merid' => $this->partnerID,
            'orderid' => $this->orderID,
            'backurl' => $this->notifyUrl,
        ];
        //格式化json字符串
        $jsonData=json_encode($data);
        //输出Base64字符串
        $base64Data = base64_encode($jsonData);
        //拼接待签名字符
        $signData = $base64Data.$this->key;
        //签名
        $sign = md5($signData);
        //拼接请求参数
        $this->parameter['req'] = urlencode($base64Data);
        $this->parameter['sign'] = $sign;
    }

    //返回参数
    public function parseRE()
    {
        if ($this->showType != 'code') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $this->payUrl . '?' . $this->arrayToURL();
        }else {
            $this->re = $this->curl_jierui($this->payUrl,$this->parameter);
            $res = json_decode(base64_decode($this->re['resp']),true);
            if($res['respcode'] == '00') {
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->showType;
                $this->return['str'] = $res['formaction'];
            }else {
                $this->return['code'] = 886;
                $this->return['msg'] = 'JIERUI:'.$res['respmsg'];
                $this->return['way'] = $this->showType;
                $this->return['str'] = '';
            }
        }
    }

    //签名验证
    public function returnVerify($para)
    {
        $pieces = json_decode(base64_decode($para['resp']),true);
        $res = [
            'status' => 0,
            'order_number' => $pieces['orderid'],
            'third_order' => $pieces['queryid'],
            'third_money' => $pieces['txnamt'],
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['orderid']);
        if(!$config){
            $res['status']=0;
            $res['error']='订单不存在';
        }

        if(in_array($pieces['resultcode'],['0000','1002']) && $this->returnVail($para['resp'],$config,$para['sign'])){
            $res['status']=1;
        }else{
            $res['status']=0;
            $res['error']='验签失败！';
        }
        return $res;
    }

    public function returnVail($str,$config,$sign){
        $signData = $str.$config['key'];
        return strtolower(md5($signData)) == strtolower($sign);
    }

    public function curl_jierui($url,$requestData){
        $ch = curl_init();//打开
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$requestData);
        $response  = curl_exec($ch);
        curl_close($ch);//关闭
        $result = json_decode($response,true);
        return $result;
    }
}