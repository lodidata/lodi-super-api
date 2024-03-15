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
 * 狼币支付
 */
class LBPAY extends BASES
{

    protected $params;

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
            'customer_id' => $this->partnerID,
            'account' => $this->data['app_id'],
            'nonce_str' => mt_rand(time(), time() + rand()),
            'customer_order_no' => $this->orderID,
            'product_intro' => '',
            'order_amount' => $this->money,
            'payment_method' => $this->payType,
            'place_ip' => $this->data['client_ip'],
            'place_area' => '',
            'trade_type' => 1,
            'notify_url' => $this->notifyUrl,
            'expire_min' => '',
            'attach' => ''
        );

        $this->params['sign'] = urlencode($this->sytMd5New($this->params, $this->key));
        $this->parameter = $this->params;
    }

    public function sytMd5New($pieces, $key)
    {
        $md5str = "";
        foreach ($pieces as $keyVal => $val) {
            $md5str = $md5str . $keyVal . "=" . $val . "&";
        }
        $md5str = rtrim($md5str, "&");
        $md5str = $md5str . $key;
        $sign = strtoupper(md5($md5str));
        return $sign;
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['code']) && $re['code'] == '200') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re["data"]["pay_url"];
        } else {
            $msg = $re['message'] ?? "未知异常";
            $this->return['code'] = 886;
            $this->return['msg'] = '狼币支付:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

    //签名验证
    public function returnVerify($pieces)
    {
        global $app;
        $pieces = $app->getContainer()->request->getParams();
        unset($pieces['s']);

        $data = $pieces['data'];
        if (!$data) {
            // 非法数据
            return false;
        }

        $res = [
            'status' => 0,
            'order_number' => $data['customer_order_no'],
            'third_order' => $data['platform_order_no'],
            'third_money' => $data['order_amount'],
            'error' => '',
        ];

        //支付状态,0-订单生成,1-支付中,2-支付成功,3-业务处理完成
        if ($data['result_code'] != 'SUCCESS') {
            $res['status'] = 0;
            $res['error'] = '未支付';
            return $res;
        }

        $config = Recharge::getThirdConfig($res['order_number']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }

        $sign = $data['sign'];
        unset($data['sign']);
        if (self::retrunVail($sign, $data, $config['key'])) {
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