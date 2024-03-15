<?php
/**
 * Author: Taylor 2019-01-18
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * 全球支付对接
 * Class GT
 * @package Logic\Recharge\Pay
 */
class QUANQF extends BASES
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
        $arr = array(
            'pay_memberid' => $this->partnerID,//商户id,由万通支付分配
            'pay_orderid' => $this->orderID,//商户系统订单号
            'pay_amount' => $this->money/100,//订单金额，单位为：元
            'pay_applydate'=> date("Y-m-d H:i:s"),//订单标题
            'pay_bankcode'=> $this->data['bank_data'],//支付方式907 网银支付；917 银联扫码；913 支付宝h5；915 微信扫码
            'pay_notifyurl' => $this->notifyUrl,//下行异步通知的地址，需要以http(s)://开头且没有任何参数
            'pay_callbackurl'=>$this->returnUrl,//页面跳转返回地址
        );
        $sign = $this->sign($arr, $this->key);//32位小写MD5签名值
        $arr['pay_md5sign'] = $sign;
        $arr['pay_return'] = 1;
        $arr['pay_attach'] = "1234";
        $arr['pay_productname'] = $this->orderID;
        $this->parameter = $arr;
    }

    //生成支付签名
    public function sign($arr, $md5key)
    {
        ksort($arr);
        $md5str = "";
        foreach ($arr as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        return strtoupper(md5($md5str . "key=" . $md5key));
    }

    //发起请求，返回参数
    public function parseRE()
    {
        $res = $this->httpspost($this->payUrl, $this->parameter);//发起请求
        //{"errcode":0,"msg":"\u6ca1\u6709\u53ef\u7528\u7684\u901a\u9053\u3010100\u3011","status":"error"}
//        $res = '{"qrcode":"http:\/\/gpay.pay168sys.info\/pay\/alipaybank\/h5\/skip\/pay\/ABANK1901182300712759\/501f3a3e948e50eb9d71e4c62c26c8c1","money":"200","status":"success"}';
        $re = json_decode($res, true);
        if ($re['status'] == 'success') {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
            $this->return['str'] = $re['qrcode'];//支付地址
        } else {
            $this->return['code'] = 8;
            $this->return['msg'] = 'QUANQF:' . ($re['msg'] ? $re['msg'] : '');
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
            'third_order' => $pieces['transaction_id'],//系统流水号
            'third_money' => $pieces['amount'] * 100,//订单金额，系统是元，需要转为分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($pieces['orderid']);
        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '没有该订单';
        }
        if($pieces['returncode'] != '00'){
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
    public function retrunVail($responseData, $md5key)
    {
        $returnArray = array( // 返回字段
            "memberid" => $responseData["memberid"], // 商户ID
            "orderid" =>  $responseData["orderid"], // 订单号
            "amount" =>  $responseData["amount"], // 交易金额
            "datetime" =>  $responseData["datetime"], // 交易时间
            "transaction_id" =>  $responseData["transaction_id"], // 支付流水号
            "returncode" => $responseData["returncode"],
        );
        ksort($returnArray);
        reset($returnArray);
        $md5str = "";
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5key));
        if ($sign == $responseData["sign"]) {
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
}