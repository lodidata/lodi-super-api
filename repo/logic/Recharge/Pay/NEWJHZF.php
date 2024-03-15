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

/**
 * 新聚合支付
 * Class NEWJHZF
 * @package Logic\Recharge\Pay
 */
class NEWJHZF extends BASES
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
            'pid'=>$this->partnerID,
            'type'=>$this->data['bank_data'],
            'orderno' => $this->orderID,
            'amount' => sprintf("%.2f", $this->money/100),
            'subject'=> 'GOODS',
            'notifyurl'=> $this->notifyUrl,
            'returnurl'=>$this->returnUrl
        );

        $this->parameter['sign'] = $this->sytMd5($this->parameter,$this->key);
    }

    public function sytMd5($pieces,$key){
        ksort($pieces);
        $string='';
        foreach ($pieces as $keys=>$value){
            if($value !='' && $value!=null){
                $string.=$keys.'='.$value.'&';
            }
        }
        $string=$string.'key='. $key;
        $sign=strtoupper(md5($string));
        return $sign;
    }



    //返回参数
    public function parseRE(){
        $re=json_encode($this->re,true);
        if(isset($re['status'])){
            $this->return['code'] = 0;
            $this->return['msg'] = 'NEWJHZF:'.$re['data']['err_msg'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] ='';
        }

        $this->return['str'] = 'http://pay-api.zypaymet.com/go.php' .'?method=HTML&html='.urlencode(base64_encode($this->re));
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = 'jump';
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status' => 1,
            'order_number' => $parameters['orderno'],
            'third_order' => $parameters['tran_id'],
            'third_money' => $parameters['amount']*100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['orderno']);
        if($parameters['State']!='SUCCESS'){
            $res['status']=0;
            $res['error']='失败！';
            return $res;
        }
        if(!$config){
            $res['status']=0;
            $res['error']='没有该订单';
        }

        $result=self::returnVail($parameters,$parameters['sign'],$config);
        if($result){
            $res['status']=1;
        }else{
            $res['status']=0;
            $res['error']='验签失败！';
        }
        return $res;
    }

    public function returnVail($param,$sign,$config){
        unset($param['sign']);
        $mySign=$this->sytMd5($param,$config['key']);
        return $sign == $mySign;
    }
}