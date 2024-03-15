<?php

/**
 *   霸业支付
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class BYZF extends BASES
{

    /**
     * 生命周期
     */
    public function start()
    {

        $this->initParam();
        $this->parseRE();
    }

    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $this->returnUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/return_url.php';
        $pub_params = [
            'merchant' => (string)$this->partnerID,
            'channel_code' => (string)$this->payType, //bank_data
            'ordernum' => (string)$this->orderID,
            'notifyurl' => $this->notifyUrl,
            'apply_date' => date('Y-m-d H:i:s', time()),
            'amount'   => sprintf('%.2f', $this->money / 100)
        ];
        $pub_params['sign'] = $this->_sign($pub_params, $this->key);
        $this->parameter = $pub_params;
    }

    /**
     * 组装前端数据,输出结果，使用go.php方法，自动post到支付
     */
    public function parseRE()
    {
        foreach ($this->parameter as &$item) {
            $item = urlencode($item);
        }
        //组装前端Form数据
        $this->parameter = $this->arrayToURL();
        $this->parameter .= '&url=' . urlencode($this->payUrl);
        $this->parameter .= '&method=POST';

        //响应结果
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL . '?' . $this->parameter;

        $this->re = $this->return;


    }

    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($parameters)
    {
        $res = [
            'order_number' => $parameters['channelOrderId'],
            'third_order' => $parameters['sysOrderId'],
            'third_money' => $parameters['amount'] * 100,
            'status' => 0,
            'error' => ''
        ];
        $config = Recharge::getThirdConfig($parameters['channelOrderId']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if ($parameters['returncode'] != '1') {
            $res['status'] = 0;
            $res['error'] = '支付订单状态未成功';
            return $res;
        }
        $result = $this->returnVail($parameters, $config['key']);
        if (!$result) {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
            return $res;
        }
        $res['status'] = 1;
        return $res;
    }

    /**
     * 生成sign
     */
    private function _sign($params, $tkey)
    {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            if ($v != '' && $v != null && $k != 'sign') {
                $string = $string . $k . '=' . $v . '&';
            }
        }
        $sign_str = $string . 'key=' . $tkey;
        $sign = md5($sign_str);
        return strtoupper($sign);
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
        $s_params = [
            "channelId" => $params["channelId"], // 商户ID
            "channelOrderId" =>  $params["channelOrderId"], // 订单号
            "amount" =>  $params["amount"], // 交易金额
            "datetime" =>  $params["datetime"], // 交易时间
            "sysOrderId" => $params["sysOrderId"], // 支付流水号
            "returncode" => $params["returncode"] //订单状态
        ];

        $sign = $this->_sign($s_params, $tkey);
        if ($sign != $return_sign) {
            return false;
        }
        return true;
    }
}
