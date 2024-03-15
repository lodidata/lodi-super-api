<?php
/**
 * 小蚂蚁支付
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class XMYZF extends BASES
{

    /**
     * 生命周期
     */
    public function start()
    {
        $this->initParam();
//        $this->basePost();
        $this->parseRE();
    }

    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $this->returnUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/return_url.php';
        if ($this->data['app_id'] == '1') {
            $this->money = $this->money - random_int(0, 20);
        }

        $pub_params = [
            'merchant' => (string)$this->partnerID,
            'channel_code' => (string)$this->payType, //bank_data
            'amount' => $this->money / 100.0,
            'ordernum' => (string)$this->orderID,
            'notifyurl' => $this->notifyUrl,
            'apply_date' => date('Y-m-d H:i:s', time()),
        ];
        //var_dump($pub_params);exit();
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
        $this->parameter = $this->arrayToURL();
        $this->parameter .= '&url=' . $this->payUrl;
        $this->parameter .= '&method=POST';

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL . '?' . $this->parameter;
    }

    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($parameters)
    {
        unset($parameters['s']);

        if (!isset($parameters['channelOrderId'])) {
            // 非法数据
            return false;
        }
        $res = [
            'order_number' => $parameters['channelOrderId'],
            'third_order' => $parameters['sysOrderId'],
            'third_money' => $parameters['amount'] * 100,
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
        if ($result) {
            $res['status'] = 1;
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
    private function _sign($pieces, $tkey)
    {
        ksort($pieces);
        $string = '';
        foreach ($pieces as $key => $val) {
            if ($val == 'null') continue;
            $string = $string . $key . '=' . $val . '&';
        }
        $string = $string . 'key=' . $tkey;
        $sign = strtoupper(md5($string));
//        print_r($sign);exit;
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
        if ($sign != $return_sign) {
            return false;
        }
        return true;
    }
}