<?php
/**
 * Author: Taylor 2018-12-21
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * 恒润支付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class HENGRZF extends BASES
{
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {

//        $param = ['order_number' => '201811251923083890', 'money' => 1000, 'third' => '929',
//            'return_url' => 'http://www.baidu.com', 'back_code' => '', 'client_ip' => '127.0.0.1'];
//        print_r(md5(http_build_query($param) . 'cf28e0cede9662e034bd68a55acec1213'));exit;

        $trade_codes = [
            'WXH5'=>'80001',//微信H5
            'ALIH5'=>'80002',//支付宝H5
            'WXSCAN'=>'60001',//微信扫码
            'ALISCAN'=>'60002',//支付宝扫码
            'QQSCAN'=>'60003',//QQ扫码
            'QQH5'=>'80003',//QQ H5
            'JDSCAN'=>'60004',//京东扫码
            'JDH5'=>'80004',//京东H5
            'UPSCAN'=>'60005',//银联扫码
            'UPH5'=>'80005',//银联扫码
            'UPQUICK'=>'60006',//银联快捷
        ];//支付方式
        $this->parameter = array(
            'appID' => $this->partnerID,//商户id,由盈支付分配
            'tradeCode'=> $trade_codes[$this->data['bank_data']],//支付产品类型编码
            'randomNo'=> (string)rand(1000,9999),//14位的保证数据验签的安全性，随机生成的随机数
            'outTradeNo' => $this->orderID,//商户系统订单号
            'totalAmount' => $this->money,//订单金额 单位：分
            'productTitle'=> $this->orderID,//商品标题，字符长度限定20字符
            'notifyUrl' => $this->notifyUrl,//下行异步通知的地址，需要以http(s)://开头且没有任何参数
            'tradeIP' => $this->data['client_ip'],//交易请求IP
//            'frontUrl' => $this->returnUrl,//下行同步通知过程的返回地址(在支付完成后盈支付接口将会跳转到的商户系统连接地址)。
        );
        $this->parameter['sign'] = $this->sytMd5($this->parameter, $this->key);//32位小写MD5签名值
    }

    //生成支付签名
    public function sytMd5($data, $signKey)
    {
        ksort($data);//排列数组 a-z排序
        $data = implode("|", $data);//拼接加密字符串
        $sign = md5($data . '|' . $signKey);//签名数据   md5(value1|value2|value3|....|md5Key)
        return strtoupper($sign);//转大写签名
    }


    //返回参数
    public function parseRE()
    {
        $res = $this->httpspost($this->payUrl, $this->parameter);//发起请求
//        $res = '{"appID":"HR181220151654079","outTradeNo":"201812212000023865","payURL":"http://api.kuaile8899.com:8088/pay/toWapCode.shtml?sign=FC019092C6B5A25E4EA680C1E94B488D","sign":"B9308D84EEF04807A5BE2A74A6B12D2E","stateCode":"0000","stateInfo":"提交成功"}';
//        $res = '{"stateCode":"9999","stateInfo":"ZFB_GZH_WAP不能低于:50元"}';
        $re = json_decode($res, true);
        if ($re['stateCode'] == '00000') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
            $this->return['str'] = $re['payURL'];//支付地址
        } else {
            $this->return['code'] = 8;
            $this->return['msg'] = 'HENGRZF:' . $re['stateInfo'] ? $re['stateInfo'] : '';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
//        $str = '{"NoticeParams":"{\"appID\":\"HR181220151654079\",\"outTradeNo\":\"201812212212151650\",\"payCode\":\"0000\",\"payDatetime\":\"2018-12-21 22:12:33\",\"productTitle\":\"201812212212151650\",\"totalAmount\":\"5100\",\"tradeCode\":\"80002\",\"sign\":\"9E0B2F6FF76333F7461B2827311E7AC0\"}"}';
//        $pieces = json_decode($str, true);
        $pieces = json_decode($pieces['NoticeParams'], true);//{"appID":"HR181220151654079","outTradeNo":"201812212212151650","payCode":"0000","payDatetime":"2018-12-21 22:12:33","productTitle":"201812212212151650","totalAmount":"5100","tradeCode":"80002","sign":"9E0B2F6FF76333F7461B2827311E7AC0"}
//        $pieces = (array)json_decode(file_get_contents('php://input'), true);
        $res = [
            'status' => 1,
            'order_number' => $pieces['outTradeNo'],//商户订单号
            'third_order' => $pieces['outTradeNo'],//第三方的支付订单号
            'third_money' => $pieces['totalAmount'],//支付金额，分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['outTradeNo']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['payCode'] != '0000'){
            $res['status'] = 0;
            $res['error'] = '支付失败';
        }
        if (self::retrunVail($pieces, $config['key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    //验签
    public function retrunVail($responseData, $md5Key)
    {
        $re_sign = $responseData['sign'];//返回签名
        $arr = array();
        foreach ($responseData as $key=>$v){
            if ($key !== 'sign'){//删除签名
                $arr[$key] = $v;
            }
        }
        ksort($arr);
        $data_check = implode("|", $arr);
        $sign_check = md5($data_check . '|' . $md5Key);//签名数据   md5(value1|value2|value3|....|md5Key)
        $sign_check = strtoupper($sign_check);
        if ($sign_check == $re_sign){
            return true;
        }else{
            return false;
        }
    }

    /**
     * PHP发送Json对象数据
     *
     * @param $url 请求url
     * @param $jsonStr 发送的json字符串
     * @return string
     */
    function httpspost($url, $param) {
        if (empty($url) || empty($param)) {
            return false;
        }
        $param = json_encode($param);
        try {

            $ch = curl_init();//初始化curl
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'ApplyParams=' . $param);
//            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $data = curl_exec($ch);//运行curl
            curl_close($ch);

            if (!$data) {
                $this->re = "请求出错：url={$url},param={$param}";
            }
            $this->re = $data;
            return $data;
        } catch (\Exception $e) {
            $this->re = $e->getMessage();
        }
    }
}