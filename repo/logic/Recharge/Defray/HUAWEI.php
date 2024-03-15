<?php

namespace Logic\Recharge\Defray;


/**
 *
 * 华为回调
 */
class HUAWEI {

    //回调数据校验
    /*
     * $resArray 第三方通知数组
     * $deposit  系统流水信息
     * $vender   授权信息
     * */
    public function callbacVerify($param, $config) {

//        $key = \DB::table('pay_config')
//            ->where('partner_id',$param['requestId'])
//            ->where('channel_id',$config['channel_id'])
//            ->value('key');
//        $tmp = explode('_',$param['requestId']);
        $res = [
            'status'       => 0,
            'uid'       =>$param['extReserved'],
            'order_number' => $param['requestId'],      //订单号
            'deal_number'  => $param['orderId'],      //交易订单号
            'money'  => $param['amount'],
            'error'        => '',
        ];

        if($param['result'] != '0'){
            $res['error'] = '支付失败！';
            return $res;
        }
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
