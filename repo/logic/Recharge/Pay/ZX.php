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
 * 众鑫支付
 * Class ZX
 * @package Logic\Recharge\Pay
 */
class ZX extends BASES
{
    protected $param;

    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = array(
            'appid' => $this->partnerID,
            'uid' => md5($this->data['client_ip']),
            'order' => $this->orderID,
            'gid' => 0,
            'price' => $this->money/100,
            'mode' => $this->data['bank_data']
        );
        $this->parameter['sn'] = $this->sytMd5New($this->parameter, $this->key);
        $this->parameter['notifyUrl'] = $this->notifyUrl;
    }



    public function sytMd5New($pieces, $key)
    {
        ksort($pieces);
        $md5str = "";
        foreach ($pieces as $keyVal => $val) {
            $md5str = $md5str . $keyVal . "=" . $val ;
        }
        $md5str = $md5str . "secret=" . $key;
        return md5($md5str);
    }

    //返回参数
    public function parseRE()
    {
        $this->return['str'] = $this->payUrl.'?'.$this->arrayToURL();
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];
    }

    //签名验证
    public function returnVerify($result)
    {

        $res = [
            'status' => 1,
            'order_number' => $result['order'],
            'third_order' => $result['transaction_id'],
            'third_money' => $result['amount']*100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($result['order']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }

        $sign = $result['sn'];
        unset($result['sn']);
        if (self::retrunVail($sign, $result, $config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($sign, $pieces, $key)
    {
        return $sign == $this->sytMd5New($pieces, $key);
    }

}