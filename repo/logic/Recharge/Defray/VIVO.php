<?php

namespace Logic\Recharge\Defray;

/**
 *
 * VIVO开放平台支付回调
 */
class VIVO {

    //回调数据校验
    /*
     * $resArray 第三方通知数组
     * $deposit  系统流水信息
     * $vender   授权信息
     * */
    public function callbacVerify($param, $config) {
        //VIVO  $param  内容
        //respCode 响应码	200
        //respMsg	响应信息	交易完成
        //signMethod	签名方法	对关键信息进行签名的算法名称：MD5
        //signature	签名信息	对关键信息签名后得到的字符串，用于商户验签签名规则请参考签名计算说明
        //tradeType	交易种类	目前固定01
        //tradeStatus	交易状态	0000，代表支付成功
        //cpId	Cp-id	定长20位数字，由vivo分发的唯一识别码
        //appId	appId	应用ID
        //uid	uid	用户在vivo这边的唯一标识
        //cpOrderNumber	商户自定义的订单号	商户自定义，最长 64 位字母、数字和下划线组成
        //orderNumber	交易流水号	vivo订单号
        //orderAmount	交易金额	单位：分，币种：人民币，为长整型，如：101，10000
        //extInfo	商户透传参数	64位
        //payTime	交易时间	yyyyMMddHHmmss
        //{"uid":"ec1b68f56025cf63","tradeType":"01","respCode":"200","tradeStatus":"0000","appId":"101154873","payTime":"20190611101648","cpOrderNumber":"c1cdac88a1214b588c27efa3d9e437d3_331541","cpId":"65712f70fc621bd020de","signMethod":"MD5","orderAmount":"100","orderNumber":"2019061110162829300017857942","extInfo":"extInfo","respMsg":"\u4ea4\u6613\u6210\u529f","signature":"b515b21e7e2b185be39cbad2491da8a5"}
        $key = \DB::table('pay_config')
            ->where('app_secret', $param['appId'])
            ->where('channel_id', $config['channel_id'])
            ->value('key');
        $tmp = explode('_', $param['cpOrderNumber']);//通知参数需要提取_后面的userid
        $res = [
            'status'       => 0,
            'uid'       => $tmp[1],
            'order_number' => $tmp[0],      //订单号
            'deal_number'  => $param['orderNumber'],      //交易订单号
            'money'  => $param['orderAmount'],
            'error'        => '',
        ];

        if($param['tradeStatus'] != '0000'){
            $res['error'] = '未支付';
            return $res;
        }
        $sign = $param['signature'];
        $signature = $this->arrayToURL($param, $key);
        if($signature != $sign){
            $res['error'] = '验签失败';
            return $res;
        }
        $res['status'] = 1;
        return $res;
    }

    public function arrayToURL($data, $key) {
        unset($data['signature'], $data['signMethod']);
        $signPars = "";
        ksort($data);
        foreach($data as $k => $v) {
            if(!is_null($v) && $v !== ''){
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .= strtolower(md5($key));
        return md5($signPars);
    }

}
