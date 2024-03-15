<?php
/**
 * Author: Taylor 2019-01-20
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * 永顺支付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class YSZF extends BASES
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
        $data=array(
            'requestId'		=>	$this->orderID,//
            'orgId'			=>	$this->data['app_id'],//机构号
            'productId'		=>	$this->data['action'] ? $this->data['action'] : '0100',//0100	扫码支付D0，9701	订单查询
            'dataSignType'	=>	'1',//正式
            'timestamp'		=>	date('YmdHis',time())
        ); //验证数据结构

        $busMap = array(
            'merno'=>$this->partnerID,//平台进件返回商户号
            'bus_no'=>$this->data['bank_data'],//业务编号
            'amount'=>$this->money,//交易金额，单位分
            'goods_info'=>$this->orderID,//商品名称
            'order_id'=>$this->orderID,//商户自定义订单号
            'return_url'=>$this->returnUrl,//前端跳转回调地址
            'notify_url'=>$this->notifyUrl,//后台通知回调地址
        );
        $businessData = json_encode($busMap);
        $businessData = $this->encrypt($businessData, $this->data['key']);//加密
        $businessData = urlencode($businessData);//加密结果 UrlEncode
        $data['businessData'] = $businessData;
        $data['signData']=$this->sign($data);
        $this->parameter = $data;
    }

    //生成支付签名
    public function sign($data)
    {
        ksort($data);
        $b = '';
        $lastvalue=end($data);
        foreach($data as $key=>$value){
            if($value==$lastvalue){
                $b .= $key .'=' .$value;
            }else{
                $b .= $key .'=' .$value.'&';
            }
        }
        $b .= $this->data['pub_key'];
        $signData = md5($b);
        $signData = strtoupper($signData);
        return $signData;
    }

    //发起请求，返回参数
    public function parseRE()
    {
        $res = $this->httpspost($this->payUrl, $this->parameter);//发起请求
//        {"key":"200999","msg":"每笔交易最低金额为：10.00元","requestId":"201901201528389974","respCode":"00","respMsg":"通讯成功","status":"2"}
//        $res = '{"key":"05","msg":"获取成功","requestId":"201901201528389970","respCode":"00","respMsg":"通讯成功","result":"{\"url\":\"http://api.ys666999.net/open-gateway/redirect/go?_t=1547974937843021740\"}","status":"3"}';
//        $res = '{"key":"400003","msg":"调用支付失败","requestId":"201901201528389970","respCode":"00","respMsg":"通讯成功","status":"2"}';
        $re = json_decode($res, true);
        if (in_array($re['key'], ['00', '05']) && !empty($re['result'])) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
            $url = json_decode($re['result'], true);
            $this->return['str'] = $url['url'];//支付地址
        } else {
            $this->return['code'] = 8;
            $this->return['msg'] = 'YSZF:' . ($re['msg'] ? $re['msg'] : '');
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //异步回调的签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['order_id'],//商户系统内部订单号
            'third_order' => $pieces['plat_order_id'],//支付系统流水号
            'third_money' => $pieces['amount'],//订单金额，分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['order_id']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['trade_status'] != '0'){
            $res['status'] = 0;
            $res['error'] = '支付失败';
        }
        if (self::retrunVail($pieces, $config['pub_key'])) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    //验签
    public function retrunVail($data, $md5Key)
    {
        $sys_sign = $data['sign_data'];
        unset($data['sign_data']);
        ksort($data);
        $lastvalue = end($data);
        $b = '';
        foreach($data as $key=>$value){
            if($value == $lastvalue){
                $b .= $key .'='.$value;
            }else{
                $b .= $key .'='.$value.'&';
            }
        }
        $b .= $md5Key;
        $signData = md5($b);
        if($signData == $sys_sign){
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
        $param = http_build_query($param);
        try {
            $ch = curl_init();//初始化curl
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
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

    //加密
    public function encrypt($str,$key){
        $size = mcrypt_get_block_size(MCRYPT_DES,MCRYPT_MODE_ECB);
        $str = $this->PaddingPKCS7($str);
        $key = str_pad($key,8,'0');
        $td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_ECB, '');
        @mcrypt_generic_init($td, $key, '');
        $data = mcrypt_generic($td, $str);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $data = base64_encode($data);
        return $data;
    }

    //解密
    public function decrypt($encrypted,$key){
        $encrypted = base64_decode($encrypted);
        $key = str_pad($key,8,'0');
        $td = mcrypt_module_open(MCRYPT_DES,'',MCRYPT_MODE_ECB,'');
        $ks = mcrypt_enc_get_key_size($td);
        @mcrypt_generic_init($td, $key, '');
        $decrypted = mdecrypt_generic($td, $encrypted);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $y = $this->pkcs5_unpad($decrypted);
        return $y;
    }

    public function PaddingPKCS7($data) {
        $block_size = mcrypt_get_block_size(MCRYPT_DES, MCRYPT_MODE_ECB);
        $padding_char = $block_size - (strlen($data) % $block_size);
        $data .= str_repeat(chr($padding_char),$padding_char);
        return $data;
    }
}