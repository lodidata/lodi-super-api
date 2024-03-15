<?php
/**
 * Author: Taylor 2019-01-22
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 比特付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class BTF extends BASES
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
            'store_no' => $this->partnerID,//平台分配商户号
            'out_trade_no' => $this->orderID,//商户生成的订单号，必须保持唯一性
            'call_notify' => $this->notifyUrl,//结果通知地址
            'pay_type' => $this->data['bank_data'],//支付类型
            'amount' => sprintf("%.2f", $this->money / 100),//交易金额，单位：元，保留两位小数
        );
        $this->parameter['sign'] = $this->sytMd5($this->parameter, $this->key);//32位小写MD5签名值
    }

    //生成支付签名
    public function sytMd5($data, $signKey)
    {
        ksort($data);
        $dataStr = '';
        foreach ($data as $k => $v) {
            if ($k === 'sign' || empty($v)) continue;
            $dataStr .= "$k=$v&";
        }
        $dataStr .= "key=$signKey";
        return md5($dataStr);
    }

    //返回参数
    public function parseRE()
    {
        $res = $this->httpspost($this->payUrl, $this->parameter);//发起请求
        //{"amount":"200.00","out_trade_no":"201901221223059450","pay_url":"http://zhr.51joypay.com/trade/api/qrCodeReceiver/6C4D7B886E4F4C38B2F66DD78851BBAA","qrcode_img_url":"http://fastpay.785f7.com/pay/bizpayurl/MjM5NzcxMzY4NTIwOTQxNTY4.do","ret_code":"0000","ret_msg":"操作成功"}
        //{"ret_code":"2004","ret_msg":"订单号已存在"}
        $re = json_decode($res, true);
        if ($re['ret_code'] == '0000') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
            $this->return['str'] = $re['pay_url'];
        } else {
            $this->return['code'] = 8;
            $this->return['msg'] = 'BTF:' . (isset($re['ret_msg']) ? $re['ret_msg'] : '');
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
        //{"result":"1","retcode":"0000","retmsg":"\u64cd\u4f5c\u6210\u529f","sign":"42ecce6b51aada29a32dbd6b087cf9f4","spbillno":"201901221602241438","storeId":"1808144852183","tranAmt":"10.00","transactionId":"2019012216022403288191915079"}
        $res = [
            'status' => 1,
            'order_number' => $pieces['spbillno'],//商户订单号
            'third_order' => $pieces['transactionId'],//第三方的支付订单号
            'third_money' => $pieces['tranAmt'] * 100,//支付金额，以元单位，需要转为分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['spbillno']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['retcode'] != '0000'){
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
    public function retrunVail($data, $config)
    {
        ksort($data);
        $dataStr = '';
        foreach ($data as $k => $v) {
            if ($k === 'sign' || empty($v)) continue;
            $dataStr .= "$k=$v&";
        }
        $dataStr .= "key={$config['key']}";
        return $data['sign'] == md5($dataStr);
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
//            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($param)
            ));
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