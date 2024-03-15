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
use Psr\Http\Message\ServerRequestInterface;

/**
 * 现在支付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class XZ extends BASES
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
            'mch_id' => $this->partnerID,
            'trade_type' => $this->data['bank_data'],
            'nonce' => mt_rand(time(),time()+rand()),
            'user_id' => '1',
            'timestamp' => time(),
            'subject' => 'GOODS',
            'out_trade_no' => $this->orderID,
            'total_fee' => $this->money,
            'spbill_create_ip' => $this->data['client_ip'],
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl
        );
        if($this->data['bank_data']=='GATEWAY'){
            $this->parameter['issuer_id']=$this->data['bank_code'];
        }
        $this->parameter['sign']=urlencode($this->getMd5Sign($this->parameter,$this->key));
    }


    function getMd5Sign($params, $signKey)//签名方式
    {
        ksort($params);
        $data = "";
        foreach ($params as $key => $value) {
            if ($value === '' || $value == null) {
                continue;
            }
            $data .= $key . '=' . $value . '&';
        }
        $sign = md5($data . 'key=' . $signKey);
        return strtoupper($sign);
    }


    //返回参数
    public function parseRE()
    {
        $res = json_decode($this->re,true);

        if(isset($res['result_code']) && $res['result_code']== 'SUCCESS') {
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = $res['pay_url'];
        }else {
            $this->return['code'] = 886;
            $this->return['msg']  = 'XZ:'.$res['result_msg'];
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = '';
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {

        $res = [
            'status' => 1,
            'order_number' => $pieces['out_trade_no'],
            'third_order' => isset($pieces['trade_no']) ?? '',
            'third_money' => $pieces['total_fee'] ,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['out_trade_no']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }

        $result=$this->verifySign($pieces,$config['pub_key']);

//        $result=$this->getMd5Sign($pieces,$config['key']);
        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }

        return $res;
    }

    /**
     * 验证签名
     * @param array $params 数据
     * @param string $platformPublicKey 公钥
     * @return bool
     */
    function verifySign($params, $platformPublicKey)
    {
        ksort($params);
        $data = "";
        foreach ($params as $key => $value) {
            if ($value === '' || $value == null || $key == 'sign') {
                continue;
            }
            $data .= $key . '=' . $value . '&';
        }

        $data = preg_replace("/&$/", '', $data);

        if (!is_string($params['sign']) || !is_string($params['sign'])) {
            return false;
        }
        $publicKey = openssl_get_publickey($platformPublicKey);

        return (bool)openssl_verify(md5($data),base64_decode($params['sign']), $publicKey,OPENSSL_ALGO_SHA256);
    }


}