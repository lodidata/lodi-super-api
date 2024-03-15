<?php
header("Content-type: text/html; charset=utf-8");

//官方支付宝手机wap支付
class ALIPAY{
    protected $config;

    public function __construct(){
        $this->config = [
            //应用ID,您的APPID。
            'app_id' => "2021001163637884",
		    //商户私钥，您的原始格式RSA私钥
		    'merchant_private_key' => "MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCFyHzR5i50Ag7WtpBtouMKKF8bhTPnRBqwzrvGbz2od0U7rDpnNK6E7IpuHaNT+eYZyBRce55usjf7pyNT5AmMl0sCtNbaTYlcfgjT5bN0GFFoBomLdwxsVdwyBvvfO59b91rluyGYuJWkMyBuP8tEJiOIlfuswWC+j8kvpSpGtrnGJ1dC2XUIfgNpwbbvM52Oudbt73QxusoReJCBqT/WNqQiULLqn2GZ/raFdU25Ztu/2DJUlWcTrYvCgrqUe3MQmdmAGZCsMiOK4xrCZIZBzu5BM35lF70XgBuIUN2nIQLmOZ6X07MtA1Vkzul/MAJhpnJz00T8imf659lS5PRJAgMBAAECggEAR266fxTpvtWOeMT4LyInGjheOAKSqSxrF/b6ukSRZo9wryER+iNd/+mRLKS0ndU0MJXtkUgMW0zbqYofyd5b3u61hZdrlRqLepBtRD9E53tIlEPRU19Yicv6i9fAyvw55dAYf8vAb5w3gnouGdAER7oZhQeYXzDN6FI7+S22ehS20cFPhmhQb+X+6RB/XXiP1HRXA+qUU1bnamvV4cUZpkA54Rq1iVLcqommmcsKlxV+ZA3z8USaYGiHFhH0WqyY6LuLVfDW2gMA/4vm0MTnLku/RuU21yd7e1BXvHzamQBQ7EE+rg8CyWjDMDMpfeRw9im/LEIf0A3gfzP+p8kzVQKBgQDGtVDyDa42nrPfZvkmafbJ7hYczKgRwUGAod55t6JT5Ta+CI9FN3Hp+PBxB7VQtM6nTu/wQX9z4GDENEAK2h0YkjmMSXMlM8v622kBCX3X3S4V7r2f9Hc7lhVPau5/WjUIpDR6ITUd7ZA9N+EQhbNYX7eYZtbL1RLAjeH7k36BlwKBgQCsWwpIrac11m7A9IeGPffWQtQWimi8OeOo3hUfgU8CCiqsTcQo8F71WssAi1pAs8x+jYngrancxwyrAVn/peCkiLAlK7vTw8RXgJGw7TQVC9SMbIA63UrxjDds/yTZf63YcLj8aEwoV7ChURpYHVPKxTblEXYW+btKmILuEhk1HwKBgCtPIZFgQfRNqs6qVut0dQGWDuPAB3G3OVub7C2DRLIcZ04L944Sg5WHWICKOY6ZmeEFZ25qGCldYjnhWYQD/gt482oMKDiXjYHjiINdWjxOTNki69mNIt+t2n8ww0KmmqCDiZyE2FrpiGKPZ1J+kZRVaGKjJ3XNvwSQ6Aw5HX2JAoGAZA9Kbh0aaN//Vlff4ehR3HrZo+hgm33EFEIx4yNv2dBLK9LN4bKCflBicAN2tv9q5cGH3P8VEQ4h5ZkYRZloTDzqffngxjTt5JrIef2Lcfh3QbqvyvyzyH1NpCKPoxFDvNbcHfTy2azm5xAtiiAkiCBsGRpFA1uiBtF5mXq6VaMCgYA1EFXOgCtBzCFeaflxlRrGsE+ue1YhmZxlGOYaZq0wefRxTaNNe3DIffu82TIYsutC6VVl0sZG2NT1bqfRAYIC/+a/vb36EPGSThU78Q0oJlh5+PZQsixWFMXVsketkJpx4TyRAva49DE9OK91PPXAnHDrVrBdy3ZO98OJT6shRA==",
            //异步通知地址
            'notify_url' => "http://super-api2.zypaymet.com/alipay.php?method=notify_url",
            //同步跳转
            'return_url' => "http://super-api2.zypaymet.com/alipay.php?method=return_url",
            //编码格式
            'charset' => "UTF-8",
            //签名方式
            'sign_type'=>"RSA2",
            //支付宝网关
            'gateway_url' => "https://openapi.alipay.com/gateway.do",
            //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
            'alipay_public_key' => "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAiSlxfxAOzHk6NZMjNIVuZq8b1HLxm67f4MyK/KC+f8JXUOdsgbm6wtw3vPVci3OTPrXq99IXjOUsDJwLgxzfazEqLgeKSfGizd4rG12aRNWf+6v+dsOGGuC2iq50go6wjO6zbHcPxsnl+tjlEO/IlHPKFza2Os7SQ9jCqjLMI5Ytjjy31LCa9WGwJCT/iXNdrzki7vd2x3W3m7/opiGglYzq4S1ciVEO0S5/bAfLMkGyJFTxLhVqCCX/4SFAsvNR9r9bqOTku3YeQl20FNttvfwKZdv4QK9WtGvqXfAqQ3ijH3Mi+raIdfMOVCM+VoWjcfBR3g7oi5Jj9lbanVl8FwIDAQAB",
        ];
    }

