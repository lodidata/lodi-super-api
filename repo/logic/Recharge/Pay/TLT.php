<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 通联支付
 * @author chuanqi
 */
class TLT extends BASES
{

    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    //组装数组
    public function initParam()
    {


        $this->parameter = array(
            //基本参数
            'mch_id' => $this->partnerID,
            'trade_type' => $this->data['bank_data'],
            'nonce' => time(),
            'timestamp' => time(),
            'subject' => 'goods',
            'out_trade_no' => $this->orderID,
            'total_fee' => $this->money,
            'spbill_create_ip'=> $this->data['client_ip'],
            'notify_url' => $this->notifyUrl,
            'sign_type'=>'MD5'

        );

        $this->parameter['sign'] = $this->sytMd5($this->parameter);
        $json = json_encode($this->parameter);

        $this->curlPost($json);
    }


    function curlPost($referer = null)
    {


        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->payUrl);

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $referer);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($referer)
        ));
        $response = curl_exec($ch);

        $this->curlError = curl_error($ch);
        $this->re = $response;


    }


    //迅游通md加密方式
    public function sytMd5($pieces)
    {

        ksort($pieces);
        $string = '';
        foreach ($pieces as $keys => $value) {
            if ($value != '' && $value != null) {
                $string = $string . $keys . '=' . $value . '&';
            }
        }

        $string = $string . 'key=' . $this->key;

        $sign = strtoupper(md5($string));

        return $sign;
    }


    public function parseRE()
    {


        $re = json_decode($this->re,true);

        if (!isset($re['code'])) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $url = $re['pay_url'];
            $tableChange = ['HTTPS'=>'https'];
            $this->return['str'] = strtr($url, $tableChange);
            // $this->return['str'] = $re['pay_url'];
        } else {
            $this->return['code'] = 5;
            $this->return['msg'] = 'tlzf:' . $re['message'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    public  function returnVerify($parameters)
    {
        $res = [
            'status' => 0,
            'order_number' => $parameters['out_trade_no'],
            'third_order' => $parameters['trade_no'],
            'third_money' => $parameters['total_fee'],
            'error' => '',
        ];
        if($parameters['result_code'] == 'SUCCESS'){
            $config = Recharge::getThirdConfig($parameters['out_trade_no']);
            $sign_status = $this->signVerify($parameters,$parameters['sign'],$config['pub_key']);
            if($sign_status){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';

        return $res;
    }


    /**
     * @param $pieces
     * @param $sign
     * @param $cert
     * @return bool
     */
    public function signVerify($pieces, $sign, $cert){
        $signPars = "";
        ksort($pieces);
        foreach($pieces as $k => $v) {
            if("sign" != $k && "" != $v) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars = substr($signPars, 0, strlen($signPars) - 1);
        $res = openssl_get_publickey($cert);
        $result = (bool)openssl_verify(md5($signPars),base64_decode($sign), $res, OPENSSL_ALGO_SHA256);

        openssl_free_key($res);
        return $result;
    }


}
