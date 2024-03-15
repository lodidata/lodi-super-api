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
 * 企业支付宝
 * Class QYZFB
 * @package Logic\Recharge\Pay
 */
class QYZFB extends BASES
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
        $money = $this->money / 100;
        $this->parameter = array(
            'merchant_id' => $this->partnerID,
            'content_type'=>'form',
            'pay_type' => $this->data['bank_data'],
            'out_trade_no' => $this->orderID,
            'amount' => sprintf("%.2f", $money),
            'robin' => '1',
//            'robin' => '2',
//            'keyId' => '2C0CE3D5E416460B',
            'notify_url' => $this->notifyUrl,
            'return_url'=>$this->returnUrl
        );
        $this->parameter['sign'] = $this->sytMd5New($this->parameter,$this->key);
        ksort($this->parameter);
    }

    public function sytMd5New($pieces,$key)
    {
        unset($pieces['robin']);
        unset($pieces['keyId']);
        unset($pieces['return_url']);
        ksort($pieces);
        $md5str = "";
        foreach ($pieces as $keyVal => $val) {
            $md5str = $md5str . $keyVal . "=" . $val . "&";
        }
        $md5str=substr($md5str,0,strlen($md5str)-1). $key;
        $sign = strtoupper(md5($md5str));
        return $sign;
    }

    //返回参数
    public function parseRE()
    {

        $this->parameter = $this->arrayToURL();
        $this->parameter .= '&url=' . $this->payUrl ;
        $this->parameter .= '&method=POST';
        $str = $this->jumpURL . '?' . $this->parameter;
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];
        $this->return['str'] = $str;


    }

    //签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['out_trade_no'],
            'third_order' => $pieces['trade_no'],
            'third_money' => $pieces['order_amount'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['out_trade_no']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if (self::retrunVail($pieces['sign'],$pieces, $config)) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($sign,$pieces, $config)
    {
        unset($pieces['sign']);
        return $sign == $this->sytMd5New($pieces,$config['key']);
    }




}