<?php

namespace Las\Pay;
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * 聚融金融
 * @author Lion
 */


class JRJR extends BASES {

    static function instantiation(){
        return new JRJR();
    }
    //与第三方交互
    public function start(){
        $this->initParam();
     //   $this->get();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){

        $this->parameter['mchId']       = $this->partnerID;   // 商户编号
        $this->parameter['pay_type']    = $this->data['bank_data']; // 支付类型
        $this->parameter['amount']      = $this->money;       // 交易金额
        $this->parameter['time']        = time();             // 订单时间
        $this->parameter['tradeNo']     =  $this->orderID;    // 订单编号
        $this->parameter['return_url']  = $this->returnUrl;   // 前端通知地址
        $this->parameter['notify_url']  = $this->notifyUrl;   // 异步通知地址
        $this->parameter['card_type']   = '';                 // 1.储蓄卡，2信用卡 (pay_type为ylpay必传)
        $this->parameter['yl_pay_type'] = '';                 // B2C,B2B (pay_type为ylpay必传)
        $this->parameter['bank_name']   = '';                 // 如：工商银行 具体附录联系客服(pay_type为ylpay必传)
        $this->parameter['sign']        = $this->sign();      // md5签名串
        $this->parameter['extra']       = ''           ;      // 商户附加信息，可做扩展参数，只允许数字字母下划线，例如aa_bb.不允许带!@#+等，例如qq+v&c?!22
        $this->parameter['client_ip']   = Client::getIp();    // 用户真实IP


    }

    //md5(tradeNo+amount+pay_type+time+mchId+md5(key) )
    public function sign(){

        $str = $this->parameter['tradeNo'].$this->parameter['amount'].$this->parameter['pay_type'].$this->parameter['time'].$this->parameter['mchId'].md5($this->key);
        return md5($str);
    }
    public function parseRE(){

        $this->payUrl .= '?'.$this->arrayToURL();
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];
        $this->return['str'] =  $this->payUrl;
    }

    /**
     * 返回地址验证
     *
     * @param
     * @return boolean
     */
    public function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => $parameters['tradeNo'],
            'third_order' => $parameters['orderNo'],
            'third_money' => $parameters['amount'],
            'error' => ''
        ];

        $config = Recharge::getThirdConfig($parameters['tradeNo']);

       // 订单号码+系统订单+支付金额+商务号+支付类型+时间戳+md5(商户密钥)
        $str = $parameters['tradeNo'].$parameters['orderNo'].$parameters['amount'].$parameters['mchId'].$parameters['pay_type'].$parameters['time'].md5($config['key']);
        $sign = strtolower(md5($str));
        $tenpaySign = strtolower($parameters['sign']);
        if($sign == $tenpaySign){
            $res['status']  = 1;
        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }

        return  $res;

    }
}
