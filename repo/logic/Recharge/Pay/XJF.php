<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 鑫玖付支付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class XJF extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->post();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $money = $this->money / 100;
        $this->parameter = array(
            'account_id' => $this->partnerID,
//            'content_type' => $this->showType == 'code' ? 'text' : 'json',
            'content_type' =>'text',
            'thoroughfare' => $this->payType,
            'type' => $this->data['action'],
            'out_trade_no' => $this->orderID,
            'robin' => 2,
            'keyId' => $this->data['app_secret'],
            'amount' => sprintf('%.2f', $money),
            'callback_url' => $this->notifyUrl,
            'success_url' => $this->returnUrl,
            'error_url' => $this->returnUrl,
        );
        $this->parameter['sign'] = $this->signMd5($this->key,$this->parameter);
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        //网银与快捷
        if (isset($re['data']['qrcode_url2']) && $re['data']['qrcode_url2']) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['data']['qrcode_url2'];
        } else {
            $msg = $re['msg'] ?? $this->re;
            $this->return['code'] = 886;
            $this->return['msg'] = 'XJF:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }

    /**
     * 签名算法
     * @param unknown $key_id S_KEY（商户KEY）
     * @param unknown $array 例子：$array = array('amount'=>'1.00','out_trade_no'=>'2018123645787452');
     * @return string
     */
    public function signMd5($key_id, $array)
    {
        $data = md5(sprintf("%.2f", $array['amount']) . $array['out_trade_no']);
        $key[] ="";
        $box[] ="";
        $cipher = '';
        $pwd_length = strlen($key_id);
        $data_length = strlen($data);
        for ($i = 0; $i < 256; $i++)
        {
            $key[$i] = ord($key_id[$i % $pwd_length]);
            $box[$i] = $i;
        }
        for ($j = $i = 0; $i < 256; $i++)
        {
            $j = ($j + $box[$i] + $key[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $data_length; $i++)
        {
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

    //签名验证
    public function returnVerify($result)
    {
        $res = [
            'status' => 0,
            'order_number' => $result['out_trade_no'],
            'third_order' => $result['trade_no'],
            'third_money' => $result['amount'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($result['out_trade_no']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单或已完成';
        }elseif($result['status'] != 'success') {
            $res['status'] = 0;
            $res['error'] = '未支付';
        }else {
            if(strtolower($result['sign']) == strtolower($this->signMd5($config['key'],$result))) {
                $res['status'] = 1;
            }else {
                $res['status'] = 0;
                $res['error'] = '验签失败';
            }
        }
        return $res;
    }

}