<?php
/**
 * Author: Taylor 2019-01-17
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * 无限付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class WXPAY extends BASES
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
//        $trade_codes = [
//            'ALIH5'=> 1,//支付宝H5
//            'ALISCAN'=> 2,//支付宝扫码
//        ];//支付方式
        $this->parameter = array(
            'shopAccountId' => $this->partnerID,//商家ID
            'shopUserId' => 0,//商家用户ID
            'amountInString' => sprintf("%.2f", $this->money / 100),//订单金额，单位元，如：0.01表示⼀一分钱；
            'payChannel' => 'alipay',//支付宝：alipay；支付宝转银行：bank
            'shopNo' => $this->orderID,
            'shopCallbackUrl' => $this->notifyUrl,
            'returnUrl' => $this->returnUrl,
            'target'=> 3, //跳转方式 1，手机跳转 2，二维码展示 3，json展示
        );
        $this->parameter['sign'] = $this->sytMd5($this->parameter, $this->key);//32位小写MD5签名值
    }

    //生成支付签名
    public function sytMd5($data, $signKey)
    {
        unset($data['shopCallbackUrl']);
        unset($data['returnUrl']);
        unset($data['shopNo']);
        unset($data['target']);
        $data['key'] = $signKey;
        $singStr = implode($data);
        return md5($singStr);
    }


    //返回参数
    public function parseRE()
    {
        $res = $this->httpspost($this->payUrl, $this->parameter);//发起请求
//        $res = '{"code":0,"url":"https:\/\/render.alipay.com\/p\/s\/i?scheme=alipayqr%3A%2F%2Fplatformapi%2Fstartapp%3FsaId%3D10000007%26clientVersion%3D3.7.0.0718%26qrcode%3Dhttps%253A%252F%252Fhnnaaq.ltd%252Fpay%252Fjump%253Fmoney%253D1.00%2526trade_no%253D2019011716175290489%2526mark%253D","amount":"1.00","out_trade_no":"201901171617238542","trade_no":"2019011716175290489"}';
//        $res = '{"code":20002,"msg":"订单提交失败：订单提交失败：商户：单笔交易金额[1.00\/5000.00]","time":"1544189564","data":[],"sign":"60793bf3619788c44034b55bfc3e26f4"}';
        $re = json_decode($res, true);
        if ($re['code'] == '0') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
            $this->return['str'] = $this->data['return_type'] == 'jump' ? $re['page_url'] : $re['url'];
        } else {
            $this->return['code'] = 8;
            $this->return['msg'] = 'WXF:' . $re['message'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
        //{"order_no":"2019011716585431742","user_id":"0","shop_no":"201901171658538093","money":"1.00","type":"bank","date":"2019-01-17 16:58:54","trade_no":"2019011716585431742","status":0,"sign":"76606b1caa23d9dd312cb365c97161ac"}
        $res = [
            'status' => 1,
            'order_number' => $pieces['shop_no'],//商户订单号
            'third_order' => $pieces['order_no'],//第三方的支付订单号
            'third_money' => $pieces['money'] * 100,//支付金额，以元单位，需要转为分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['shop_no']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['status'] != '0'){
            $res['status'] = 0;
            $res['error'] = '支付失败';
        }
        if (self::retrunVail($pieces, $config)) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    //验签
    public function retrunVail($pieces, $config)
    {
        $arr['shop_id'] = $config['partner_id'];//商户id
        $arr['user_id'] = $pieces['user_id'];//用户id
        $arr['order_no'] = $pieces['order_no'];//系统订单号
        $arr['sign_key'] = $config['key'];
        $arr['money'] = $pieces['money'];
        $arr['type'] = $pieces['type'];
        $singStr = implode($arr);
        return $pieces['sign'] == md5($singStr);
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