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
 * CGP PAY
 * Class GT
 * @package Logic\Recharge\Pay
 */
class CGP extends BASES
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
        date_default_timezone_set('UTC');
        $money = $this->money / 100;
        $this->parameter = array(
            'MerchantOrderId' => $this->orderID,
            'OrderDescription' => '客户自订',
            'Attach' => '商户自订',
            'Amount' => $money * 100000000,
            'OrderBuildTimeSpan' => strtotime(date('Y-m-d H:i:s')),
            'OrderExpireTimeSpan' => 0,
            'Symbol' => 'CGP',
            'ReferUrl' => $this->returnUrl,
            'CallBackUrl' => $this->notifyUrl,
            'MerchantId' => $this->partnerID
        );
        $this->parameter['Sign'] = urlencode($this->sytMd5New($this->parameter));
//        print_r($this->parameter);die;
    }

    public function sytMd5New($pieces)
    {
        ksort($pieces);
        $md5str = "";
        foreach ($pieces as $keyVal => $val) {
            $md5str = $md5str . $val . ",";
        }
        $md5str = $md5str . $this->key;
        $sign = md5($md5str);
        return $sign;
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        //网银与快捷
        if ($re['ReturnCode'] == '0' && isset($re['ReturnCode'])) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['Qrcode'];
        } else {
            $msg = $re['RetrunMessage'] ?? $re['RetrunMessage'];
            $this->return['code'] = 886;
            $this->return['msg'] = 'CGP:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

    //签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['MerchantOrderId'],
            'third_order' => $pieces['OrderId'],
            'third_money' => ($pieces['PayAmount']/100000000) * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['MerchantOrderId']);
//        print_r($config);
//        print_r($config);exit;
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if (self::retrunVail($pieces, $config)) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($pieces)
    {
        return $pieces['sign'] == $this->sytMd5New($pieces);
    }

}