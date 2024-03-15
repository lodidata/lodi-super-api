<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/10/15
 * Time: 15:44
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class JFT extends BASES
{

    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->doPay();
    }

    //组装数组
    public function initParam()
    {

        //请求数据

        $this->parameter = array(
            "merchantId" => $this->partnerID, //商户号
            "merOrderId" => $this->orderID, //商户订单号
            "txnAmt" => strval($this->money), //支付金额 单位分
            "backUrl" => $this->notifyUrl, //异步回调 , 支付结果以异步为准
            "frontUrl" => $this->returnUrl, //同步回调 不作为最终支付结果为准，请以异步回调为准
            "subject" => "recharge",
            "userId" => 1,
            'content' => 'goods',
            'gateway' => $this->payType,
        );
//        var_dump($this->parameter);die();
        $this->parameter["signature"] = $this->getSign($this->parameter, $this->key); //加密
        $this->parameter['signMethod'] = "MD5";
        $this->parameter['subject'] = base64_encode($this->parameter['subject']);
        $this->parameter['content'] = base64_encode($this->parameter['content']);
    }


    protected function doPay()
    {
        $this->getHttpContent($this->payUrl, "POST", $this->parameter);
        $re = json_decode($this->re, true);
        if (empty($re)) {
            $this->return['code'] = 886;
            $this->return['msg'] = 'JFT:网络异常';
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
            return;
        } //如果转换错误，原样输出返回
        //验证返回信息
        if ($re["success"] == 1) {

            $this->return['code'] = 0;
            $this->return['msg'] = 'success';
            $this->return['way'] = $this->showType;
            $this->return['url'] = $re['payLink'];
            $this->return['str'] = $re['payLink'];
            $this->parameter['method'] = 'GET';

        } else {
            $this->return['code'] = 886;
            $this->return['msg'] = isset($re['error']) && !empty($re['error']) ? $re['error'] : 'JFT:第三方通道未知错误';
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
            return;
        }

    }

    public function getSign($data, $key)
    {
        ksort($data);
        $signStr = urldecode(http_build_query($data));
        $signStr = $signStr . $key;
        $signStr = base64_encode(md5($signStr, TRUE));
        return $signStr;


    }


    public function returnVerify($params)
    {

        $res = [
            'status' => 0,
            'order_number' => $params['merOrderId'],//outorderno
            'third_order' => $params['merOrderId'],
            'third_money' => $params['txnAmt'],
            'error' => ''
        ];


        $config = Recharge::getThirdConfig($params['merOrderId']);
        $returnSign = $params['signature'];
        unset($params['signature']);
        $mysign = $this->getSign($params, $config['key']);
        if ($returnSign == $mysign) {
            if ($params['respCode'] == '1001') {//支付成功
                $res['status'] = 1;
            } else { //支付失败
                $res['error'] = '支付失败';
                echo 'fail';
            }
        } else {
            $res['error'] = '该订单验签不通过或已完成';
        }

        return $res;

    }

    function getHttpContent($url, $method = 'GET', $postData = array())
    {
        $data = '';
//        $user_agent = $_SERVER ['HTTP_USER_AGENT'];
//        $header = array(
//            "User-Agent: $user_agent"
//        );
        if (!empty($url)) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); //30秒超时
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
//                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                //curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
                if (strstr($url, 'https://')) {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                }

                if (strtoupper($method) == 'POST') {
                    $curlPost = is_array($postData) ? http_build_query($postData) : $postData;
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
                }
                $data = curl_exec($ch);
                $this->curlError = curl_error($ch);
                curl_close($ch);
                $this->re = $data;
            } catch (\Exception $e) {
                $data = '';
            }
        }
        return $data;
    }
}