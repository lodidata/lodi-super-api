<?php
/**
 * 易付: Taylor 2019-02-26
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 易付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class YIFU extends BASES
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
            'uid' => $this->partnerID,//用户ID
            'money' => sprintf("%.2f", $this->money / 100),//交易金额，单位：元，保留两位小数
            'channel' => $this->data['bank_data'],//支付渠道
            'post_url' => $this->notifyUrl,//通知接口地址
            'return_url' => $this->returnUrl,//跳转地址
            'order_id' => $this->orderID,//商户订单号
//            'order_uid' => '',//用户编号
//            'goods_name' => '',//商品名
        );
        $this->parameter['key'] = $this->sytMd5($this->parameter, $this->key);//32位小写MD5签名值
    }

    //生成支付签名
    public function sytMd5($array, $signKey)
    {
//        $str = md5($array['uid'].$signKey.$array['money'].$array['channel'].$array['post_url'].$array['return_url'].$array['order_id'].$array['order_uid'].$array['goods_name']);
        $str = md5($array['uid'].$signKey.$array['money'].$array['channel'].$array['post_url'].$array['return_url'].$array['order_id']);
        return $str;
    }


    //返回参数
    public function parseRE()
    {
        $res = $this->httpspost($this->payUrl, $this->parameter);//发起请求
        //{"msg":"接口调用成功","code":200,"qrcode":"http://www.easypays.vip/pay.html?o=f838b30ac3e1bf10f6e472490c904a59","pcQrcode":"http://www.easypays.vip/pay.html?o=f838b30ac3e1bf10f6e472490c904a59","h5Qrcode":"alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode=http%3A%2F%2Fwww.easypays.vip%2Fpay.html%3Fo%3Df838b30ac3e1bf10f6e472490c904a59","channel":"alipaybag","money":200.0,"realName":"","tips":"请使用普通红包进行付款！","remark":""}
        $re = json_decode($res, true);
        if ($re['code'] == 200) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
            $this->return['str'] = $re['pcQrcode'];
        } else {
            $this->return['code'] = 8;
            $this->return['msg'] = 'YIFU:' . (isset($re['msg']) ? $re['msg'] : '');
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
        //支付成功才会回调
        $res = [
            'status' => 1,
            'order_number' => $pieces['order_id'],//商户订单号
            'third_order' => $pieces['trade_no'],//第三方的支付订单号
            'third_money' => $pieces['money'] * 100,//支付金额
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['order_id']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
//        if($pieces['key'] != '10000'){
//            $res['status'] = 0;
//            $res['error'] = '支付失败';
//        }
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
        $sys_sign = $array['key'];
        $my_sign = md5($signKey.$array['trade_no'].$array['order_id'].$array['channel'].$array['money'].$array['remark'].$array['order_uid'].$array['goods_name']);
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