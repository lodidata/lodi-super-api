<?php
/**
 * 聚宝盆支付
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;
use Utils\Utils;

class JBPZF extends BASES
{
    /**
     * 生命周期
     */
    public function start()
    {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $this->parameter = [
            'merchant_code' => $this->partnerID,
            'orderid' => $this->orderID,
            'channel' => $this->payType,
            'amount' => $this->money/100,
            'timestamp' => time(),
            'reference' => $this->orderID,
            'notifyurl' => $this->notifyUrl,
            'httpurl' => $this->returnUrl,
        ];

        //秘钥存入 token字段中
        $this->parameter['sign'] = $this->_sign($this->parameter, $this->key, true);
    }

    /**
     * 生成sign
     */
    private function _sign($pieces, $tkey, $isLower)
    {
        ksort($pieces);
        $string = [];
        foreach ($pieces as $key => $val) {
            if ($key != 'sign') {
                $string[] = $key . '=' . $val;
            }
        }
        $params = join('&', $string);

        $sign_str = $params . '&' . $tkey;
        $sign = md5($isLower ? strtolower($sign_str) : $sign_str);
        return $sign;
    }

    /**
     * 组装前端数据,输出结果
     */
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['status']) && $re['status'] == '1') {
            $re = $re['data'];
            $this->return['code'] = 0;//code为空代表是OK
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['return'];
        } else {
            $this->return['code'] = 65;  //非0代付 错误
            $this->return['msg'] = 'JBPZF:';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($data)
    {
        global $app;
        $data = $app->getContainer()->request->getParams();
        if (isset($data['s'])) {
            unset($data['s']);
        }

        if (!isset($data['amount']) || !isset($data['order_id']) || !isset($data['status'])) {
            $res = [
                'status' => 0, //为０表示 各种原因导致该订单不能上分（）
                'error' => '未知错误',
            ];
            return $res;
        }

        $res = [
            'status' => 0, //为０表示 各种原因导致该订单不能上分（）
            'order_number' => $data['order_id'],
            'third_order' => $data['order_id'],
            'third_money' => $data['amount'] * 100,
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($res['order_number']);

        //无此订单
        if (!$config) {
            $res['error'] = '订单号不存在';
            return $res;
        }

        if ($data['status'] != 'PAID') {
            $res['error'] = '未支付';
            return $res;
        }

        if ($data['sign'] != $this->_sign($data, $config['key'], false)) {
            $res['error'] = '签名验证失败';
            return $res;
        }
        $res['status'] = 1;
        return $res;
    }
}
