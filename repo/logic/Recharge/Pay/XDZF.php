<?php
/**
 * 现代支付: Taylor 2019-02-22
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 现代对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class XDZF extends BASES
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
            'service' => 'getCodeUrl',//接口名称
            'merchantNo' => $this->partnerID,//商户编号
            'bgUrl' => $this->notifyUrl,//服务器接收支付结果的后台地址
            'version' => 'V2.0',//网关版本
            'payChannelCode' => $this->data['bank_data'],//支付通道编码
            'orderNo' => $this->orderID,//商户订单号
            'orderAmount' => $this->money,//以"分"为单位的整型
            'curCode' => 'CNY',//目前只支持人民币 固定值：CNY
            'orderTime' => date('YmdHis'),//订单时间
            'orderSource' => 2,//订单来源：1PC 2手机
            'signType' => 1,//签名类型：1:MD5, 2:RSA
        );
        $this->parameter['sign'] = $this->sytMd5($this->parameter, $this->key);//32位小写MD5签名值
    }

    //生成支付签名
    public function sytMd5($array, $signKey)
    {
        ksort($array);
        $str = ""; //key&value
        foreach($array as $key=>$val){
            if (NULL != $val && "" != $val) {
                $str.= $key . "=" . $val . "&";
            }
        }
        $str = rtrim($str, "&");
        return strtoupper(md5($str.$signKey));
    }


    //返回参数
    public function parseRE()
    {
        $res = $this->httpspost($this->payUrl, $this->parameter);//发起请求
        //{"codeUrl":"https://ds.alipay.com/?from=mobilecodec&scheme=alipays%3A%2F%2Fplatformapi%2Fstartapp%3FappId%3D10000007%26qrcode%3Dhttp%3A%2F%2F47.111.40.100%2Fgateway%2Fpay%2Flocation.do%3Fid%3D93","dealMsg":"操作成功！","sign":"FE63091BF2303D1175B9D6956EFC550C","dealCode":"10000","thirdUseOrderNo":"93"}
        //{"dealMsg":"银行或通道系统异常","sign":"527D3FEE98C3789A0D359FB31618B87F","dealCode":20033}
        $re = json_decode($res, true);
        if ($re['dealCode'] == '10000') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
            $this->return['str'] = $re['codeUrl'];//支付宝H5跳转地址
        } else {
            $this->return['code'] = 8;
            $this->return['msg'] = 'XDZF:' . (isset($re['dealMsg']) ? $re['dealMsg'] : '');
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
        $res = [
            'status' => 1,
            'order_number' => $pieces['orderNo'],//商户订单号
            'third_order' => $pieces['cxOrderNo'],//第三方的支付订单号
            'third_money' => $pieces['orderAmount'],//支付金额为分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['orderNo']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['dealCode'] != '10000'){
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
    public function retrunVail($array, $signKey)
    {
        $sys_sign = $array['sign'];
        unset($array['sign']);
        ksort($array);
        $str = ""; //key&value
        foreach($array as $key=>$val){
            if (NULL != $val && "" != $val) {
                $str.= $key . "=" . $val . "&";
            }
        }
        $str = rtrim($str, "&");
        $my_sign = strtoupper(md5($str.$signKey));
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