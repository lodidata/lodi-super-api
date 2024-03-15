<?php
/**
 * Author: Taylor 2018-12-07
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * 盈支付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class YINGZF extends BASES
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

        $money=$this->money / 100;
        $this->parameter = array(
            'merchantNo' => $this->partnerID,//商户id,由盈支付分配
            'amount' => sprintf("%.2f" , $money),//单位元（人民币）,最小0.01
            'orderNo' => $this->orderID,//商户系统订单号
            'body' => '',//支付宝支付过程中展示的，支付内容，不填则显示“支付宝支付”
            'notifyUrl' => $this->notifyUrl,//下行异步通知的地址，需要以http(s)://开头且没有任何参数
            'frontUrl' => $this->returnUrl,//下行同步通知过程的返回地址(在支付完成后盈支付接口将会跳转到的商户系统连接地址)。
        );
        if($this->data['bank_data'] == 'alipay'){
            $this->parameter['returnType'] = 2;//请求的返回方式：1-页面，2-Url，默认为1
        }else{

        }
        $this->parameter['sign'] = $this->sytMd5($this->parameter, $this->key);//32位小写MD5签名值
    }

    //生成支付签名
    public function sytMd5($data, $signKey)
    {
        ksort($data);
        $dataStr = '';
        foreach ($data as $k => $v) {
            if ($k === 'sign' || empty($v)) continue;
            if (is_array($v)){
                $v = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            $dataStr .= "$k=$v&";
        }
        $dataStr = substr(mb_strtolower($dataStr), 0,strlen($dataStr) - 1).$signKey;
        return md5($dataStr);
    }


    //返回参数
    public function parseRE()
    {
        $res = $this->httpspost($this->payUrl, $this->parameter);//发起请求
//        $res = '{"code":"00000","msg":"成功","time":"1544190554","data":{"payUrl":"https:\/\/qr.alipay.com\/bax04096r6evexhijxow8079","amount":"1.00"},"sign":"a4cb90f71a433e41d5ef27c4d59d50d2"}';
//        $res = '{"code":20002,"msg":"订单提交失败：订单提交失败：商户：单笔交易金额[1.00\/5000.00]","time":"1544189564","data":[],"sign":"60793bf3619788c44034b55bfc3e26f4"}';
        $re = json_decode($res, true);
        if ($re['code'] == '00000') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
            if ($this->data['bank_data'] == 'alipay') {
                $this->return['str'] = $re['data']['payUrl'];//支付宝H5跳转地址
            } else {
                $this->return['str'] = $re['qrcode_url'];//微信扫码地址
            }
        } else {
            $this->return['code'] = 8;
            $this->return['msg'] = 'YINGZF:' . $re['msg'];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
//        $pieces = (array)json_decode(file_get_contents('php://input'), true);
        $res = [
            'status' => 1,
            'order_number' => $pieces['orderNo'],//商户订单号
            'third_order' => $pieces['sysOrderNo'],//第三方的支付订单号
            'third_money' => $pieces['amount'] * 100,//支付金额
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['orderNo']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['code'] != '00000'){
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
        $arr['merchantNo'] = $config['partner_id'];
        $arr['orderNo'] = $pieces['orderNo'];
        $arr['amount'] = $pieces['amount'];
        $arr['code'] = $pieces['code'];
        $arr['msg'] = $pieces['msg'];
        $arr['sysOrderNo'] = $pieces['sysOrderNo'];
        return $pieces['sign'] == $this->sytMd5($arr, $config['key']);
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
}