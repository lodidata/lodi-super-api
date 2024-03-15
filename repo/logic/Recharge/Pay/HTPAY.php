<?php
/**
 * 鸿途支付
 * Created by PhpStorm.
 * User: shuidong
 * Date: 2018/12/22
 * Time: 10:41
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

class HTPAY extends BASES
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
        $this->returnUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/return_url.php';
        $this->parameter = [
            'appid' => $this->partnerID,
            'version' => 'v1.1',
            'amount' => sprintf("%.2f", $this->money/100),
            'pay_type' => $this->payType,
            'out_trade_no' => (string)$this->orderID,
            'out_uid' => md5(Client::getIp()),
            'success_url' => $this->returnUrl,
            'callback_url' => $this->notifyUrl,
            'error_url' => $this->returnUrl,

        ];
        $this->parameter['sign'] = $this->_sign($this->parameter, $this->key);
    }


    /**
     * 组装前端数据,输出结果
     */
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if ($re && $re['code'] == 200) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            if($this->data['return_type']=='code'){
                $this->return['str'] = 'https://www.kuaizhan.com/common/encode-png?large=true&data='.$re['data']['qrcode'];
            }else{
                $this->return['str'] = $re['url'];
            }

        } else {
            $this->return['code'] = 0;
            $this->return['msg'] = '鸿途支付:' . $re['msg'];
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
            'order_number' => $parameters['out_trade_no'],
            'third_order' => $parameters['out_trade_no'],
            'third_money' => $parameters['amount_true']*100
        ];
        $config = Recharge::getThirdConfig($parameters['out_trade_no']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        $result = $this->returnVail($parameters, $config['key']);
        if ($parameters['callbacks']!='CODE_SUCCESS') {
            $res['status'] = 0;
            $res['error'] = '支付失败！';
            return $res;
        }

        if ($result) {
            $res['status'] = 1;
            $res['error'] = '';
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
            return $res;
        }
        return $res;
    }


    /**
     * 生成sign
     */
    private function _sign($params, $key)
    {
        $params = array_filter($params);
        ksort($params);

        $string = '';
        foreach ($params as $keyVal => $param) {
            $param=urldecode($param);
            $string .= $keyVal . '=' . $param . '&';
        }
        $string .= 'key=' . $key;
        $sign = strtoupper(md5($string));
        return $sign;
    }

    /**
     * 回调后进行业务判断
     * @param $params
     * @param $conf
     * @param $reques_params
     * @return bool
     */
    public function returnVail($params, $tkey)
    {
        $return_sign = $params['sign'];
        unset($params['sign']);
        $sign = $this->_sign($params, $tkey);
        if ($sign != $return_sign ) {
            return false;
        }
        return true;
    }
}