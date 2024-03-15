<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/10/15
 * Time: 15:44
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class HESHUN extends BASES
{

    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->doPay();
    }

    //组装数组
    public function initParam()
    {

        $this->parameter = [
            'pay_memberid'=> $this->partnerID,
            'pay_orderid'=> $this->orderID,
            'pay_amount'=> $this->money/100,
            'pay_applydate'=> date("Y-m-d H:i:s"),
            'pay_notifyurl'=> $this->notifyUrl,
            'pay_callbackurl'=> $this->returnUrl,
            'pay_bankcode'=> $this->payType,
        ];

        $this->parameter['pay_md5sign'] = urlencode($this->getSign($this->parameter,$this->key));
        $this->parameter['pay_applydate'] = urlencode($this->parameter['pay_applydate']);
        $this->parameter['pay_notifyurl'] = urlencode($this->parameter['pay_notifyurl']);
        $this->parameter['pay_callbackurl'] = urlencode($this->parameter['pay_callbackurl']);
        $this->parameter['pay_attach'] = "1234|456";
        $this->parameter['pay_productname'] = 'goods';

    }


    protected function doPay()
    {
        $this->parameter['method'] = 'POST';
        $this->parameter['url'] = urlencode($this->payUrl);
        $this->return['code'] = 0;
        $this->return['msg'] = '';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL.'?'.$this->arrayToURL();
    }

    public function getSign($data, $key)
    {
        ksort($data);
        $md5str = "";
        foreach ($data as $k => $val) {
            $md5str = $md5str . $k . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $key));

        return $sign;

    }


    public function returnVerify($params)
    {

        $res = [
            'status' => 0,
            'order_number' => $params['orderid'],//outorderno
            'third_order' => $params['transaction_id'],
            'third_money' => $params['amount'] * 100,
            'error' => ''
        ];


        $config = Recharge::getThirdConfig($params['orderid']);
        $returnSign = $params['sign'];
        unset($params['sign']);
        unset($params['attach']);
        $mysign = $this->getSign($params, $config['key']);
        if ($returnSign == $mysign) {
            if ($params['returncode'] == '00') {//支付成功
                $res['status'] = 1;
            } else { //支付失败
                $res['error'] = '支付失败';
                echo 'fail';
            }
        } else {
            $res['error'] = '该订单验签不通过或已完成';
        }

        return $res;

    }

}