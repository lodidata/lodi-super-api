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
 * 全付通支付
 * Class QFT
 * @package Logic\Recharge\Pay
 */
class QFT extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = array(
            //基本参数
            'mch_id' => $this->partnerID,//必填项，商户号
            'service' => $this->data['bank_data'],//接口类型
            'notify_url' => $this->notifyUrl,
            'version' => '2.0',//固定值：
            'out_trade_no' => $this->orderID,
            'body' => $this->desc ?? 'goods-',
            'total_fee' => $this->money,
            'mch_create_ip' => '127.0.0.1',  //订单生成的机器 IP
            'time_start' => Date('YmdHis'),
            'nonce_str' => mt_rand(time(), time() + rand()), //随机字符串，不长于 32 位
            'sign_type' => 'RSA_1_256'
        );
        $this->parameter['sign'] = $this->createRSASign($this->parameter);
        $this->parameter = $this->toXml($this->parameter);

    }

    function createRSASign($parameters)
    {
        $signPars = "";
        ksort($parameters);
        foreach ($parameters as $k => $v) {
            if ("" != $v && "sign" != $k) {
                $signPars .= $k . "=" . $v . "&";
            }
        }

        $signPars = substr($signPars, 0, strlen($signPars) - 1);
        $res = openssl_get_privatekey($this->key);

        openssl_sign($signPars, $sign, $res, OPENSSL_ALGO_SHA256);

        openssl_free_key($res);
        $sign = base64_encode($sign);
        return $sign;
    }


    //返回参数
    public function parseRE()
    {
        $re = $this->parseXML($this->re);
        if($re['status'] == 0 && $re['result_code'] == 0){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['code_url'];
        }else{
            $msg = $re['err_msg'] ?? $re['message'];
            $this->return['code'] = 101;
            $this->return['msg'] = 'QFT:'.$msg;
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($parameters)
    {
        $res=[
            'status' => 1,
            'order_number' => $parameters['out_trade_no'],
            'third_order' => $parameters['out_transaction_id'],
            'third_money' => $parameters['total_fee'],
            'error' => '',
        ];

        $config=Recharge::getThirdConfig($parameters['out_trade_no']);
        if(!$config){
            $res['status']=0;
            $res['error']='未有该订单';
        }
        if(self::returnVail($parameters,$config)){
            $res['status']=1;
        }else{
            $res['status']=0;
            $res['error']='验签失败！';
        }
        return $res;
    }

    public function returnVail($parameters,$config){
        $signPars = "";
        ksort($parameters);
        foreach($parameters as $k => $v) {
            if("sign" != $k && "" != $v) {
                $signPars .= $k . "=" . $v . "&";
            }
        }

        $signPars = substr($signPars, 0, strlen($signPars) - 1);
        $res = openssl_get_publickey($config['pub_key']);
        $result = (bool)openssl_verify($signPars, base64_decode($parameters["sign"]), $res,OPENSSL_ALGO_SHA256);
        openssl_free_key($res);
        return $result;
    }
}