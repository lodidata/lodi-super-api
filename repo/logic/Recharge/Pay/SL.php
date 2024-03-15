<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 *
 * 速龙
 * @author Lion
 */
class SL extends BASES {

    static function instantiation(){

        return new SL();
    }

    private $_returnUrl = '';

    //与第三方交互
    public function start(){

        $this->initParam();       // 数据初始化
        $this->parseRE();         // 处理结果
    }

    //组装数组
    public function initParam(){

        $this->parameter['merchant_code']        = $this->partnerID;         //商家号
        $this->parameter['service_type']         = $this->data['bank_data'];   //业务类型
        $this->parameter['notify_url']           = $this->notifyUrl;         //服务器异步通知地址
        $this->parameter['interface_version']    = $this->data['app_id'];      //接口版本    固定值：V3.1(必须大写)
        if($this->data['app_id'] == 'V3.0'){
            $this->parameter['input_charse']     = 'UTF-8';
        }
        $this->parameter['client_ip']            = Client::getClientIp();    //客户端IP    消费者创建交易时所使用机器的IP或者终端ip，最大长度为15个字符
        $this->parameter['order_no']             = $this->orderID;           //商户网站唯一订单号
        $this->parameter['order_time']           = date('Y-m-d H:i:s',time());       //商户订单时间    //yyyy-MM-dd HH:mm:ss，举例：2013-11-01 12:34:58
        $this->parameter['order_amount']         = str_replace(',', '', number_format($this->money/100, 2));  //商户订单总金额    //该笔订单的总金额，以元为单位，精确到小数点后两位。
        $this->parameter['product_name']         = time();       //商品名称
        $this->parameter['product_code']         = '';           //商品编号         可选
        $this->parameter['product_num']          = '';           //商品数量         可选
        $this->parameter['product_desc']         =  '';          //商品描述         可选
        $this->parameter['extra_return_param']   =  '';          //公用回传参数     可选
        $this->parameter['extend_param']         =  '';          //公用业务扩展参数 可选
        $this->parameter['sign']                 = $this->signparame();      //签名
        $this->parameter['sign_type']            = 'RSA-S';                  //签名方式    RSA或RSA-S，不参与签名

    }

    //生成签名
    public function signparame() {

        $arr1 = array_filter ($this->parameter);  //去除空值
        ksort($arr1);  //键名升序

        $str = '';
        $nub = 1;
        foreach($arr1 as $k => $v){
            if($nub == 1){
                $str .=   $k  ."=".$v;
            }else{
                $str .=   '&'.$k  ."=".$v;
            }
            $nub ++;
        }

        $merchant_private_key = openssl_get_privatekey($this->key);
        openssl_sign($str,$sign_info,$merchant_private_key,OPENSSL_ALGO_MD5);
        $sign = base64_encode($sign_info);

        return $sign;
    }

    //处理结果
    public function parseRE(){

        if($this->data['app_id'] == 'V3.1'){
            $this->basePost();

            $re = $this->parseXML($this->re);

           // Array ( [response] => Array ( [resp_code] => FAIL [resp_desc] => order_amount 非法 [result_code] => 1 ) )
           //print_r($re);   die;
            if($re['response']['result_code'] == 1){   //0：获取二维码成功  1：获取二维码失败
                $this->return['code'] = 886;
                $this->return['msg'] = 'SL:'.$re['response']['resp_desc'];
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = '';
            }else{
                if(isset($re['response']['qrcode'])){
                    $this->return['code'] = 0;
                    $this->return['msg'] = 'SUCCESS';
                    $this->return['way'] = $this->data['return_type'];
                    $this->return['str'] = $re['response']['qrcode'];
                }else{
                    $this->return['code'] = 886;
                    $this->return['msg'] = 'SL:'.$re['response']['resp_desc'];
                    $this->return['way'] = $this->data['return_type'];
                    $this->return['str'] = '';
                }
            }

        }else{
            $this->parameter['sign'] = urlencode($this->parameter['sign']);
            $this->parameter = $this->arrayToURL();
            $this->parameter .= '&url=' . $this->payUrl;
            $this->parameter .= '&method=POST';
            $str = $this->jumpURL.'?'.$this->parameter;
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $str;
        }
    }

    //回调数据校验
    /*
     * $parameters 第三方通知数组
     * $key  公钥
     * $app_id  商户PID
     * */
    public function returnVerify($parameters = array()) {

        $res = [
            'status' => 0,
            'order_number' => $parameters['order_no'],
            'third_order'  => $parameters['trade_no'],
            'third_money'  => $parameters['order_amount']*100,
            'error' => ''
        ];

        $signStr = '';

        $signStr .= "interface_version=".$parameters['interface_version']."&";

        $signStr .= "merchant_code=".$parameters['merchant_code']."&";

        $signStr .= "notify_id=".$parameters['notify_id']."&";

        $signStr .= "notify_type=".$parameters['notify_type']."&";

        $signStr .= "order_amount=".$parameters['order_amount']."&";

        $signStr .= "order_no=".$parameters['order_no']."&";

        $signStr .= "order_time=".$parameters['order_time']."&";

        $signStr .= "trade_no=".$parameters['trade_no']."&";

        $signStr .=  "trade_status=".$parameters['trade_status']."&";

        $signStr .=  "trade_time=".$parameters['trade_time'];

        //使用平台的公钥进行解签
        $key ='-----BEGIN PUBLIC KEY-----
            MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDAInNBckIDD5joW1GAq
            IgF1maOTOFN1ZE7aDj8sBlZQFZx7jvkMSnBQSEwtzQc/IioZu1h4CbJQM
            xUc4KnmXKpZTyspY7B48dbJzOxvqjfTPbdvj8ltiV4u9STz50GKyjfQTk
            Y4fSghCPQgq71LW0+teE3aLNr/YAP6FPDUE+/kwIDAQAB
-----END PUBLIC KEY-----';

        $dinpaySign = base64_decode($parameters["sign"]);

        $flag = openssl_verify($signStr,$dinpaySign,$key,OPENSSL_ALGO_MD5);

        if($flag){
            $res['status']  = 1;
        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }
        return  $res;

    }

}
