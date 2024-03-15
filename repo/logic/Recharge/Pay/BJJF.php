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
 * 百聚计费
 * Class GT
 * @package Logic\Recharge\Pay
 */
class BJJF extends BASES
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
            'pid' => $this->partnerID,
            'type' => $this->data['bank_data'],
            'out_trade_no' => $this->orderID,
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl,
            'subject' => 'GOODS',
            'total_fee' => $this->money / 100,
            'site_name' => 'CP'
        );
        $this->parameter['sign'] = $this->getMd5Sign($this->parameter, $this->key);
        $this->parameter['sign_type'] = 'MD5';
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
        $data = substr($data, 0, strlen($data) - 1);
        $str = $data . $signKey;
        $sign = md5($str);
        return $sign;
    }


    //返回参数
    public function parseRE()
    {
//        $this->parameter = $this->arrayToURL();
//        $this->parameter .= '&url=' . $this->payUrl ;
//        $this->parameter .= '&method=POST';
//        $str = $this->jumpURL . '?' . $this->parameter;
//        $this->return['code'] = 0;
//        $this->return['msg'] = 'SUCCESS';
//        $this->return['way'] = $this->data['return_type'];
//        $this->return['str'] = $str;
        $str = $this->re;
        $val = array();
        preg_match('/location.href=\"([^\"]+)/', $str, $val);

        if (isset($val[1]) && $val) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $val[1];
        } else {
            $this->return['code'] = 886;
            $this->return['msg'] = 'BJJF:' . "第三方未知错误！";
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {

        $res = [
            'status' => 1,
            'order_number' => $pieces['out_trade_no'],
            'third_order' => isset($pieces['trade_no']) ?? '',
            'third_money' => $pieces['money'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['out_trade_no']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        $sign = $pieces['sign'];

        unset($pieces['sign']);
        unset($pieces['sign_type']);
        $signStr = $this->getMd5Sign($pieces, $config['pub_key']);

//        $result=$this->getMd5Sign($pieces,$config['key']);
        if ($signStr == $sign) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }

        return $res;
    }


}