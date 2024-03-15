<?php
/**
 * 万卡支付
 * 万咖支付
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class WANKA extends BASES
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
        $pub_params = [
            'mch_id' => $this->partnerID,          
            'order_no' =>$this->orderID,
            'amount' => $this->money/100,
            'subject' => 'changre',
            'paytype' => (string)$this->payType,
            'return_url' => $this->notifyUrl,
            'order_time'=>date("YmdHis")
        ];
        $pub_params['sign'] = $this->createSign($pub_params,$this->key);
        $this->parameter=$pub_params;
    }

    /**
     * 组装前端数据,输出结果
     */
    public function parseRE()
    {
        $result = json_decode($this->re, true);
        if ($result['err_code']=="0") {
            $this->return['code']   = 0;
            $this->return['msg']    = $result['err_msg'];
            $this->return['way']    = $this->data['return_type'];;
            $this->return['str']    = $result['data']['qrcode'];
        }else{
            $this->return['code'] = $result['err_code'];
            $this->return['msg']  = 'WKPAY: ' . $result['err_msg'];
            $this->return['way'] = '';
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
            'status' => 1,
            'order_number' => $data['order_no'],
            'third_order' => $data['tran_id'],
            'third_money' => $data['amount']*100,
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($data['order_no']);


        if (! $config) {
            $res['status'] = 0;
            $res['error'] = '订单号不存在';

            return $res;
        }
        //校验sign
        $signPars = $data['order_no'].$data['amount'].$data['tran_id'].$config['pub_key'];
        $sign = md5($signPars);
        if ($sign != $data['sign']) {
            $res['status'] = 0;
            $res['error'] = '签名验证失败';
            return $res;
        }

        return $res;
    }

     //签名函数
    public function createSign($parameters,$key)
    {
        $signPars = $parameters['order_no'].$parameters['amount'].$key.$parameters['mch_id'];
        $sign = md5($signPars);
        return $sign;
    }

}