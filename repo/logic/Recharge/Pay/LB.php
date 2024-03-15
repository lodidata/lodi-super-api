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
 * 联邦支付
 * Class GT
 * @package Logic\Recharge\Pay
 */
class LB extends BASES
{
    private $parameters;
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $this->parameters = array(
            'mchno' => $this->partnerID,
            'orderid' => $this->orderID,
            'price' => $this->money / 100,
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl
        );

    }


    function getMd5Sign($params, $signKey)//签名方式
    {
        ksort($params);
        $data = "";
        foreach ($params as $key => $value) {
            if ($value === '' || $value == null) {
                continue;
            }
            $data .= $key . '=' . $value . '&';
        }
        $sign = md5($data . 'key=' . $signKey);
        return $sign;
    }


    //返回参数
    public function parseRE()
    {
        $this->parameters['sign'] = $this->getMd5Sign($this->parameters, $this->key);

        $xmlStr = $this->toXmlS($this->parameters);
        $this->parameter['req'] = urlencode($xmlStr);
        $this->parameter = $this->arrayToURL();
        $this->parameter .= '&url=' . $this->payUrl;
        $this->parameter .= '&method=POST';

        $str = $this->jumpURL . '?' . $this->parameter;

        $this->return['code'] = 0;
        $this->return['msg'] = 'SUCCESS';
        $this->return['way'] = $this->data['return_type'];
        $this->return['str'] = $str;
    }

    /**
     * 将数据转为XML
     */
    public function toXmlS(array $array){

        $xml = '<?xml version="1.0" encoding="GBK" standalone="yes"?><xml>';
        forEach($array as $k=>$v){
            $xml.='<'.$k.'>'.$v.'</'.$k.'>';
        }
        $xml.='</xml>';

        return $xml;
    }


    //签名验证
    public function returnVerify($pieces)
    {
        $pieces = file_get_contents("php://input");
        $pieces = substr(urldecode($pieces), strpos(urldecode($pieces), "<xml>"));
        $pieces = simplexml_load_string($pieces, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($pieces), true);

        $res = [
            'status' => 1,
            'order_number' => $val['orderid'],
            'third_order' => '',
            'third_money' => $val['price'] * 100,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($val['orderid']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        $result = $this->verifySign($val, $config['pub_key']);

        if ($result) {
            $res['status'] = 1;
            $res['error'] = '';
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    /**
     * 验证签名
     * @param array $params 数据
     * @param string $platformPublicKey 公钥
     * @return bool
     */
    function verifySign($params, $pubKey)
    {
        ksort($params);
        $data = "";
        foreach ($params as $key => $value) {
            if ($value === '' || $value == null || $key == 'sign') {
                continue;
            }
            $data .= $key . '=' . $value . '&';
        }
        if (!is_string($params['sign']) || !is_string($params['sign'])) {
            return false;
        }
        $signStr = $data . $pubKey;
        return (bool)$signStr == $params['sign'];
    }


}