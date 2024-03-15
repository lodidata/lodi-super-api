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
 * 派支付
 * Class PZF
 * @package Logic\Recharge\Pay
 */
class PZF extends BASES
{
    //与第三方交互
    public function start(){
        $this->initParam();
        if(!$this->payUrl)
            $this->payUrl = 'https://api.vc-pai.com/gateway/api/scanpay';
        $this->parseRE();
    }

    //初始化参数
    public function initParam(){
        $this->parameter = array(
            'merchant_code' => $this->partnerID,
            'service_type' => $this->data['bank_data'],
            'interface_version'	=> 'V3.1',
            'client_ip' => '127.0.0.1',
            'notify_url' => $this->notifyUrl,
            'order_no'=>$this->orderID,
            'order_time'=>Date('Y-m-d H:i:s'),
            'order_amount'=>$this->money/100,
            'product_name'=>'goods',
        );

        if ($this->parameter['service_type'] == 'direct_pay') {
            $this->parameter['interface_version'] = 'V3.0';
            $this->parameter['input_charset'] = 'UTF-8';
        }


        $this->parameter['sign'] = $this->encryptSign($this->parameter);
        $this->parameter['sign_type'] = 'RSA-S';

    }

    /**
     * @param $sign_arr
     * 加密返回加密串
     */
    public function encryptSign($sign_arr)
    {
        $signStr = "";

        $signStr = $signStr."client_ip=".$sign_arr['client_ip']."&";



        if (isset($sign_arr['input_charset'])) $signStr = $signStr."input_charset=".$sign_arr['input_charset']."&";
        $signStr = $signStr."interface_version=".$sign_arr['interface_version']."&";

        $signStr = $signStr."merchant_code=".$sign_arr['merchant_code']."&";

        $signStr = $signStr."notify_url=".$sign_arr['notify_url']."&";

        $signStr = $signStr."order_amount=".$sign_arr['order_amount']."&";

        $signStr = $signStr."order_no=".$sign_arr['order_no']."&";

        $signStr = $signStr."order_time=".$sign_arr['order_time']."&";


        $signStr = $signStr."product_name=".$sign_arr['product_name']."&";



        $signStr = $signStr."service_type=".$sign_arr['service_type'];

/////////////////////////////   RSA-S签名  /////////////////////////////////


/////////////////////////////////初始化商户私钥//////////////////////////////////////


        $merchant_private_key= openssl_get_privatekey($this->key);

        openssl_sign($signStr,$sign_info,$this->key,OPENSSL_ALGO_MD5);

        $sign = base64_encode($sign_info);
        return $sign;
    }


    //返回参数
    public function parseRE(){
        if ($this->parameter['service_type'] == 'direct_pay'){
            $this->parameter['sign'] = urlencode($this->parameter['sign']);
            $this->parameter = $this->arrayToURL();
            $this->parameter .= '&url=' . $this->payUrl;
            $this->parameter .= '&method=POST';
            $str = $this->jumpURL.'?'.$this->parameter;
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $str;
        }else {
            $this->basePost();
            $result = $this->parseXML($this->re);
            $re = $result['response'];
            if ($re['result_code'] == 0) {
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->showType;
                $this->return['str'] = urldecode($re['payURL']);
                if (strstr($this->return['str'], '.COM')) {
                    $strArr = explode('.COM', $this->return['str']);
                    $str = strtolower($strArr[0]) . ".com" . $strArr[1];
                    $this->return['str'] = $str;
                }
            } else {
                $this->return['code'] = 55;
                $this->return['msg'] = 'PZF:' . $re['result_desc'];
                $this->return['way'] = $this->showType;
                $this->return['str'] = '';
            }

        }
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status' => 1,
            'order_number' => $parameters['order_no'],
            'third_order' => '',
            'third_money' => 0,
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['order_no']);

        if(!$config){
            $res['status']=0;
            $res['error']='未有该订单';
        }
        $result=$this->returnVail($parameters,$config);

        if($result){
            $res['status']=1;
        }else{
            $res['status']=0;
            $res['error']='验签失败！';
        }
        return $res;
    }

    //签名验证封装
    public function returnVail($parameters,$config){
        $signStr = "";
        $tempSign =  $parameters['sign'];
        unset($parameters['sign']);
        unset($parameters['sign_type']);
        ksort($parameters);
        foreach($parameters as $k => $v) {
            //  字符串0  和 0 全过滤了，所以加上
            if(!empty($v) || $v === 0  || $v === "0") {
                $signStr .= $k . "=" . $v . "&";
            }
        }
        $signStr = rtrim($signStr,'&');
/////////////////////////////   RSA-S验签  /////////////////////////////////

        $dinpay_public_key = openssl_get_publickey($config['pub_key']);

        $flag = openssl_verify($signStr,base64_decode($tempSign),$dinpay_public_key,OPENSSL_ALGO_MD5);
//////////////////////   异步通知必须响应“SUCCESS” /////////////////////////
        /**
        如果验签返回ture就响应SUCCESS,并处理业务逻辑，如果返回false，则终止业务逻辑。
         */

        return $flag;
    }
}