<?php
/**
 * 石墨支付
 * @author Taylor 2019-04-14
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 石墨支付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class SMZF extends BASES
{
    private $httpCode = '';

    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->parseRE();
    }

    //初始化参数
    public function initParam()
    {
        $this->parameter = array(
            'pid' => $this->partnerID,//商户编号
            'payType' => $this->data['bank_data'],//商户付款类型，1-支付宝 2-微信
            'payScene' => 1,//商户付款场景，1-wap 2-pc
            'bizOrderNo' => $this->orderID,//商户订单号
            'createTime' => date('Y-m-d H:i:s'),//付款请求创建时间 yyyy-MM-dd HH:mm:ss 日期格式
            'amount' => $this->money/100,//付款金额，单位元，格式为 Decimal （float），最多两位小数
            'returnUrl' => $this->returnUrl,//同步返回地址，付款成功后，将跳转至该地址
            'notifyUrl' => $this->notifyUrl,//回调地址
        );
        $this->parameter['sign'] = $this->sytMd5($this->parameter, $this->key);//32位小写MD5签名值
        $this->parameter['signMode'] = 2;//32位小写MD5签名值
    }

    //生成支付签名
    public function sytMd5($array, $signKey)
    {
        $array['key'] = $signKey;
        ksort($array);
        $str = "";
        foreach($array as $key=>$val){
            if (NULL != $val && "" != $val) {
                $str .= $key . "=" . $val . "&";
            }
        }
        $str = rtrim($str, "&");
        return strtoupper(md5($str));
    }


    //返回参数
    public function parseRE()
    {
        $res = $this->httpspost($this->payUrl, $this->parameter);//发起请求
//        $res = '{"code":"0","message":"SUCCESS","data":{"bizOrderNo":"201904141602325645","payType":"2","amount":"300.00","pid":"sh008","orderNo":"2019041420190414567022805661646848","qrcode":"alipays://platformapi/startapp?appId=20000067&url=http%3A%2F%2F47.75.78.33%3A8910%2Fppay%2Fuser%2Faliphb%3FqCode%3DSHISWHZalGgkIUroiGLMxQYFPOcLRVaI","payUrl":"http://47.75.78.33:8910/ppay/user/qOrder?qCode=SHISWHZalGgkIUroiGLMxQYFPOcLRVaI","expireTime":"2019-04-14 16:31:11","totalFee":"10.50","account":"平头哥"}}';
//        {"code":"2","message":"错误的订单金额，当前有效金额范围300.00 - 5000.00","data":null}
//        {"code":"4","message":"签名信息错误","data":null}
        $re = json_decode($res, true);
        if ($re['code'] == '0') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
            $this->return['str'] = $re['data']['payUrl'];//支付宝H5跳转地址
        } else {
            $this->return['code'] = 8;
            $this->return['msg'] = 'SMZF:' . (isset($re['message']) ? $re['message'] : '');
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //异步回调校验
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['bizOrderNo'],//商户订单号
            'third_order' => $pieces['orderNo'],//平台订单号
            'third_money' => $pieces['amount'] * 100,//支付金额为元
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($res['order_number']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
            return $res;
        }
        if($pieces['status'] != '0'){
            $res['status'] = 0;
            $res['error'] = '支付失败';
            return $res;
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
    public function retrunVail($array, $signKey)
    {
        $sys_sign = $array['sign'];
        unset($array['sign']);
        $array['key'] = $signKey;
        ksort($array);
        $str = ""; //key&value
        foreach($array as $key=>$val){
            if (NULL != $val && "" != $val) {
                $str.= $key . "=" . $val . "&";
            }
        }
        $str = rtrim($str, "&");
        $my_sign = strtoupper(md5($str));
        return $my_sign == $sys_sign;
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
            $this->httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
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