    /**
     * 生成签名
     */
    private function sign($data) {
        ksort($data);
        $string = '';
        if(is_array($data)){
            foreach ($data as $k => $v){
                if ($v != '' && $v != null && $k != 'sign'){
                    $string = $string . $k . '=' . $v . '&';
                }
            }
        }

        $string = substr($string, 0, strlen($string) - 1);
        $priKey = $this->config['merchant_private_key'];
        $res = "-----BEGIN RSA PRIVATE KEY-----\n".wordwrap($priKey, 64, "\n", true)."\n-----END RSA PRIVATE KEY-----";
        openssl_sign($string, $sign, $res, OPENSSL_ALGO_SHA256);
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * 代付业务内容
     */
    private function get_biz_content(){
        $biz_content   = [
            "out_biz_no" 	  => $this->third_params['order_number'],//商户订单号
            "payee_type" 	  => "ALIPAY_LOGONID",
            "payee_account" => $this->third_params['account_no'],//收款账户
            "payee_real_name" => $this->third_params['account_name'],//收款人姓名
            "amount" 		  => sprintf("%.2f", $this->third_params['order_amount']),//元
            "payer_show_name" => '转账',
            "remark" 		  => "往来款"
        ];
        return $biz_content;
    }

    /**
     * wap支付业务内容
     */
    private function get_pay_biz_content(){
        $biz_content   = [
            "body"    => '耐克球鞋'.rand(1, 10),//交易
            "subject"   => '体育用品',//关键字
            "out_trade_no"  => date('YmdHis'),//商户订单号
            "timeout_express"   => "5m",
            "total_amount"  => sprintf("%.2f", '0.01'),//元
            "product_code"  => "QUICK_WAP_WAY",
        ];
        return $biz_content;
    }

    /**
     * 解析第三方参数
     */
    private function parse_pay_params($method, $biz_content){
        $sys_params = array(
            "app_id" 		=> $this ->config['app_id'],
            "method" 		=> $method,
            "format"       => 'JSON',
            'return_url'  => $this->config['return_url'],
            "charset" 		=> $this->config['charset'],
            "sign_type" 	=> 'RSA2',
            "timestamp" 	=> date("Y-m-d H:i:s"),
            "version" 		=> '1.0',
            "notify_url"  => $this->config['notify_url'],
            "biz_content" 	=> json_encode($biz_content, JSON_UNESCAPED_UNICODE),
        );
        return $sys_params;
    }

    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    protected function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }

