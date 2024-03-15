<?php
/**
 * Author: Taylor 2019-01-12
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * 万通支付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class WTZF extends BASES
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
        $trade_codes = [
            'ALIH5'=>['c'=>'zfb', 't'=>'MWEB'],//支付宝H5
            'ALISCAN'=>['c'=>'zfb', 't'=>''],//支付宝扫码
            'WXH5'=>['c'=>'wx', 't'=>'MWEB'],//微信H5
            'WXSCAN'=>['c'=>'wx', 't'=>''],//微信扫码
            'UPQUICK'=>['c'=>'unionpay', 't'=>''],//银联快捷
        ];//支付方式
        $arr = array(
            'orderid' => $this->orderID,//商户系统订单号
            'merid' => $this->partnerID,//商户id,由万通支付分配
            'totalfee' => $this->money/100,//订单金额，单位为：元
            'subject'=> $this->orderID,//订单标题
            'body'=> $this->orderID,//订单说明
            'paymethod'=> $trade_codes[$this->data['bank_data']]['c'],//支付方式支付宝：zfb；银联：unionpay；微信：wx；QQ钱包：qq
            'funname'=> 'prepay',//支付方法，扫码、H5填写：“prepay”
            'notifyurl' => $this->notifyUrl,//下行异步通知的地址，需要以http(s)://开头且没有任何参数
            'ip' => $this->data['client_ip'],//交易请求IP
        );
        if(!empty($trade_codes[$this->data['bank_data']]['t']))
            $arr['tradetype'] = 'MWEB';//支付宝、银联等的H5支付必填‘MWEB’，否则不用；
        ksort($arr);
        foreach($arr as $key=>$value)
        {
            $arrStr[] = $key.'='.$value;
        }
        $sign = $this->sign($arrStr, $this->key);//32位小写MD5签名值
        //生成xml字符串
        $xml = "<xml>";
        foreach($arr as $key=>$v){
            $xml .= '<'.$key.'>'.$v.'</'.$key.'>';
        }
        $xml = $xml.'<sign>'.$sign.'</sign></xml>';
        $this->parameter = $xml;
    }

    //生成支付签名
    public function sign($arr, $key)
    {
        $str='';
        sort($arr);
        foreach($arr as $v) {
            $str .= $v.'&';
        }
        $str .= 'key='.$key;
        return strtoupper(md5($str));
    }

    //发起请求，返回参数
    public function parseRE()
    {
        $res = $this->httpspost($this->payUrl, $this->parameter);//发起请求
//        $res = "<xml><flag>00</flag><outtradeno>201901131603490537387008816</outtradeno><msg>ok</msg><mweburl>http://47.100.222.47:2666/api/orderalipay/pay/orderid/13020190113160349735452222</mweburl></xml>";
        //错误请求
//        $res = "<xml><flag>2011</flag><msg>订单流水号已经被使用</msg></xml>";
//        $res = "<xml><flag>99</flag><msg>系统异常，订单金额1.0小于单笔最低限额200.0</msg></xml>";
//        $res = "<xml><flag>2008</flag><msg>第三方服务器失败，系统当前通道维护</msg></xml>";
        $re = $this->parseXML($res);
        if ($re['flag'] == '00') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
            //H5支付方式链接 mweburl 当支付方式为H5时，返回的此链接;
            //二维码链接URL shorturl 当支付方式为扫码时，返回此链接自行生成二维码，用来扫码支付
            $this->return['str'] = $this->data['return_type'] == 'jump' ? $re['mweburl'] : $re['shorturl'];//支付地址
        } else {
            $this->return['code'] = 8;
            $this->return['msg'] = 'WTZF:' . (isset($re['msg']) ? $re['msg'] : '');
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //异步回调的签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['orderid'],//商户系统内部订单号
            'third_order' => $pieces['outtranno'],//万通支付系统流水号
            'third_money' => $pieces['orderamt'] * 100,//订单金额，万通系统是元，需要转为分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['orderid']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['tradestate'] != 'TRADE_SUCCESS'){
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
        $gettxml22 = $_GET;
        $str = '';
        foreach($gettxml22 as $key=>$value)
        {
            if($key != "sign"){
                $str .= $key .'=' . $value. '&';
            }
        }
        $str .= 'key='.$md5Key;
        $sign_check = strtoupper(md5($str));

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
        $en_param = $this->encryptString($param);
        $header[] = "Content-type: text/xml;charset=UTF-8";
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $en_param);
            $data = curl_exec($ch);
            curl_close($ch);
            if (!$data) {
                $this->re = "请求出错：url={$url},param={$param}";
            }else{
                $this->re = $this->decryptString($data);
            }
            return $this->re;
        } catch (\Exception $e) {
            $this->re = $e->getMessage();
        }
    }

    //加密方法
    public function encryptString($input) {
        $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
        $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
        //$iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        $key= "1102130405061708";
        $iv = "1102130405061708";
        $pad = $size - (strlen($input) % $size);
        $input = $input . str_repeat(chr($pad), $pad);
        mcrypt_generic_init($td, $key, $iv);
        $data = mcrypt_generic($td, $input);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $data = base64_encode($data);
        //$handle  = fopen(ROOT_PATH.'a.txt','a+');
        //fwrite($handle,$data);
        return $data;
    }

    //解密方法
    public function decryptString($sStr) {
        $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
        //$iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        $sKey = "1102130405061708";
        $iv = "1102130405061708";
        $decrypted= @mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $sKey, base64_decode($sStr), MCRYPT_MODE_CBC, $iv);
        $dec_s = strlen($decrypted);
        $padding = ord($decrypted[$dec_s-1]);
        $decrypted = substr($decrypted, 0, -$padding);
        return $decrypted;
    }
}