<?php

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 欢快支付
 */
class HKPAY extends BASES
{

    //与第三方交互
    public function start()
    {

        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //组装数组
    public function initParam()
    {
        $pub_params = [
            'coopId' => $this->partnerID,
            'outOrderNo' => $this->orderID,
            'subject' => 'Goods',
            'money' => $this->money,
            'notifyUrl' => $this->notifyUrl,
            'pathType' => $this->payType,

        ];
        $pub_params['sign'] = $this->_sign($pub_params, $this->key);
        $this->parameter = $pub_params;
    }

    /**
     * 生成sign
     */
    private function _sign($pieces, $tKey)
    {
        ksort($pieces);
        $string = [];
        foreach ($pieces as $key => $val) {
            if ($key != 'sign' && $val != '' && $val != null) {
                $string[] = $key . '=' . $val;
            }
        }
        $params = join('&', $string);
        $sign = $params . $tKey;
        return md5($sign);
    }

    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['code']) && $re['code'] == 0) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['payurl'];
//            $this->return['str'] = "httt://www.baidu.com/test";

        } else {
            $msg = $re['msg'];
            $this->return['code'] = 53;
            $this->return['msg'] = '欢快支付:' . $msg ?? '第三方未知错误';
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }

    }

    /**
     * 回调验签
     */
    public function returnVerify($input)
    {

        if (!isset($input['outOrderNo']))
        {
            // 非法数据
            return false;
        }

        $res = [
            'status' => 0,
            'order_number' => $input['outOrderNo'],
            'third_order' => $input['outOrderNo'],
            'third_money' => $input['money'],
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($input['outOrderNo']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '订单号不存在';
            return $res;
        }
        if ($input['code'] != 0) {
            $res['status'] = 0;
            $res['error'] = '未支付';
            return $res;
        }


        $result = $this->returnVail($input, $config['key']);
        if (!$result) {
            $res['status'] = 0;
            $res['error'] = '签名验证失败';
            return $res;
        }
        $res['status'] = 1;
        return $res;
    }

    public function returnVail($params,$tkey)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        $signResult = $this->_sign($params,$tkey);

        return $sign==$signResult;
    }
}
