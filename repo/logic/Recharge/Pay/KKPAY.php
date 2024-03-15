<?php
/**
 * KKpay: Taylor 2019-06-15
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 大栋支付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class KKPAY extends BASES
{
    private $httpCode = '';

    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    public function trimNullValue($parames)
    {
        foreach ($parames as $key => $val) {
            if ($val == null || $val == '') {
                unset($parames[$key]);
            }
        }
        return $parames;
    }

    public function formatArr2Str($params)
    {
        ksort($params);
        reset($params);
        $unsign = http_build_query($params, '', '&');
        return $unsign;
    }

    function urlsafe_b64encode($string)
    {
        $data = base64_encode($string);
        $data = str_replace(array('+', '/', '='), array('-', '_', ''), $data);
        return $data;
    }

    function urlsafe_b64decode(string $string)
    {
        $data = str_replace(array('-', '_'), array('+', '/'), $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

    public function sign($postdata)
    {
        $pfxpath = dirname(__FILE__) . "/cert/{$this->data['app_id']}-GY001.pfx";
        $cer_key = file_get_contents($pfxpath);
        openssl_pkcs12_read($cer_key, $certs, $this->key);
        $pkeyid = openssl_get_privatekey($certs['pkey']);
        openssl_sign($postdata, $signMsg, $pkeyid, OPENSSL_ALGO_SHA1);
        $signMsg = $this->urlsafe_b64encode($signMsg);
        openssl_free_key($pkeyid);
        return $signMsg;
    }

    public function ckSign($params, $pub_key)
    {
        $sigm = $params['signMsg'];
        unset($params['signMsg']);
        unset($params['rspCod']);
        unset($params['rspMsg']);
        unset($params['urlAsync']);
        $params    = $this->trimNullValue($params);
        $unsign    = $this->formatArr2Str($params);
        $str = chunk_split(trim($pub_key), 64, "\n");
        $cert = "-----BEGIN PUBLIC KEY-----\n$str-----END PUBLIC KEY-----\n";
        $pubkeyid  = openssl_pkey_get_public($cert);
        $sigm      = $this->urlsafe_b64decode($sigm);
        $r         = openssl_verify($unsign, $sigm, $pubkeyid);
        openssl_free_key($pubkeyid);
        return $r == 1 ? true : false;
    }

    public function updateCharset($unsign)
    {
        $charset = mb_detect_encoding($unsign);
        if ($charset !== FALSE) {
            $unsign = mb_convert_encoding($unsign, 'GB18030', $charset);
        }
        return $unsign;
    }

    //初始化参数
    public function initParam()
    {
        if($this->data['action'] == '02001'){//网银快捷
            $params = array(
                'interfaceCode' => $this->data['action'],//接口类型
                'charset' => '1',
                'accessType' => '1',
                'merchantId' => $this->partnerID,//商户ID
                'signType' => '3',
                'notifyUrl' => $this->notifyUrl,//异步通知地址
                'pageUrl' => $this->returnUrl,//同步地址
                'version' => 'v1.0',//
                'language' => '1',//
                'order_id' => $this->orderID,//商户订单号
                'amount' => $this->money,//交易金额，单位：分
                'amt_type' => 'CNY',
            );
        }else if($this->data['action'] == '03001'){//微信H5支付
            $params = array(
                'interfaceCode' => $this->data['action'],//接口类型
                'charset' => '1',
                'accessType' => '1',
                'merchantId' => $this->partnerID,//商户ID
                'signType' => '3',
                'notifyUrl' => $this->notifyUrl,//异步通知地址
                'pageUrl' => $this->returnUrl,//同步地址
                'version' => 'v1.0',//
                'language' => '1',//
                'orderNo' => $this->orderID,//商户订单号
                'amount' => $this->money,//交易金额，单位：分
                'currency' => 'CNY',
                'appType' => $this->payType,
                'orderTime' => date('YmdHis'),
            );
        }else if($this->data['action'] == '01001'){//微信扫码
            $params = array(
                'interfaceCode' => $this->data['action'],//接口类型
                'inputCharset' => '1',
                'version' => 'v1.0',//
                'language' => '1',//
                'signType' => '3',
                'submitType' => '00',
                'pageUrl' => $this->returnUrl,//同步地址
                'bgUrl' => $this->notifyUrl,//异步通知地址
                'payType' => '0',
                'jumpType' => '00',
                'accessType' => '1',
                'merchantId' => $this->partnerID,//商户ID
                'paymentType' => '2',
                'loginType' => '1',
                'orderNo' => $this->orderID,//商户订单号
                'currency' => 'CNY',
                'orderAmount' => $this->money,//交易金额，单位：分
                'orderTime' => date('YmdHis'),
                'productDesc' => 'VIP:'.$this->orderID,
                'isGuarant' => '0',
                'merPayType' => $this->payType,
            );
        }
        $params            = $this->trimNullValue($params);
        $unsign            = $this->formatArr2Str($params);
        $sign              = $this->sign($unsign);
        $params['signMsg'] = trim($sign);
        $postData          = $this->formatArr2Str($params);

        $this->parameter  = $this->updateCharset($postData);
    }

    //返回参数
    public function parseRE()
    {
//        foreach ($this->parameter as &$item) {
//            $item = urlencode($item);
//        }
//        $this->parameter = $this->arrayToURL();
        $this->parameter .= '&url=' . $this->payUrl;
        $this->parameter .= '&method=POST';

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->showType;
        $this->return['str'] = $this->jumpURL . '?' . $this->parameter;
    }

    public function str2Fields($str)
    {
        $fields    = explode("&", $str);
        $repFields = array();
        foreach ($fields as $field) {
            $keyValue               = explode("=", $field);
            $repFields[$keyValue[0]] = $keyValue[1];
        }
        return $repFields;
    }

    //签名验证
    public function returnVerify($pieces)
    {
        global $app;
        $result = $app->getContainer()->request->getParams();
        unset($result['s']);
        //支付成功才会回调
        $res = [
            'status' => 1,
            'order_number' => $result['orderNo'],
            'third_order' => $result['payOrderId'],//第三方的支付订单号
            'third_money' => $result['orderAmount'],//支付金额为分，保留两位小数
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($res['order_number']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if ($result['payResult'] != '00') {
            $res['status'] = 0;
            $res['error'] = '未支付';
            return $res;
        }
//        $post = $this->str2Fields(file_get_contents('php://input', 'r'));
        ksort($result);
        $sign_result = $this->ckSign($result, $config['pub_key']);
        if ($sign_result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }
}