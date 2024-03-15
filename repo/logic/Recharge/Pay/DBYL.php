<?php
//namespace Las\Pay;
//use Las\BASES;
//use Las\Utils\Client;
//use phpDocumentor\Reflection\Types\Array_;

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 东北银联
 * @author chuanqi
 */

class DBYL extends BASES {

    static function instantiation(){
        return new DBYL();
    }
    //与第三方交互
    public function start(){
        $this->initParam();
        //$this->basePost();
        $this->parseRE();
    }

    //组装数组
    public function initParam(){

        $this->parameter = array(
            //基本参数
            'mcht_no'=>$this->partnerID,
            'trade_no' => $this->orderID,
            'notify_url'=> $this->notifyUrl,
            'totalAmount' => $this->money,
            'subject'=> 'kehu4399',
        );

        $this->parameter['sign'] = $this->sytMd5($this->parameter);
        $json = json_encode($this->parameter);
        //print_r($json);die;
        $this->curlPost($json);
    }

    //迅游post
    function curlPost($referer=null){
        $ch=curl_init();
        curl_setopt($ch,CURLOPT_URL,$this->payUrl);

        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$referer);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($referer)
        ));
        $response = curl_exec($ch);
        $this->curlError = curl_error($ch);
        $this->re = $response;
    }

    //md加密方式
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
        return $sign;
    }


    public function parseRE(){
        $re = json_decode($this->re,true);

        if($re['status'] == '0' ){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $url=$re['qrCode'];
            $table_change = array('HTTPS'=>'https');
            $this->return['str'] = strtr($url,$table_change);
        }else{
            $this->return['code'] =  5;
            $this->return['msg'] = 'dbyl:'.isset($re['message'])?$re['message']:'系统故障';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }


    public  function returnVerify($pieces) {

        $res = [
            'status' => 0,
            'order_number' => $pieces['transactionId'],
            'third_order'  => $pieces['sysNo'],
            'third_money'  => $pieces['totalAmount'],
            'error' => ''
        ];

        $sign = $pieces['sign'];

        $config = Recharge::getThirdConfig($pieces['transactionId']);

        ksort($pieces);
        $string='';
        foreach ($pieces as $key=>$value){
            if($value !='' && $value !=null && $key !='sign'){
                $string=$string.$key.'='.$value.'&';
            }
        }
        $string=$string.'key='. $config['pub_key'];
        $mySign=strtoupper(md5($string));

        if($sign == $mySign){
            $res['status']  = 1;
        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }
        return  $res;
    }
}
