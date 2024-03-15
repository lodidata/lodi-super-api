<?php
/**
 * 华信支付
 * Created by Hans.
 * Date: 2019/6/1
 */

namespace Logic\Recharge\Pay;


use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class HUAXZF extends BASES
{
    public function start()
    {
        if ($this->data['rule'] == '3') {//固额
            $request_money = $this->getRequestMoney($this->money);
            if (empty($request_money)) {
                $this->return['code'] = 99;
                $this->return['msg'] = '请求失败,不支持此固额,请检查支付配置中APP_SITE是否为空或不存在对应金额(以,分隔填写金额)';
                $this->return['way'] = $this->showType;
                return;
            }
            $this->money = $request_money;
        }
        $this->initParams();
        $this->payJson2();
        $this->parseRE();
    }

    private function initParams()
    {
        $this->returnUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/return_url.php';

        $pub_params = [
            'merchNo' => (string)$this->partnerID,
            'orderNo' => (string)$this->orderID,
            'amount' => $this->money/100,
            'outChannel' => $this->payType,
            'notifyUrl' => $this->notifyUrl,
            'reqTime' => date('YmdHis', time()),
            'userId' => rand(1,88888),
        ];

        $pub_params['sign'] = $this->_sign($pub_params, $this->key);
        $this->parameter = $pub_params;
    }

    private function getRequestMoney($money)
    {
        if (empty($this->data['app_site'])) {
            return $money;
        }
        //PDD通道
        //例如：支付渠道配置的固额(200,300,400) 支付配置中APP_SITE就要对应配置上要转的金额(169,296,399)
        $money_source = explode(',', $this->data['moneys']);
        //对应第三方请求的金额,在支付配置APP_SITE框中配置
        $money_real = explode(',', $this->data['app_site']);

        $index = array_search($money * 100, $money_source);

        if ($index < 0 || $money_real == null || count($money_real) < $index - 1) {
            return null; //找不到对应金额映射
        }
        return $money_real[$index];
    }

    /**
     * 异步返回，使用go.php自动跳转
     */
    private function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['data']) && $re['code'] == 0) {
            $this->return['code'] = 0;//code为空代表是OK
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;

            if (!empty($re['data']['qrcode_url'])) {
                $url = $this->qrcodeUrl . $re['data']['qrcode_url'];
            } else {
                $url = $re['data']['code_url'];
            }
            $this->return['str'] = $url;
        } else {
            $this->return['code'] = 99;
            $this->return['msg'] = '华信支付:' . (empty($re['msg']) ? "第三方未知错误" : $re['msg']);
            $this->return['way'] = $this->showType;;
            $this->return['str'] = '';
        }
    }

    public function returnVerify($parameters)
    {
        global $app;
        $parameters = $app->getContainer()->request->getParams();
        unset($parameters['s']);

        if ((!isset($parameters['code'])) || $parameters['code'] != 0 || (!isset($parameters['data']))) {
            return false;
        }

        $parameters = $parameters['data'];

        if (!(isset($parameters['orderNo']) && isset($parameters['amount']))) {
            return false;
        }
        $res = [
            'status' => 0,
            'order_number' => $parameters['orderNo'],
            'third_order' => isset($parameters['businessNo']) ? $parameters['businessNo'] : $parameters['orderNo'],
            'third_money' => $parameters['amount']*100,
        ];

        if ($parameters['orderState'] != 1) {
            $res['status'] = 0;
            $res['error'] = '支付订单状态未成功';
            return $res;
        }

        $config = Recharge::getThirdConfig($res['order_number']);
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
            $string[] = $key . '=' . $val;
        }
        $params = join('&', $string);
        $sign_str = $params . $tkey;
        $sign = md5($sign_str);
        return strtoupper($sign);
    }

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