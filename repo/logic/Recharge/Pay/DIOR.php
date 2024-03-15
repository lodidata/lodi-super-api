<?php
/**
 * 迪奥支付
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class DIOR extends BASES
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
        $pub_params = [
            'MerchantCode' => $this->partnerID,
            'BankCode' => (string)$this->payType,
            'Amount' => sprintf("%.2f", $this->money/100),
            'OrderId' => (string)$this->orderID,
            'NotifyUrl' => $this->notifyUrl,
            'OrderDate' => (string)(time() * 1000),
            'Ip' => $this->data['client_ip'],
        ];
        $pub_params['Sign'] = $this->_sign($pub_params, $this->key);
        $this->parameter = $pub_params;
    }

    /**
     * 组装前端数据,输出结果
     */
    public function parseRE()
    {
        $re = json_decode($this->re, true);
        if (isset($re['resultCode']) && $re['resultCode'] == '200' && $re['success']) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $re_data = $re['data']['data'];
            //['type'=>'string', 'info'=>"<script>window.location.href = 'http://59.56.78.161:9010/topay/index.jhtml?orderid=16cd7900f60e1a98e2fdae44c3711b22a150683f56575967';</script>"]
            //['type'=>'url', 'info'=>"http://mat01.02alipay.com:9099/jump/alipay?oid=5db56315e4f4c=19998"]
            if ($re_data['type'] == 'url') {
                $url = $re_data['info'];
//                if($this->payType=='ALIPAY'||$this->payType='WECHAT '){
//                    $url = $this->qrcodeUrl . urlencode($url);
//                }
            } else if ($re_data['type'] == 'img') {
//                $url = $this->jumpURL . '?method=IMG&img=' . $re_data['info'];
                $url = $re_data['info'];
            } else {//html
                $url = $this->jumpURL . '?method=HTML&html=' . base64_encode($re_data['info']);
            }
            $this->return['str'] = $url;
        } else {
            $this->return['code'] = 8;
//            $this->return['code'] = $re['resultCode'];
            $this->return['msg'] = "迪奥支付:".$re['resultMsg'];
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
        global $app;
        $parameters = $app->getContainer()->request->getParams();
        unset($parameters['s']);

        if ((!isset($parameters['OrderId']) && isset($parameters['OutTradeNo']) && isset($parameters['Amount']))) {
            return false;
        }

        $res = [
            'order_number' => $parameters['OrderId'],
            'third_order' => $parameters['OutTradeNo'],
            'third_money' => $parameters['Amount'] * 100,//元转为分
        ];
        $config = Recharge::getThirdConfig($parameters['OrderId']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if ($parameters['Status'] != 1) {
            $res['status'] = 0;
            $res['error'] = '订单支付状态为失败';
            return $res;
        }
        $result = $this->returnVail($parameters, $config['key']);
        if ($result) {
//            $this->updateMoney($res['order_number'], $res['third_money']);
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
    private function _sign($pieces, $tkey)
    {
        ksort($pieces);
        $string = [];
        foreach ($pieces as $key => $val) {
            $string[] = $key . '=' . $val;
        }
        $params = join('&', $string);
        $sign_str = $params . '&Key=' . $tkey;
        $sign = md5($sign_str);
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
        $return_sign = $params['Sign'];
        unset($params['Sign']);
        foreach ($params as $k => $val) {
            if (empty($val)) {
                unset($params[$k]);
            }
        }
        $sign = $this->_sign($params, $tkey);
        if ($sign != $return_sign) {
            return false;
        }
        return true;
    }
}