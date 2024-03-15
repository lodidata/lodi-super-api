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
use Utils\Client;
use Psr\Http\Message\ServerRequestInterface;

/**
 * SPD支付
 * Class SPD
 * @package Logic\Recharge\Pay
 */
class SPD extends BASES
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
        $this->parameter = array(
            'sys_protocol_version' => '0.0.2',
            'sys_sdk_version' =>'0.0.1/java',
            'sys_cert_no' =>'20190227201822457005',
            'sys_sign_method' => 'MD5',
            'sys_api_name' => 'trade.create',
            'sys_api_version' => '0.0.1',
            'merchantNo' => $this->partnerID,
            'payType' => $this->data['bank_data'],
            'outTradeNo' => $this->orderID,
            'amount' => $this->money,
            'content' => 'GOODS',
            'clientIp' => Client::getIp(),
            'callbackURL' => $this->notifyUrl,
            'returnURL'=>$this->returnUrl
        );

        $pl = $this->data['pl'];
        if($this->data['bank_data']=='UNION_PAY'){
            if($pl && $pl=='h5'){
                $this->parameter['payType']='H5_UNION_PAY';
            }
        }

        $this->parameter['sys_sign'] = $this->sytMd5New($this->parameter,$this->key);
    }

    public function sytMd5New($pieces,$key)
    {
        ksort($pieces);
        $md5str = "";
        foreach ($pieces as $keyVal => $val) {
            $md5str = $md5str . $keyVal . "=" . $val . "&";
        }
        $md5str=$md5str.'key='. $key;
        $sign = strtoupper(md5($md5str));
        return $sign;
    }

    //返回参数h\
    public function parseRE()
    {
        $re = json_decode($this->re,true);

        if(isset($re['errorCode']) && $re['errorCode']== 'SUCCEED') {
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = $re['data']['paymentInfo'];
        }else {
            $this->return['code'] = 886;
            $this->return['msg']  = 'SPD:'.$re['errorCode'];
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = '';
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
        $headers =$this->_getallheaders();
        $res = [
            'status' => 1,
            'order_number' => $pieces['outTradeNo'],
            'third_order' => $pieces['tradeNo'],
            'third_money' => $pieces['amount'],
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['outTradeNo']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if (self::retrunVail($headers['x-oapi-sign'],$pieces, $config)) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function retrunVail($sign,$pieces, $config)
    {
        return $sign == $this->sytMd5New($pieces,$config['key']);
    }


    function _getallheaders() {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $key = str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))));
                if(0 == strcmp($key, "x-oapi-sign")) {
                    $headers[$key] = urldecode($value);
                } else {
                    $headers[$key] = urldecode($value);
                }
            }
        }
        return $headers;
    }


}