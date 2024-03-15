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

class GT extends BASES
{
    //与第三方交互
    public function start(){
        $this->initParam();
    }

    //初始化参数
    public function initParam(){
        $this->parameter['partner'] = $this->partnerID;
        $this->parameter['banktype'] = $this->data['bank_data'];

        if($this->parameter['banktype'] == 'direct_pay'){
            $this->parameter['banktype'] = $_REQUEST['pay_code']??'ICBC';
        }

        $this->parameter['paymoney'] = $this->money/100;
        $this->parameter['ordernumber'] = $this->orderID;
        $this->parameter['callbackurl'] = $this->notifyUrl;

        $this->parameter['sign'] = $this->sytMd5($this->parameter);

        $url_param = http_build_query($this->parameter);
        $this->parseRE($this->payUrl.'?'.$url_param);
    }

    public function sytMd5($pieces)
    {
        $string='';
        foreach ($pieces as $keys=>$value){
            if($value !='' && $value!=null){
                $string=$string.$keys.'='.$value.'&';
            }
        }
        $string = substr($string,0,strlen($string)-1);
        $string=$string. $this->key;

        $string=md5($string);

        return $string;
    }


    //返回参数
    public function parseRE($url){
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $url;
    }

    //签名验证
    public function returnVerify($pieces) {
        $res = [
            'status' => 1,
            'order_number' => $pieces['ordernumber'],
            'third_order' => $pieces['sysnumber'],
            'third_money' => $pieces['paymoney']*100,
            'error' => '',
        ];
        $config=Recharge::getThirdConfig($pieces['ordernumber']);
        if(!$config){
            $res['status']=0;
            $res['error']='没有该订单';
        }
        if(self::retrunVail($pieces,$config)){
            $res['status']=1;
        }else{
            $res['status']=0;
            $res['error']='验签失败！';
        }
        return $res;
    }

    public function retrunVail($pieces,$config){
        $arr['partner'] = $pieces['partner'];
        $arr['ordernumber'] = $pieces['ordernumber'];
        $arr['orderstatus'] = $pieces['orderstatus'];
        $arr['paymoney'] = $pieces['paymoney'];
        $string='';
        foreach ($arr as $key=>$value){
            if($value !='' && $value !=null && $key !='sign' && $key!='sysnumber' && $key!='attach'){
                $string=$string.$key.'='.$value.'&';
            }
        }
        $string = substr($string,0,strlen($string)-1);

        $string=$string.$config['key'];

        $mySign=md5($string);
        return $pieces['sign'] == $mySign;
    }
}