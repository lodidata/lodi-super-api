<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/10/15
 * Time: 15:44
 */

namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class NEWHT extends BASES
{
    //海豚支付
    //与第三方交互
    public function start()
    {
        $this->initParam();
        $this->doPay();
    }

    //组装数组
    public function initParam()
    {

        // array(1=>'支付宝',2=>'支付宝扫码',3=>'微信',4=>'微信扫码',5=>'QQ支付',6=>'银联快捷',7=>'银联扫码',8=>'银联网关')
        $this->parameter = array(
            "pay_memberid" => $this->partnerID,
            "pay_orderid" => $this->orderID,
            "pay_applydate" => date('Y-m-d H:i:s'),
            "pay_bankcode" => $this->payType,
            "pay_notifyurl" => $this->notifyUrl,
            "pay_callbackurl" => $this->returnUrl,
            "pay_amount" => (int)$this->money / 100 //支付金额 单位元
        );

        $sign = $this->MakeSign($this->parameter, $this->key);
        $this->parameter['pay_md5sign'] = $sign;
        $this->parameter['pay_productname'] = 'GOODS';
    }


    protected function doPay()
    {

        $this->alipay_request_api($this->payUrl, $this->parameter);
        $re = json_decode($this->re, true);
//            print_r($this->curlError);
//            print_r($re);
//            print_r($this->re);
//            exit;
        if (isset($re['code']) && $re['code'] == 200) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = $re['data']['qrcode_url'];
        } else {
            $msg = $re['msg'] ?? "未知异常";
            $this->return['code'] = 886;
            $this->return['msg'] = 'NEWHT:' . $msg;
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
        }

    }


    public function returnVerify($params)
    {

        $res = [
            'status' => 0,
            'order_number' => $params['orderid'],//outorderno
            'third_order' => $params['transaction_id'],
            'third_money' => $params['amount'] * 100,
            'error' => ''
        ];


        $config = Recharge::getThirdConfig($params['orderid']);
        $returnSign = $params['sign'];
        unset($params['sign']);
        $mysign = $this->MakeSign($params, $config['key']);
        if ($returnSign == $mysign) {
            if ($params['returncode'] == '00') {//支付成功
                $res['status'] = 1;
            } else { //支付失败
                $res['error'] = '未支付或支付失败';
                echo 'success';
            }
        } else {
            $res['error'] = '该订单验签不通过或已完成';
        }

        return $res;

    }


    public function MakeSign($param, $key)
    {
        //签名步骤一：按字典序排序参数
        ksort($param);
        $string = self::ToUrlParams($param);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $key;
        //签名步骤三：MD5加密
        $string = strtoupper(md5($string));
        return $string;
    }

    /**
     * 格式化参数格式化成url参数
     */
    public static function ToUrlParams($param)
    {
        $buff = "";
        foreach ($param as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    public static function request_api($url, $postFields = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //https 请求
        if (strlen($url) > 5 && strtolower(substr($url, 0, 5)) == "https") {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        if (is_array($postFields) && 0 < count($postFields)) {
            $postBodyString = "";
            foreach ($postFields as $k => $v) {
                $postBodyString .= "$k=" . urlencode($v) . "&";
            }
            unset($k, $v);
            curl_setopt($ch, CURLOPT_POST, true);

            $header = array("content-type: application/x-www-form-urlencoded; charset=UTF-8");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBodyString, 0, -1));
        }
        $reponse = curl_exec($ch);
        return json_decode($reponse, true);
    }

    public function alipay_request_api($url, $postFields = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //https 请求
        if (strlen($url) > 5 && strtolower(substr($url, 0, 5)) == "https") {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        if (is_array($postFields) && 0 < count($postFields)) {
            $postBodyString = "";
            foreach ($postFields as $k => $v) {
                $postBodyString .= "$k=" . urlencode($v) . "&";
            }
            unset($k, $v);
            curl_setopt($ch, CURLOPT_POST, true);

            $header = array("content-type: application/x-www-form-urlencoded; charset=UTF-8");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBodyString, 0, -1));
        }
        $reponse = curl_exec($ch);
        $this->curlError = curl_error($ch);
        $this->re = $reponse;
    }
}