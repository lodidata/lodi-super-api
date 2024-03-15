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
 * 汇富通
 * Class GT
 * @package Logic\Recharge\Pay
 */
class HFT extends BASES
{

    protected  $params;
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
        $this->params = array(
            'mchId' => $this->partnerID,
            'appId' => $this->data['app_id'],
            'productId'=>$this->data['bank_data'],
            'mchOrderNo' => $this->orderID,
            'currency' => 'cny',
            'amount' => $this->money,
            'clientIp ' => Client::getIp(),
            'notifyUrl'=>$this->notifyUrl,
            'subject'=>'GOODS',
            'body'=>'GOODS'
        );
        if($this->params['productId']=='8015'){
            $this->params['extra']="{returnType:'URL'}";
        }
        $this->params['sign'] = urlencode($this->sytMd5New($this->params,$this->key));

        $this->parameter['params']=json_encode($this->params);
    }

    public function sytMd5New($pieces,$key)
    {
        ksort($pieces);
        $md5str = "";
        foreach ($pieces as $keyVal => $val) {
            $md5str = $md5str . $keyVal . "=" . $val . "&";
        }
        $md5str = $md5str . "key=" .$key ;
        $sign = strtoupper(md5($md5str));
        return $sign;
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['retCode']) && $re['retCode'] == 'SUCCESS') {
            $payParams=$re['payParams'];
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $payParams['payUrl'];
        } else {
            $msg = $re['retMsg'] ?? "未知异常";
            $this->return['code'] = 886;
            $this->return['msg'] = 'HTF:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

    //签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['mchOrderNo'],
            'third_order' => $pieces['payOrderId'],
            'third_money' => $pieces['amount'],
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['mchOrderNo']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        $sign = $pieces['sign'];
        unset($pieces['sign']);
        if (self::retrunVail($sign, $pieces,$config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($sign,$pieces,$key)
    {
        return $sign == $this->sytMd5New($pieces,$key);
    }

}