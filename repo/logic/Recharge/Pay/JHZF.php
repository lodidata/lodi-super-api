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
 * 聚合支付
 * Class JHZF
 * @package Logic\Recharge\Pay
 */
class JHZF extends BASES
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
            'out_trade_no' => $this->orderID,
            'money' => sprintf("%.2f", $this->money/100),
            'name'=> 'VIP会员',
            'notify_url'=> $this->notifyUrl,
            'return_url'=>$this->returnUrl
        );

        $this->parameter['sign'] = $this->sytMd5($this->parameter);
        $this->parameter['sign_type'] = 'MD5';
    }

    public function sytMd5($pieces){
        ksort($pieces);

        $string='';
        foreach ($pieces as $keys=>$value){
            if($value !='' && $value!=null){
                $string.=$keys.'='.$value.'&';
            }
        }
        $string=rtrim($string,'&'). $this->key;
        $sign=strtolower(md5($string));
        //$string=$string.'&sign='.$sign;
        return $sign;
    }



    //返回参数
    public function parseRE(){
//        var_dump($this->re);die;
        $this->return['str'] = 'http://pay-api.zypaymet.com/go.php'.'?method=HTML&html='.urlencode(base64_encode($this->re));

//        $this->parameter = $this->arrayToURL();
//        $this->parameter .= '&url=' . $this->payUrl ;
//        $this->parameter .= '&method=POST';
//        $str = $this->jumpURL . '?' . $this->parameter;
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];
//        $this->return['str'] = $str;
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status' => 1,
            'order_number' => $parameters['out_trade_no'],
            'third_order' => $parameters['trade_no'],
            'third_money' => $parameters['money']*100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['out_trade_no']);
        if($parameters['code']!=1){
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

    public function returnVail($parameters,$sign,$config){
        unset($parameters['sign_type']);
        unset($parameters['sign']);
        ksort($parameters);
        $string='';
        foreach ($parameters as $key=>$value){
            if($value !='' && $value !=null && $key !='sign'){
                $string.=$key.'='.$value.'&';
            }
        }
        $string=rtrim($string,'&'). $config['key'];

        $mySign=strtolower(md5($string));
        return $sign == $mySign;
    }
}