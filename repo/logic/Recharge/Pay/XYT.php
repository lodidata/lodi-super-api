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

/**
 * 讯游通
 * Class XYT
 * @package Logic\Recharge\Pay
 */
class XYT extends BASES
{
    //与第三方交互
    public function start(){
        $this->initParam();
        $this->parseRE();
    }

    //初始化参数
    public function initParam(){
        $this->parameter = array(
            //基本参数
            'payKey'=>$this->partnerID,
            'orderPrice' => $this->money/100,
            'outTradeNo' => $this->orderID,
            'orderTime' => Date('YmdHis'),
            'productName' => '-',
            'orderIp'=>'127.0.0.1',
            'returnUrl'=>$this->returnUrl,
            'notifyUrl' => $this->notifyUrl,
            'productType'=>$this->data['bank_data'],
            'remark'=>'-'
        );
        if($this->showType == 'unionpay'){
            $this->parameter['bankCode'] = $_REQUEST['bankCode'];
            $this->parameter['bankAccountType'] = 'PRIVATE_DEBIT_ACCOUNT';
        }
        $string = $this->sytMd5($this->parameter);
        $this->basePost($string);
    }


    public function sytMd5($pieces)
    {
        ksort($pieces);
        $string='';
        foreach ($pieces as $keys=>$value){
            if($value !='' && $value!=null){
                $string=$string.$keys.'='.$value.'&';
            }
        }
        $string=$string.'paySecret='. $this->key;
        $sign=strtoupper(md5($string));
        $string=$string.'&sign='.$sign;
        return $string;
    }


    //返回参数
    public function parseRE(){
        $re = json_decode($this->re,true);
        if($re['resultCode'] == '0000' ){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['payMessage'];
        }else{
            $this->return['code'] = $re['resultCode'] ?? 5;
            $this->return['msg'] = 'XYT:'.$re['errMsg'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status' => 1,
            'order_number' => $parameters['order_no'],
            'third_order' => $parameters['trade_no'],
            'third_money' => 0,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['order_no']);

        if(!$config){
            $res['status']=0;
            $res['error']='未有该订单';
        }
        if($parameters['trade_status']!='SUCCESS'){
            $res['status']=0;
            $res['error']='第三方请求失败！';
        }else{
            $result=$this->returnVail($parameters,$config);
            if($result){
                $res['status']=1;
            }else{
                $res['status']=0;
                $res['error']='验签失败！';
            }
        }
        return $res;
    }

    public function returnVail($pieces,$config){
        ksort($pieces);
        $string='';
        foreach ($pieces as $key=>$value){
            if($value !='' && $value !=null && $key !='sign'){
                $string=$string.$key.'='.$value.'&';
            }
        }
        $string=$string.'paySecret='. $config['key'];
        $mySign=strtoupper(md5($string));
        return $pieces['sign'] == $mySign;
    }
}