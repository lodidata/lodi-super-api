<?php
/**
 * 多宝支付
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class DBZF extends BASES
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
        $this->money = $this->money / 100;
        $this->parameter = [
            'bid' => $this->partnerID,
            'money' => $this->money,
            'order_sn' => $this->orderID,
            'pay_type' => $this->payType,
            'data_type' =>  $this->data['return_type'] == 'code' ? 'json' : '',
            'notify_url' => $this->notifyUrl,
        ];
        $tmp = [
            'key' => $this->key,
            'bid' => $this->partnerID,
            'money' => $this->money,
            'order_sn' => $this->orderID,
            'notify_url' => $this->notifyUrl,
            'iv' => $this->data['token']
        ];
        //秘钥存入 token字段中
        $this->parameter['sign'] = md5(implode('|',$tmp));
    }


    /**
     * 组装前端数据,输出结果
     */
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['code']) && $re['code'] == '100') {
            $this->return['code'] = 0;//code为空代表是OK
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['data']['url'] ?? $re['data']['qrcode'];
        } else {
            $this->return['code'] = 65;  //非0代付 错误
            $this->return['msg'] = 'DBZF:' . $re['msg'] ?? '请求超时';
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
        $res = [
            'status' => 0, //为０表示 各种原因导致该订单不能上分（）
            'order_number' => $data['order_sn'],
            'third_order' => $data['sys_order_sn'],
            'third_money' => $data['pay_money'] * 100,
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($data['order_sn']);

        //无此订单
        if (!$config) {
            $res['error'] = '订单号不存在';

            return $res;
        }
        $tmp = [
            'key' => $config['key'],
            'pay_time' => $data['pay_time'],
            'money' => $data['money'],
            'pay_money' => $data['pay_money'],
            'order_sn' => $data['order_sn'],
            'sys_order_sn' => $data['sys_order_sn'],
            'iv' => $config['token']
        ];
        //校验sign

        if ($data['sign'] != md5(implode('|',$tmp))) {
            $res['error'] = '签名验证失败';
            return $res;
        }
        $res['status'] = 1;

        return $res;
    }


}