    protected function getSignContent($params) {
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);
        return $stringToBeSigned;
    }

    /**
     *  rsaCheckV1 & rsaCheckV2
     *  验证签名
     *  在使用本方法前，必须初始化AopClient且传入公钥参数。
     *  公钥是否是读取字符串还是读取文件，是根据初始化传入的值判断的。
     **/
    protected function rsa_check($params, $signType = 'RSA2'){
        $sign = $params['sign'];
        $params['sign_type'] = null;
        $params['sign'] = null;
        return $this->verify($this->getSignContent($params), $sign, $signType);
    }

    /**
     * 异步验签
     *
     * @param $data
     * @param $sign
     * @param string $signType
     * @return bool
     */
    protected function verify($data, $sign, $signType = 'RSA2'){
        $res = "-----BEGIN PUBLIC KEY-----\n" .wordwrap($this->config['alipay_public_key'], 64, "\n", true) ."\n-----END PUBLIC KEY-----";

        //调用openssl内置方法验签，返回bool值
        if ("RSA2" == $signType) {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
        } else {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        }
        return $result;
    }

    /**
     * 解析第三方参数
     */
    private function parse_params($method, $biz_content){
        $sys_params = array(
            "app_id" 		=> $this ->config['app_id'],
            "method" 		=> $method,
            "sign_type" 	=> 'RSA2',
            "version" 		=> '1.0',
            "timestamp" 	=> date("Y-m-d H:i:s"),
            "biz_content" 	=> json_encode($biz_content),
            "charset" 		=> 'UTF-8',
            'format'       => 'JSON',
        );
        return $sys_params;
    }


    /**
     * 建立请求，以表单HTML形式构造（默认）
     * @param $para_temp 请求参数数组
     * @return 提交表单HTML文本
     */
    public function order_recharge(){
        exit('hh');
        $sys_params = $this->parse_pay_params('alipay.trade.wap.pay', $this->get_pay_biz_content());
        $sys_params['sign'] = $this->sign($sys_params);
        $this->write_log('表单提交下单', $sys_params);

        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='".$this->config['gateway_url']."?charset=".trim($this->config['charset'])."' method='POST'>";
        foreach ($sys_params as $key=>$val){
            if (false === $this->checkEmpty($val)) {
                //$val = $this->characet($val, $this->postCharset);
                $val = str_replace("'","&apos;",$val);
                //$val = str_replace("\"","&quot;",$val);
                $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
            }
        }

        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml."<input type='submit' value='ok' style='display:none;''></form>";
        $sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";
        return $sHtml;
    }

    /**
     * APP sdk生成订单
     */
    public function sdk_order(){
        $this->config['app_id'] = '2021001156604692';
        $this->config['merchant_private_key'] = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCwvTrefT4zAJBrQS0LSUnnet9TUxiiUtMRUjg2aAF8BW/MO4491YROmfnNJcG4e7sn2ouWMRK1E1HGmNfTMl6uo0lpQF4EMf2jDakLn/mqQa8roLv2RIY7rPN/b5EgTE4ePV411qZ0nthPiBF0KSRWhdjNhnjQVv8DGLff5PG17YMK2aD1Y5MhI2RpnrfGUfTSfOj5GxA7XEu1N53LGhGcMh8rykaKDqrVTj25pAwlht9F4WIZDs5uGKQxntLZUnIAU1UFdfgL1PCWu6oLGn58uCfsEErw2UzJnZM93+8i4XS8U0wtHwea6Hnw29urSAK5VMtJolO9/c9aVanGNqTLAgMBAAECggEAF4K0qBAlDAfRLlNXOJ8hM6fGuYDeUAmQhkdXrvUa7XKTsqlhfJxAo2wcLMwO/wmGlgCefPY+NNRDFpYSb8fNjJGwHE4xs4Eq6lvQ1vkK7zkZokYZeuMWsC2LnrIqrg84fRFQzPym2/Cdecbx+2/Vo6TeeEihHaXU+oZ5P1FnTfn3I0xywDwwBrAVgUTVVQ1L04Kcds22t4EUZPMTYybmfdM8IfscecOf1OLqlrM1hezNtq7NsM5oOD7oe32ZETd5OHdGFKBh1nKX1AT9zGaCwQrEgC+Gq55RiYNPK0d6IRfPYbhXU2CYynWFfRbBvbtcvDjDmNzQdtes2voMez5rUQKBgQD4iH6wdu4DPQNoOXrDUXKKMHghrSLdhJNdOGwcVaTBUIkyLdFCmHOzz3HrOBCOUZqPFrUSPPy61feIYn68Uw8yFO4B+5HZzZC+CncoKg3es3Rnl9bLFR4MXwwyebh+JPnpV3XmapXVGqZWVTU4XlfazGmDUwZPqfAQmoJh4//xvwKBgQC2DI6PkiolcsZM15EsXrah/Z+HxOMv6wBB4cQJhwKCQK+BMERZ9MYVfP/8erX1e/7/6q4IkvM2se/fTnKWAFgz/9Gp19cgGSMQ79kY467b0geCOXGAeIJPKYeq7Hth8MA9pog1q0gP0eHxQmAjfeLAhAstIpXegHak466mVzv39QKBgQCd2kvlFtSbd/AnDYL8dUmznY9fjFD9s0vJxKFd6cOICPfqyBEGJEAwr4xiYqyZSBlL0pdVKyk2HdpnZG+se3DGVWbKGZeMZ7UMDyeZegRvMzm25kjFmfcI0oGzuX3FmQSmASfgHkhmHtQRN3NjBwDz9ir5/wyeIohYc1pmhGK2jwKBgQCG/BSQ3B4oGkxzGbvpHGlq/7XUY+bY1vUf5JkJP3RaxD/eGL31vYtKz563xP06grB3bbmRXfS8738fIvnPw32jQOJjf0lh4YGgw1dEHz6+e6NZqeJBEhn9PJv93s81td+1Vs/Ui6YpJMTVsRO7/VGu0bm/w89AZhyS8sfDSxeKKQKBgBz4h+5sjWXno96sq/fmIyOgUS9gk9qozG7dfloDxHGtb74aAwsILiI+b9Tk/XvYLLHPO1P5MeGUWSxXmy7j5uqSB2SdZNwJmitQumeqfKCv/Zsps48ex4GJq19ZlVQrgzRqqHub19DHXaFm0Lv9mmrgNJuxRBMftT56/v49Lnyl';
        $this->config['alipay_public_key'] = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAru3yg0YTZSl92uUu3CCOD7lRwiPIKFW25YG7H23iwzpua281WpAPpWmDKg6dOdEKGrU8vLMhmTYSy8X/L91TzV7qjVQVwTqUsU5VrD35jqpR8tiEIdKlkr3Hjca2yccQZsbjS6y/Q4NjFeE9LzXWUaQCLh9JR9ngSFr+UxZ3AsiZ+Mg1qcCr7yrgNO2D7xTQnXFmPsQix2ruD9lWnsQWdtOHRBEaKL9z7moJhWzQUaDhopgGtBx8FcQlSInFfSWE3e0rRYzXJpjDErid6MVy8fmNIMHM2YQYzvP3U+p2jFI4JFyYuvG8A6sx7BvcrVjGbu6V7AbH1zQXWeR7VPw4dwIDAQAB';

        $params['app_id'] = $this ->config['app_id'];
        $params['method'] = 'alipay.trade.app.pay';
        $params['format'] = 'json';
        $params['sign_type'] = $this->config['sign_type'];
        $params['timestamp'] = date("Y-m-d H:i:s");
        $params['alipay_sdk'] = 'alipay-sdk-php-20200415';
        $params['charset'] = $this->config['charset'];
        $params['version'] = '1.0';
        $params['notify_url'] = $this->config['notify_url'];
        $params['app_auth_token'] = null;

        $biz_content = [
            "body"    => '李宁服装'.rand(1, 10),//交易
            "subject"   => '衣服用品',//关键字
            "out_trade_no"  => date('YmdHis'),//商户订单号
            "timeout_express"   => "5m",
            "total_amount"  => '0.01',//元
            "product_code"  => "QUICK_MSECURITY_PAY",
        ];
        $params['biz_content'] = json_encode($biz_content, JSON_UNESCAPED_UNICODE);

        $params['sign'] = $this->sign($params);
        $this->write_log('APP提交下单', $params);

        return http_build_query($params);
    }

    //同步返回
    public function return_url(){
        $arr = $_GET;
        $this->write_log('支付同步返回', $arr);
        $result = $this->rsa_check($arr);
        if($result) {//验证成功
            exit('success');
        }else{
            exit('fail');
        }
    }

    //异步返回
    public function notify_url(){
        $this->config['app_id'] = '2021001156604692';
        $this->config['merchant_private_key'] = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCwvTrefT4zAJBrQS0LSUnnet9TUxiiUtMRUjg2aAF8BW/MO4491YROmfnNJcG4e7sn2ouWMRK1E1HGmNfTMl6uo0lpQF4EMf2jDakLn/mqQa8roLv2RIY7rPN/b5EgTE4ePV411qZ0nthPiBF0KSRWhdjNhnjQVv8DGLff5PG17YMK2aD1Y5MhI2RpnrfGUfTSfOj5GxA7XEu1N53LGhGcMh8rykaKDqrVTj25pAwlht9F4WIZDs5uGKQxntLZUnIAU1UFdfgL1PCWu6oLGn58uCfsEErw2UzJnZM93+8i4XS8U0wtHwea6Hnw29urSAK5VMtJolO9/c9aVanGNqTLAgMBAAECggEAF4K0qBAlDAfRLlNXOJ8hM6fGuYDeUAmQhkdXrvUa7XKTsqlhfJxAo2wcLMwO/wmGlgCefPY+NNRDFpYSb8fNjJGwHE4xs4Eq6lvQ1vkK7zkZokYZeuMWsC2LnrIqrg84fRFQzPym2/Cdecbx+2/Vo6TeeEihHaXU+oZ5P1FnTfn3I0xywDwwBrAVgUTVVQ1L04Kcds22t4EUZPMTYybmfdM8IfscecOf1OLqlrM1hezNtq7NsM5oOD7oe32ZETd5OHdGFKBh1nKX1AT9zGaCwQrEgC+Gq55RiYNPK0d6IRfPYbhXU2CYynWFfRbBvbtcvDjDmNzQdtes2voMez5rUQKBgQD4iH6wdu4DPQNoOXrDUXKKMHghrSLdhJNdOGwcVaTBUIkyLdFCmHOzz3HrOBCOUZqPFrUSPPy61feIYn68Uw8yFO4B+5HZzZC+CncoKg3es3Rnl9bLFR4MXwwyebh+JPnpV3XmapXVGqZWVTU4XlfazGmDUwZPqfAQmoJh4//xvwKBgQC2DI6PkiolcsZM15EsXrah/Z+HxOMv6wBB4cQJhwKCQK+BMERZ9MYVfP/8erX1e/7/6q4IkvM2se/fTnKWAFgz/9Gp19cgGSMQ79kY467b0geCOXGAeIJPKYeq7Hth8MA9pog1q0gP0eHxQmAjfeLAhAstIpXegHak466mVzv39QKBgQCd2kvlFtSbd/AnDYL8dUmznY9fjFD9s0vJxKFd6cOICPfqyBEGJEAwr4xiYqyZSBlL0pdVKyk2HdpnZG+se3DGVWbKGZeMZ7UMDyeZegRvMzm25kjFmfcI0oGzuX3FmQSmASfgHkhmHtQRN3NjBwDz9ir5/wyeIohYc1pmhGK2jwKBgQCG/BSQ3B4oGkxzGbvpHGlq/7XUY+bY1vUf5JkJP3RaxD/eGL31vYtKz563xP06grB3bbmRXfS8738fIvnPw32jQOJjf0lh4YGgw1dEHz6+e6NZqeJBEhn9PJv93s81td+1Vs/Ui6YpJMTVsRO7/VGu0bm/w89AZhyS8sfDSxeKKQKBgBz4h+5sjWXno96sq/fmIyOgUS9gk9qozG7dfloDxHGtb74aAwsILiI+b9Tk/XvYLLHPO1P5MeGUWSxXmy7j5uqSB2SdZNwJmitQumeqfKCv/Zsps48ex4GJq19ZlVQrgzRqqHub19DHXaFm0Lv9mmrgNJuxRBMftT56/v49Lnyl';
        $this->config['alipay_public_key'] = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAru3yg0YTZSl92uUu3CCOD7lRwiPIKFW25YG7H23iwzpua281WpAPpWmDKg6dOdEKGrU8vLMhmTYSy8X/L91TzV7qjVQVwTqUsU5VrD35jqpR8tiEIdKlkr3Hjca2yccQZsbjS6y/Q4NjFeE9LzXWUaQCLh9JR9ngSFr+UxZ3AsiZ+Mg1qcCr7yrgNO2D7xTQnXFmPsQix2ruD9lWnsQWdtOHRBEaKL9z7moJhWzQUaDhopgGtBx8FcQlSInFfSWE3e0rRYzXJpjDErid6MVy8fmNIMHM2YQYzvP3U+p2jFI4JFyYuvG8A6sx7BvcrVjGbu6V7AbH1zQXWeR7VPw4dwIDAQAB';

        $arr = $_POST;
        if(isset($arr['method'])){
            unset($arr['method']);
        }
        $this->write_log('支付异步返回', $arr);

        $arr = json_decode('{"gmt_create":"2020-06-19 15:53:42","charset":"UTF-8","seller_email":"jiprf85@163.com","subject":"衣服用品","sign":"QN1CjCvKaOsxFRfAK1514H3+wmBJClf\/eiq+fyMsL33PTEeeR866fZnzjPxtcJ+viqigot6Fe8\/Q4cy0jCxEmR8MIS97\/g91lB9PU3RzJ7xHG263b\/L0LytJhGoVsOpXZTQEYpyPkiaoWDvWxmRYzobxCkIbPkPtHdhDfoCeGwi9tN8+n8F3S9OacoOReGNdlCkK7YdKeQUH7BmmZYnAzqgy\/XKJfpjPoRsS2dLX7U5p7kYDBARyxVXADwdEmthBBq2bWr1ugQrH18DcrXxsp1TEjJ+FGDd5uPnxD1I2BOe2irfFIiibDogyjKy9zkkV4mq6v+LBRiaS\/bOBKLqUaw==","body":"李宁服装2","buyer_id":"2088300982057482","invoice_amount":"0.01","notify_id":"2020061900222155343057481403369841","fund_bill_list":"[{\"amount\":\"0.01\",\"fundChannel\":\"ALIPAYACCOUNT\"}]","notify_type":"trade_status_sync","trade_status":"TRADE_SUCCESS","receipt_amount":"0.01","app_id":"2021001156604692","buyer_pay_amount":"0.01","sign_type":"RSA2","seller_id":"2088831215425962","gmt_payment":"2020-06-19 15:53:43","notify_time":"2020-06-19 15:56:41","version":"1.0","out_trade_no":"20200619155323","total_amount":"0.01","trade_no":"2020061922001457481406912744","auth_app_id":"2021001156604692","buyer_logon_id":"ekc***@163.com","point_amount":"0.00"}', true);

        $result = $this->rsa_check($arr);
        if($result) {//验证成功
            if($arr['trade_status'] == 'TRADE_FINISHED') {
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //请务必判断请求时的total_amount与通知时获取的total_fee为一致的
                //如果有做过处理，不执行商户的业务程序

                //注意：
                //退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
            }else if($arr['trade_status'] == 'TRADE_SUCCESS') {
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //请务必判断请求时的total_amount与通知时获取的total_fee为一致的
                //如果有做过处理，不执行商户的业务程序
                //注意：
                //付款完成后，支付宝系统发送该交易状态通知
            }
            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
            exit('success');
        }else{
            exit('fail');
        }
    }

    //查询
    public function order_query(){
        $biz_content = [
            'out_biz_no' => $this->third_params['order_number'],
        ];
        $sys_params = $this->parse_params('alipay.fund.trans.order.query', $biz_content);
        $sys_params['sign'] = $this->sign($sys_params);

    }

    //form表单提交
    protected function form_post($url, $data, $timeout = 15){
        $start_time = microtime(true);
        $post_string = http_build_query($data);
        //初始化 curl
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded;charset=UTF-8']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);//获取请求状态码
        if($result === false){
            $curl_error = curl_error($ch);//获取CURL请求错误
        }else{
            $curl_error = '';
        }
        curl_close($ch);
        $end_time = microtime(true);
        return ['http_code'=>$http_code, 'result'=>$result, 'curl_error'=>$curl_error, 'cost_time'=>bcsub($end_time, $start_time, 2).'s'];
    }

    //请确保项目文件有可写权限，不然打印不了日志。
    function write_log($title='', $text='') {
        file_put_contents (  ROOT_PATH."/data/logs/php/alipay.txt", date( "Y-m-d H:i:s") .
            " {$title} " . (is_array($text) ? json_encode($text, JSON_UNESCAPED_UNICODE) : $text) . "\n", FILE_APPEND );
    }
}

$alipay = new ALIPAY();
$method = isset($_GET['method']) ? $_GET['method'] : 'notify_url';

if($method == 'order_recharge'){
    $result = $alipay->order_recharge();
    exit($result);
}else if($method == 'sdk_order'){
    $result = $alipay->sdk_order();
    exit($result);
}else if($method == 'return_url'){
    $alipay->return_url();
}else if($method == 'notify_url'){
    $alipay->notify_url();
}else{
    $alipay->notify_url();
//    exit('error');
}

