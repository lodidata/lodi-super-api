<?php

namespace Logic\Recharge\Defray;


/**
 *
 * 小米回调
 */
class XIAOMI {

    //回调数据校验
    /*
     * $resArray 第三方通知数组
     * $deposit  系统流水信息
     * $vender   授权信息
     * */
    public function callbacVerify($param, $config) {
        //小米官方  $param  内容
        //appId	必须	游戏ID
        //cpOrderId	必须	开发商订单ID
        //cpUserInfo	可选	开发商透传信息
        //uid	必须	用户ID
        //orderId	必须	游戏平台订单ID
        //orderStatus	必须	订单状态，TRADE_SUCCESS 代表成功
        //payFee	必须	支付金额,单位为分,即0.01 米币。
        //productCode	必须	商品代码
        //productName	必须	商品名称
        //productCount	必须	商品数量
        //payTime	必须	支付时间,格式 yyyy-MM-dd HH:mm:ss
        //orderConsumeType	可选	订单类型：10：普通订单11：直充直消订单
        //partnerGiftConsume	必选	使用游戏券金额 （如果订单使用游戏券则有,long型），如果有则参与签名
        //signature	必须	签名,签名方法见后面说明
        $key = \DB::table('pay_config')
            ->where('partner_id',$param['appId'])
            ->where('channel_id',$config['channel_id'])
            ->value('key');
        $tmp = explode('_',$param['cpOrderId']);
        $res = [
            'status'       => 0,
            'uid'       => $tmp[1],
            'order_number' => $tmp[0],      //订单号
            'deal_number'  => $param['orderId'],      //交易订单号
            'money'  => $param['payFee'],
            'error'        => '',
        ];

        if($param['orderStatus'] != 'TRADE_SUCCESS'){
            $res['error'] = '未支付';
            return $res;
        }
        $sign = $param['signature'];
        unset($param['signature']);
        $str = $this->arrayToURL($param);
        $signature = hash_hmac("sha1", $str, $key);
//        if($signature != $sign){
//            $res['error'] = '验签失败';
//            return $res;
//        }
        $res['status'] = 1;
        return $res;
    }

    public function arrayToURL($data) {
        $signPars = "";
        ksort($data);
        foreach($data as $k => $v) {
            //  字符串0  和 0 全过滤了，所以加上
            $signPars .= $k . "=" . $v . "&";
        }
        $signPars = rtrim($signPars,'&');
        return $signPars;
    }

}
