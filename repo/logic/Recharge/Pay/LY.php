<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 *
 * 乐游
 * @author Lion
 */
class LY extends BASES {

    static function instantiation(){
        return new LY();
    }

    //与第三方交互
    public function start(){
        $this->initParam();       // 数据初始化
        $this->basePost();        // POST请求
        $this->parseRE();         // 处理结果
    }

    //组装数组
    public function initParam(){

        $this->parameter['fxid']        = $this->partnerID;           //商务号
        $this->parameter['fxddh']       = $this->orderID;             //商户订单号
        $this->parameter['fxdesc']      = time();                     //商品名称
        $this->parameter['fxfee']       = $this->money/100;           //支付金额
        $this->parameter['fxattch']     = $this->data['bank_data'];   //附加信息
        $this->parameter['fxnotifyurl'] = $this->notifyUrl;           //异步通知地址
        $this->parameter['fxbackurl']   = $this->returnUrl;           //同步通知地址
        $this->parameter['fxpay']       = $this->data['bank_data'];   //请求类型 【支付宝wap：zfbwap】
        $this->parameter['fxsign']      = $this->sign();              //签名
        $this->parameter['fxip']        = Client::getClientIp();      //支付用户IP地址

    }

    //生成签名  【md5(商务号+商户订单号+支付金额+异步通知地址+商户秘钥)】
    public function sign(){

        $str = $this->partnerID.$this->orderID.$this->money/100 .$this->notifyUrl.$this->key;
        return md5($str);

    }

    //处理结果
    public function parseRE(){

        $re = json_decode($this->re,true);
        if($re['status'] == 1 && $re['payurl']){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['payurl'];
        }else{
            $this->return['code'] = 886;
            $this->return['msg'] = 'LY:'.$re['error'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

    //回调数据校验
    /*
     * 签名【md5(订单状态+商务号+商户订单号+支付金额+商户秘钥)】
     * */
    public function returnVerify($parameters = array()) {

        $res = [
            'status' => 0,
            'order_number' => $parameters['fxddh'],
            'third_order' => $parameters['fxorder'] ?? '',
            'third_money' => $parameters['fxfee']*100,
            'error' => ''
        ];

        $config = Recharge::getThirdConfig($parameters['fxddh']);

        $str =  $parameters['fxstatus'].$parameters['fxid'].$parameters['fxddh'].$parameters['fxfee'].$config['key'];
        $sign = strtolower(md5($str));
        $tenpaySign = strtolower($parameters['fxsign']);
        
        if($sign == $tenpaySign){
            $res['status']  = 1;
        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }
        return  $res;
    }

}
