<?php
/**
 * Author: Taylor 2019-02-14
 */
namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 嘉连支付对接
 * @package Logic\Recharge\Pay
 */
class JLZF extends BASES
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
            'pay_memberid' => $this->partnerID,//商家ID
            'pay_orderid' => $this->orderID,
            'pay_applydate' => date("Y-m-d H:i:s"),
            'pay_bankcode' => $this->data['bank_data'],//支付编码
            'pay_notifyurl' => $this->notifyUrl,
            'pay_callbackurl' => $this->returnUrl,
            'pay_amount' => sprintf("%.2f", $this->money / 100),//订单金额，单位元,
        );
        $this->parameter['pay_md5sign'] = $this->sytMd5($this->parameter, $this->key);//32位小写MD5签名值
//        $this->parameter['pay_attach'] = "1234|456";
        $this->parameter['pay_productname'] = 'VIP'.$this->orderID;
        if($this->data['bank_data'] == 913){
            $this->parameter['pay_type'] = 101;
        }
    }

    //生成支付签名
    public function sytMd5($native, $signKey)
    {
        ksort($native);
        $md5str = "";
        foreach ($native as $key => $val) {
            $md5str .=  $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $signKey));
        return $sign;
    }

    //返回参数
    public function parseRE()
    {
        if($this->data['bank_data'] == 919){//支付宝红包模式使用表单POST方式
            $this->parameter['url'] = $this->payUrl;
            $this->parameter['method'] = 'POST';

            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $this->jumpURL . '?' . $this->arrayToURL();
        }else{
            $res = $this->httpspost($this->payUrl, $this->parameter);//发起请求
    //        var_dump($res);exit;
            //{"status":"error","msg":"签名验证失败","data":{"pay_memberid":"10053","pay_orderid":"201902141502008539","pay_applydate":"2019-02-14 16:12:02","pay_bankcode":"904","pay_notifyurl":"http://pay-api.zypaymet.com/jlzf/callback","pay_callbackurl":"https://m.taylor.com/user","pay_amount":"100.00","pay_md5sign":"123","pay_productname":"VIP201902141502008539"}}
    //        $res = '{"net":true,"ok":true,"data":"https://mclient.alipay.com/cashier/mobilepay.htm?alipay_exterface_invoke_assign_target=invoke_887f9a458bafd2abc781c8d5d52fa9f0&alipay_exterface_invoke_assign_sign=_i_d8r31a_ih_im_z3i0f_nv0lh_uj_a_wqd_mp_u%2Fr_dfw9u_ts_nk_s_v_c%2F_p%2F_x090_g_s_a%3D%3D"}';
            $re = json_decode($res, true);
            if ((isset($re['ok']) && $re['ok'] == true) || (isset($re['status']) && $re['status'] == 'success')) {
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->data['return_type'];//jump跳转或code扫码
                $this->return['str'] = $re['data'];
            } else {
                $this->return['code'] = 8;
                $this->return['msg'] = 'JLZF:' . $re['msg'];
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = '';
            }
        }
    }

    //签名验证
    public function returnVerify($pieces)
    {
        //{"order_no":"2019011716585431742","user_id":"0","shop_no":"201901171658538093","money":"1.00","type":"bank","date":"2019-01-17 16:58:54","trade_no":"2019011716585431742","status":0,"sign":"76606b1caa23d9dd312cb365c97161ac"}
        $res = [
            'status' => 1,
            'order_number' => $pieces['orderid'],//商户订单号
            'third_order' => $pieces['transaction_id'],//第三方的支付订单号
            'third_money' => $pieces['amount'] * 100,//支付金额，以元单位，需要转为分
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
        $arr['memberid'] = $pieces['memberid'];//商户id
        $arr['orderid'] = $pieces['orderid'];//用户id
        $arr['amount'] = $pieces['amount'];
        $arr['datetime'] = $pieces['datetime'];
        $arr['transaction_id'] = $pieces['transaction_id'];
        $arr['returncode'] = $pieces['returncode'];
        ksort($arr);
        reset($arr);
        $md5str = "";
        foreach ($arr as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $config['key']));
        return $pieces['sign'] == $sign;
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