<?php
/**
 * 新轻松付支付
 * Created by PhpStorm.
 * Date: 2019/2/11
 * Time: 14:15
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class NEWQSF extends BASES
{

    /**
     * 生命周期
     */
    public function start()
    {
        $this->initParam();
        //$this->basePost();
        $this->parseRE();
    }

    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $pub_params = [
            'pay_memberid' => (string)$this->partnerID,
            'pay_bankcode' => (string)$this->payType, //bank_data
            'pay_amount' => $this->money / 100,
            'pay_orderid' => (string)$this->orderID,
            'pay_callbackurl' => $this->returnUrl,
            'pay_notifyurl' => $this->notifyUrl,
            'pay_applydate' => date('Y-m-d H:i:s', time())
        ];
        //var_dump($pub_params);exit();
        $pub_params['pay_md5sign'] = $this->_sign($pub_params, $this->key);
        $pub_params['pay_productname'] = $this->orderID;
        $this->parameter = $pub_params;
        //var_dump($this->parameter);exit();
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
        $res = [
            'order_number' => $parameters['orderid'],
            'third_order' => $parameters['transaction_id'],
            'third_money' => $parameters['amount'] * 100,
            'error' => '',
            'status' => 1,
        ];
        $config = Recharge::getThirdConfig($parameters['orderid']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if ($parameters['returncode'] != '00') {
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
        return $res;
    }

    /**
     * 生成sign
     */
    private function _sign($pieces, $tkey)
    {
        ksort($pieces);
        $string = [];
        foreach ($pieces as $key => $val) {
            $string[] = $key . '=' . $val;
        }
        $params = join('&', $string);
        $sign_str = $params . '&key=' . $tkey;
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
        unset($params['sign']);
        unset($params['attach']);
        $sign = $this->_sign($params, $tkey);
        if ($sign != $return_sign) {
            return false;
        }
        return true;
    }
}