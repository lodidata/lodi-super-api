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
 * 呱呱支付
 * Class GG
 * @package Logic\Recharge\Pay
 */
class GG extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        if (!$this->payUrl)
            $this->payUrl = 'http://api.guaguapay.net/pay/v1/order';
        $this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = array(
            'merNo' => $this->partnerID,                        #测试使用
            'appId' => $this->data['app_id'],
            'transType' => $this->data['bank_data'],
            'transAmt' => $this->money,
            'transTime' => date('YmdHis', time()),
            'orderNo' => $this->orderID,
        );
        $temp = array(
            'returnUrl' => $this->returnUrl,
            'notifyUrl' => $this->notifyUrl,
            'clientIp' => '127.0.0.1',
            'showQR' => 2,
        );
        $this->parameter['sign'] = md5(implode('|', $this->parameter) . '|' . $this->key);
        $this->parameter = array_merge($this->parameter, $temp);
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if ($re['status'] === '0') {
            if (!is_array($re['result']))
                $re['result'] = json_decode($re['result'], true);
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $re['result']['payUrl'];
        } else {
            $this->return['code'] = $re['status'];
            $this->return['msg'] = 'GG:' . $re['message'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($parameters)
    {
        $res = [
            'status' => 1,
            'order_number' => $parameters['orderNo'],
            'third_order' => $parameters['orderId'],
            'third_money' => 0,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['orderNo']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '未有该订单';
        }
        $result=$this->returnVail($parameters,$config);
        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function returnVail($input,$config)
    {
        //        MD5(orderId| orderNo| transaction_id | merNo | appId | transAmt | orderDate | respCode | timeEnd |KEY)
        $data['orderId'] = $input['orderId'];
        $data['orderNo'] = $input['orderNo'];
        $data['transaction_id'] = $input['transaction_id'];
        $data['merNo'] = $input['merNo'];
        $data['appId'] = $input['appId'];
        $data['transAmt'] = $input['transAmt'];
        $data['orderDate'] = $input['orderDate'];
        $data['respCode'] = $input['respCode'];
        $data['timeEnd'] = $input['timeEnd'];
        $data['key'] = $config['pub_key'];
        $sign = md5(implode('|', $data));
        return $sign == $input['sign'];
    }
}