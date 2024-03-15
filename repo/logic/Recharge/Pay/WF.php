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
 * 万付支付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class WF extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->dopay();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $money = $this->money / 100;
        $this->parameter = array(
            'money' => "$money",
            'trade_no' => $this->orderID,
            'product_id' => '123',
            'product_name' =>'GOODS',
            'pay_type' =>$this->data['bank_data'],
            'notify_url' =>$this->notifyUrl,
            'return_url' =>$this->returnUrl,
            'mch_id' => $this->partnerID,
            'time' => strtotime('now')
        );
        $this->parameter['sign'] = $this->sytMd5New($this->parameter,$this->key);
    }

    // http_build_query  URL格式化传输数据
    public function dopay($referer = null){
        $data = json_encode($this->parameter,true);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->payUrl);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8', 'Content-Length:' . strlen($data)]);
        $res = curl_exec($curl);
        $this->curlError = curl_error($curl);
        curl_close($curl);
        $this->re = $res;
        return $res;
    }

    public function sytMd5New($pieces,$key)
    {
        ksort($pieces);
        $md5str = "";
        foreach ($pieces as $keyVal => $val) {
            $md5str = $md5str . $keyVal . $val ;
        }
        $md5str = $md5str  .$key ;
        $sign = strtolower(md5($md5str));
        return $sign;
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        //网银与快捷
        if (isset($re['code']) && $re['code'] == '1') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'success';
            $this->return['way'] = 'jump';
            $this->return['str'] = $re['data']['url'];
        } else {
            $msg = $re['msg'] ?? "未知异常";
            $this->return['code'] = 886;
            $this->return['msg'] = 'WF:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

    //签名验证
    public function returnVerify($pieces)
    {

        $res = [
            'status' => 1,
            'order_number' => $pieces['trade_no'],
            'third_order' =>'',
            'third_money' => $pieces['money'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['trade_no']);
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