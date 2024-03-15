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
 * 恒力信支付：注意对照请求参数，可能有不同的支付
 *
 */
class HLXPAY extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        // 请求参数
        $data = array(
            "orderId" => $this->orderID,
            "amount" => $this->money,//请求单位：分
            "version" => "1",
            "merchantCode" => $this->partnerID,//商户号
            "body" => $this->orderID,
            "notifyUrl" => $this->notifyUrl,
        );
        $params = array(
            "data" => urlencode(base64_encode(json_encode($data))),
            "sign" => $this->_sign($data, $this->key),
        );

        $this->parameter = $params;
    }

    //返回参数
    public function parseRE()
    {
        $re = json_decode($this->re, 1);

        //预下单拿到交易编号
        if ($re["success"] == true) {
            $re["data"] = json_decode(base64_decode(urldecode($re["data"])), true);

            if (!empty($re['data']['tranId'])) {
                //获取支付链接
                $re = $this->getPayData($re['data']['tranId']);
                if ($re['success'] == 1 && isset($re['data']['payParams'])) {
                    $params = json_decode($re['data']['payParams'], true);
                    $this->return['code'] = 0;
                    $this->return['msg'] = 'SUCCESS';
                    $this->return['str'] = $params['codeUrl'];
                    $this->return['way'] = $this->data['return_type'];
                    return;
                }
            }
        }
        $this->return['code'] = 99;
        $this->return['msg'] = '恒力信支付:' . (isset($re['msg']) ? $re['msg'] : "发起请求失败");
        $this->return['way'] = $this->data['return_type'];

    }

    private function getPayData($tranId)
    {
        $arr = parse_url($this->payUrl);
        $url = sprintf("%s://%s", $arr['scheme'], $arr['host']) . '/pay/post';

        $data = array(
            "version" => "1",
            "merchantCode" => $this->partnerID,//商户号
            "way" => $this->payType,
            "tranId" => $tranId,//预下单 接口返回
        );
        $params = array(
            "data" => urlencode(base64_encode(json_encode($data))),
            "sign" => $this->_sign($data, $this->key),
        );

        $this->parameter = $params;
        $this->payUrl = $url;
        $this->basePost();

        //返回内容
        $re = json_decode($this->re, 1);
        //预下单拿到交易编号
        if ($re["success"] == true) {
            $re["data"] = json_decode(base64_decode(urldecode($re["data"])), true);
        }
        return $re;
    }

    function _sign($parameters, $key)
    {
        $signPars = json_encode($parameters) . $key;
        return md5($signPars);
    }

    //签名验证
    public function returnVerify($parameters)
    {
        if (!isset($parameters['data'])) {
            return false;
        }

        $return_sign = $parameters['sign'];

        if (strpos($parameters['data'], "%")) {
            $parameters['data'] = urldecode(($parameters['data']));
        }
        $parameters = base64_decode($parameters['data']);
        $data = $parameters; //用来验签的json数据串

        $parameters = json_decode($data, true);
        if (!(isset($parameters['amount']) && isset($parameters['orderId']))) {
            //非法数据
            return false;
        }

        $order_number = $parameters['orderId'];

        if ($parameters['status'] != 1) {
            $res['status'] = 0;
            $res['error'] = '未成功订单';
            return $res;
        }

        $res = [
            'status' => 0,
            'order_number' => $order_number,
            'third_order' => $parameters['tranId'],
            'third_money' => $parameters['amount'],
        ];

        $config = Recharge::getThirdConfig($order_number);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }

        $sign_str = $data . $config['key'];
        $sign = md5($sign_str);

        if ($sign == $return_sign) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }
}