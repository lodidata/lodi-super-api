<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * COKA酷卡/库卡
 */

class COKA extends BASES
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
            "mchId"    => $this->partnerID,
            "appId"    => $this->data['app_id'] ,
            "productId"    => $this->payType,
            "currency"       => 'cny',
            "mchOrderNo"       => $this->orderID,
            "amount"      => $this->money,
            "clientIp" => $this->data['client_ip'],
            "notifyUrl"     =>$this->notifyUrl,
            "subject"      => 'GOOds',
            "body"      => time(),
        ];
        $this->parameter['sign'] = $this->currentMd5('key=');
    }

    /**
     * 
     */
    public function parseRE()
    {
        $re = json_decode($this->re,true);
        if (isset($re['payParams']['payUrl']) && $re['payParams']['payUrl']) {
            $this->return['code']   = 0;
            $this->return['msg']    = 'success';
            $this->return['way']    = $this->data['return_type'];;
            $this->return['str']    = $re['payParams']['payUrl'];
        }else{
            $this->return['code'] =9999;
            $this->return['msg']  = 'COKA: ' . $re['retMsg'] ?? "支付失败";
            $this->return['way'] = '';
            $this->return['str'] = '';
        }
        
    }

    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($parameters)
    {
        $res = [
            'status' => 1,
            'order_number' => $parameters['mchOrderNo'],
            'third_order' => $parameters['payOrderId'],
            'third_money' => $parameters['amount'],
            'error' => '',
        ];

        $config = Recharge::getThirdConfig($parameters['mchOrderNo']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '订单号不存在';
            return $res;
        }

        if (!in_array($parameters['status'],[2,3] )) {
            $res['status'] = 0;
            $res['error'] = '未支付';
            return $res;
        }
        //校验sign
        $sign = $parameters['sign'];
        unset($parameters['sign']);
        $this->parameter = $parameters;
        $this->key = $config['key'];
        if (strtolower($sign) != $this->currentMd5('key=')) {
            $res['status'] = 0;
            $res['error']  = '验签失败！';
            return $res;
        }
        return $res;
    }

}