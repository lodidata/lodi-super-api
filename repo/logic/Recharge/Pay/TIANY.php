<?php
/*
 * 天元支付
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/11
 * Time: 15:52
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;
use Utils\Utils;

class TIANY extends BASES
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
        $parameter = [
            'mchId' => $this->partnerID,
            'appId' => $this->data['app_id'],
            'mchOrderNo' => (string)$this->orderID,
            'amount' => $this->money,
            'notifyUrl' => $this->notifyUrl,
            'subject'=>(string)$this->orderID,
            'body' => (string)$this->orderID,
            'currency'=>'cny',
            'productId' =>$this->payType,
        ];
        $parameter['sign'] = $this->_sign($parameter,$this->key);
        $this->parameter = [
            'params' => json_encode($parameter),
        ];
    }


    /**
     * 组装前端数据,输出结果,
     */
    public function parseRE()
    {
        $re = json_decode($this->re,true);
        if(isset($re['retCode']) && $re['retCode'] == 'SUCCESS'){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['payUrl'] ?? $re['payParams']['payUrl'];
        }else{
            $this->return['code'] = $re['retCode'];
            $this->return['msg'] = 'TIANY:'.$re['retMsg'];
            $this->return['way'] = $this->data['return_type'];
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
            'order_number' => $parameters['mchOrderNo'],
            'third_order' => $parameters['payOrderId'],
            'third_money' => $parameters['amount'],
        ];
        $config = Recharge::getThirdConfig($parameters['mchOrderNo']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if ($parameters['status'] != 2){
            $res['status'] = 0;
            $res['error'] = '支付订单状态失败';
            return $res;
        }
        $result = $this->returnVail($parameters, $config['key']);
        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }
    /**
     * 生成sign
     */
    private function _sign($params, $tKey)
    {
        ksort($params);
        $string = "";
        foreach ($params as $key=>$val)
        {
            if($val){
                $string = $string?$string."&".$key."=".$val:$key."=".$val;
            }

        }
        $string = $string.'&key='.$tKey;
        $sign = md5($string);
        return strtoupper($sign);
    }

    public function returnVail($params,$tkey)
    {
        $return_sign = $params['sign'];
        unset($params['sign']);
        $sign = $this->_sign($params,$tkey);
        if ($sign != $return_sign){
            return false;
        }
        return true;
    }
}