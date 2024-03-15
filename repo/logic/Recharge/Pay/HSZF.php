<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/11/8
 * Time: 11:17
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class HSZF extends BASES
{
    //与第三方交互
    public function start()
    {
        $money = $this->money;

        if (is_int((int)$money) / 100) {
            $this->initParam();
            $this->basePost();
            $this->parseRE();
        } else {
            $this->return['code'] = 63;
            $this->return['msg'] = 'HSZF:' . '充值金额格式不正确，必须为正整数！';
            $this->return['way'] = '';
            $this->return['str'] = '';
            return;
        }
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = [
            'account_id'   => $this->partnerID,
            'thoroughfare' => $this->data['bank_data'],
            'out_trade_no' => $this->orderID,  //订单号
            'robin'        => 2,
            'amount'       => $this->money / 100,  //单位元
            'callback_url' => $this->notifyUrl,
            'success_url'  => $this->returnUrl,
            "error_url"    => $this->returnUrl,
            'content_type' => 'json',
        ];

        $signMd5 = ["amount" => $this->money / 100, "out_trade_no" => $this->orderID];
        $this->parameter['sign'] = $this->getSign($signMd5, $this->key);
    }

    public function getSign($array, $key_id)
    {
        $data = md5(number_format($array['amount'], 2) . $array['out_trade_no']);
        $key[] = "";
        $box[] = "";
        $cipher = "";
        $pwd_length = strlen($key_id);
        $data_length = strlen($data);
        for ($i = 0; $i < 256; $i++) {
            $key[$i] = ord($key_id[$i % $pwd_length]);
            $box[$i] = $i;
        }

        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $key[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $data_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;

            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;

            $k = $box[(($box[$a] + $box[$j]) % 256)];
            $cipher .= chr(ord($data[$i]) ^ $k);
        }

        return md5($cipher);
    }

    public function getQrCode($orderid)
    {

        $orderid = intval($orderid);
        if (!empty($orderid)) {
            //$url = "http://ry.qzqingchao.com/gateway/alipaycl/getOrder.do?content_type=json&id=" . $orderid;
            $url = 'http://ry.qzqingchao.com/gateway/pay/automaticAlipay.do?content_type=json&id=' . $orderid;
            try {
                $chs = curl_init();
                curl_setopt($chs, CURLOPT_URL, $url);
                curl_setopt($chs, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($chs, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($chs, CURLOPT_RETURNTRANSFER, true);
                $data = curl_exec($chs);
                curl_close($chs);

                $this->re = $data;
            } catch (\Exception $e) {

            }
        }
    }


    //返回参数
    public function parseRE()
    {
        $res = json_decode($this->re, true);

        if ($res['code'] == 200) {
            $res['data']['qrcode'] = $this->getQrCode($res['data']['order_id']);
            $re = json_decode($this->re, true);
            if (!empty($re['data']['qrcode'])) {
                $this->return['code'] = 0;
                $this->return['msg'] = 'success';
                $this->return['way'] = $this->showType;
                $this->return['str'] = $re['data']['qrcode'];
            } else {
                $this->return['code'] = $re['code'] ?? 1;
                $this->return['msg'] = 'HSZF:' . $re['msg'] ?? '获取二维码错误';
                $this->return['way'] = $this->showType;
                $this->return['str'] = '';
            }
        } else {
            $this->return['code'] = $re['code'] ?? 1;
            $this->return['msg'] = 'HSZF:' . $re['msg'] ?? '请求错误';
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($parameters)
    {
        $res = [
            'status'       => $parameters['status'],
            'order_number' => $parameters['out_trade_no'],
            'third_order'  => $parameters['trade_no'],
            'third_money'  => $parameters['amount'] * 100,
            'error'        => '',
        ];

        $config = Recharge::getThirdConfig($parameters['out_trade_no']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '未有该订单';

            return $res;
        }

        $strsign = $this->getSign(
            ['amount' => $res['third_money'] / 100, 'out_trade_no' => $res['order_number']], $config['key']
        );

        $result = $strsign == $parameters['sign'];

        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }

        return $res;
    }
}