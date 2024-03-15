<?php

namespace Logic\Recharge\Defray;


/**
 *
 * OPPO回调
 */
class OPPO
{

    //回调数据校验
    /*
     * $resArray 第三方通知数组
     * $deposit  系统流水信息
     * $vender   授权信息
     * */
    public function callbacVerify($param, $config)
    {
        /**
         *
         * notifyId
         * 回调通知 ID(该值使用系统为这次支付生 成的订单号)
         * String(50)
         *
         * partnerOrder
         * 开发者订单号(客户端上传)
         * String(100)
         *
         * productName
         * 商品名称(客户端上传)
         * String(50)
         *
         * productDesc
         * 商品描述(客户端上传)
         * String(100)
         *
         * price
         * 商品价格(以分为单位)
         * int
         *
         * attach
         * 请求支付时上传的附加参数(客户端上传)
         * String(200)
         *
         * sign
         * 签名
         * String
         */

        if (!(isset($param['attach']) && isset($param['partnerOrder']) && isset($param['notifyId']) && isset($param['price']))) {
            return false;//非法请求,缺少参数
        }
        $res = [
            'status' => 0,
            'uid' => $param['attach'],
            'order_number' => $param['partnerOrder'],      //订单号
            'deal_number' => $param['notifyId'],      //交易订单号
            'money' => $param['price'] / 100,
            'error' => '',
        ];

        $pub_key = $config['pub_key'];
        if ($this->rsa_verify($param, $pub_key) != 1) {
            $res['error'] = '验签失败';
            return $res;
        }
        $res['status'] = 1;
        return $res;
    }

    public function arrayToURL($data)
    {
        $signPars = "";
        ksort($data);
        foreach ($data as $k => $v) {
            //  字符串0  和 0 全过滤了，所以加上
            $signPars .= $k . "=" . $v . "&";
        }
        $signPars = rtrim($signPars, '&');
        return $signPars;
    }

    function rsa_verify($contents, $pub_key)
    {
        $str_contents = "notifyId={$contents['notifyId']}&partnerOrder={$contents['partnerOrder']}&productName={$contents['productName']}&productDesc={$contents['productDesc']}&price={$contents['price']}&count={$contents['count']}&attach={$contents['attach']}";
        $publickey = $pub_key;
        $pem = chunk_split($publickey, 64, "\n");
        $pem = "-----BEGIN PUBLIC KEY-----\n" . $pem . "-----END PUBLIC KEY-----\n";
        $public_key_id = openssl_pkey_get_public($pem);
        $signature = base64_decode($contents['sign']);
        return openssl_verify($str_contents, $signature, $public_key_id);//成功返回1,0失败，-1错误,其他看手册
    }

}
