<?php
/**
 * 盒付/盛世支付
 * Created by Hans.
 * Date: 2019/6/1
 */

namespace Logic\Recharge\Pay;


use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class BOXPAY extends BASES
{

    public function start()
    {
        $this->initParams();
        $this->parseRE();
    }

    private function initParams()
    {
        $this->returnUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/return_url.php';
        $pub_params = [
            'pay_memberid' => (string)$this->partnerID,
            'pay_orderid' => (string)$this->orderID,
            'pay_applydate' => date('Y-m-d H:i:s', time()),
            'pay_bankcode' => (string)$this->payType, //bank_data
            'pay_notifyurl' => $this->notifyUrl,
            'pay_callbackurl' => $this->returnUrl,
            'pay_amount' => $this->money / 100,
        ];
        $pub_params['pay_md5sign'] = $this->_sign($pub_params, $this->key);
        $pub_params['pay_productname'] = 'Goods';
        $this->parameter = $pub_params;
    }

    /**
     * 异步返回，使用go.php自动跳转
     */
    private function parseRE()
    {
        //使用redis保存信息的跳转页面
        $this->buildGoOrderUrl();
        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->goPayUrl;
    }

    public function returnVerify($parameters)
    {
        global $app;
        $parameters = $app->getContainer()->request->getParams();
        unset($parameters['s']);

        if (!isset($parameters['orderid']) || !isset($parameters['transaction_id']) || !isset($parameters['amount'])) {
            return false;
        }

        $res = [
            'status' => 0,
            'order_number' => $parameters['orderid'],
            'third_order' => $parameters['transaction_id'],
            'third_money' => $parameters['amount'] * 100,
        ];

        if ($parameters['returncode'] != '00') {
            $res['status'] = 0;
            $res['error'] = '支付订单状态未成功';
            return $res;
        }

        $config = Recharge::getThirdConfig($parameters['orderid']);
        //未查询到配置数据
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '支付失败';
            return $res;
        }

        //按规则验签
        $result = $this->returnVail($parameters, $config['key']);
        if (!$result) {
            $res['status'] = 0;
            $res['error'] = '验签失败!';
            return $res;
        }

        //向第三方查询支付订单,查询地址可配置
        $url = $config['terminal'];
        if (empty($url)) {
            $arr = parse_url($config['payurl']);
            $url = $arr['scheme'] . '://' . $arr['host'] . (isset($arr["port"]) ? ":" . $arr["port"] : "") . '/Pay_Trade_query.html';
        }

        $success = $this->queryOrder($url, $res['order_number'], $config['partner_id'], $config['key']);
        //查询第三方有结果
        if ($success != null && $success != 'SUCCESS') {
            $res['status'] = 0;
            $res['error'] = '查询第三方订单返回状态:' . $success;
            return $res;
        }
        $res['status'] = 1;
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
            if ($val != null && $val != '') {
                $string[] = $key . '=' . $val;
            }
        }
        $params = join('&', $string);
        $sign_str = $params . '&key=' . $tkey;
        $sign = md5($sign_str);
        return strtoupper($sign);
    }

    public function returnVail($data, $pubkey)
    {
        $signstr = $data['sign'];
        unset($data['sign']);
        $sign = $this->_sign($data, $pubkey);
        return $sign == $signstr;
    }

    public function queryOrder($queryUrl, $orderNumber, $partnerID, $tkey)
    {
        $params = [
            "pay_memberid" => $partnerID,
            "pay_orderid" => $orderNumber,
        ];

        $params['pay_md5sign'] = $this->_sign($params, $tkey);

        $this->payUrl = $queryUrl;
        $this->parameter = $params;

        $this->logCurlFunc($orderNumber, $this->basePost());

        $re = json_decode($this->re, true);

        if (isset($re['trade_state'])) {
            return $re['trade_state'];
        }
        return null;
    }

}