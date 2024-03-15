<?php
/**
 * TC支付支付
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class TCZF extends BASES
{

    /**
     * 生命周期
     */
    public function start()
    {
        $this->initParam();
        $this->payJson2();
        $this->parseRE();
    }



    /**
     * 提交参数组装
     */
    public function initParam()
    {
        $this->returnUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/return_url.php';
        $pub_params = [
            'userName' => (string)$this->partnerID,
            'partnerOrderId' => (string)$this->orderID,
            'channelCode' => (string)$this->payType, //bank_data
            'amount' => $this->money,
            'description' => 'goods',
            'securityKey' => $this->pubKey,
            'notifyUrl' => $this->notifyUrl,
        ];
        $pub_params['sign'] = $this->_sign($pub_params, $this->key);
        $this->parameter = $pub_params;
    }

    /**
     * 组装前端数据,输出结果
     */
    public function parseRE()
    {

        $data = json_decode($this->re, true);
        if (isset($data['code']) && $data['code'] == '0') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $data['data']['data'];
        } else {
            $this->return['code'] = 23;
            $this->return['msg'] = $data['msg'];
            $this->return['way'] = $this->data['return_type'];
        }
    }

    /**
     * 回调验证处理
     * 获取接口返回数据，再次验证
     */
    public function returnVerify($parameters)
    {
        global $app;
        $parameters = $app->getContainer()->request->getParams();
        unset($parameters['s']);

        if (!isset($parameters['partnerOrderId']) || !isset($parameters['money'])) {
            return false;
        }

        $res = [
            'status' => 0,
            'order_number' => $parameters['partnerOrderId'],
            'third_order' => $parameters['orderId'],
            'third_money' => $parameters['money'],
        ];

        $config = Recharge::getThirdConfig($res['order_number']);

        if (!$config) {
            $res['error'] = '没有该订单';
            return $res;
        }


        if (!$this->returnVail($parameters,$config['key'])) {
            $res['error'] = '验签失败！';
            return $res;
        }
        $res['status'] = 1;
        return $res;
    }

    /**
     * 生成sign
     */
    private function _sign($params, $tKey)
    {
        ksort($params);
        unset($params['sign']);
        $signString = $params['userName'].$params['securityKey'].$params['partnerOrderId'].$params['description'].$params['amount'].$params['channelCode'].$params['notifyUrl'].$tKey;
        $s1 = strtoupper(md5($signString));
        $s2 = strtoupper(md5($s1));
        $s3 = strtoupper(md5($s2));
        return strtoupper($s3);
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
        $signStr =  $params['orderId'].$params['partnerOrderId'].$params['money'].$tkey;
        $s1 = strtoupper(md5($signStr));
        $s2 = strtoupper(md5($s1));
        $s3 = strtoupper(md5($s2));
        if ($s3 != $return_sign) {
            return false;
        }
        return true;
    }
}