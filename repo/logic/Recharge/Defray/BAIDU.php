<?php

namespace Logic\Recharge\Defray;

/**
 *
 * 百度开放平台支付回调
 */
class BAIDU {

    //回调数据校验
    /*
     * $resArray 第三方通知数组
     * $deposit  系统流水信息
     * $vender   授权信息
     * */
    public function callbacVerify($param, $config) {
        //百度支付SDK服务
        //BAIDU  $param  内容
        //AppID 应用 ID
        //OrderSerial	SDK 系统内部订单号
        //CooperatorOrderSerial	CP 订单号
        //Sign 签名
        //Content.UID	用户 ID 同客户端 SDK 中 API返回的用户 ID
        //Content.MerchandiseName	商品名称
        //Content.OrderMoney	订单金额，保留两位小数。单位：元
        //Content.StartDateTime	订单创建时间 格 式 ：yyyy-MM-ddHH:mm:ss
        //Content.BankDateTime	银行到帐时间 格 式 ：yyyy-MM-ddHH:mm:ss
        //Content.OrderStatus	订单状态 0:失败 1:成功
        //Content.StatusMsg	    订单状态描述
        //Content.ExtInfo	    CP 扩展信息，客户端SDK 传入，发货通知原样返回
        //Content.VoucherMoney	代金券金额

        //百度移动游戏SDK
        //appid 开发者平台创建游戏 id
        //orderid Sdk 生成的订单号
        //amount 金额
        //unit 金额的单位(元或者分)
        //jfd 游戏在百度游戏申请的计费点 id，不是其他平台上申请的 id
        //status 订单状态(success 标示成功,failed 标示失败)
        //paychannel 使用的充值渠道标示
        //phone 手机号(短代充值一般会有)，但是支付宝等没有
        //channel 充值方式所属大类(CUCC/CTCC/CMCC，联通电信移动)
        //from 标示从 sdk 的充值通知(gsdk)
        //sign MD5 值(对 appid,orderid,amount,unit,status,paychannel,appsecret 拼接字符串取 MD5 值)
        //extchannel 推广渠道 id，支持不同渠道发布包的收入
        //cpdefinepart 在弱联网或强联网情况下，cp 自定义的透传信息，根据此字段可以做些逻辑处理
        $key = \DB::table('pay_config')
            ->where('partner_id', $param['appid'])
            ->where('channel_id', $config['channel_id'])
            ->value('app_secret');
//        $tmp = explode('_', $param['CooperatorOrderSerial']);//通知参数需要提取_后面的userid
//        $param['des_content'] = json_decode(base64_decode($param['content']), true);
        $res = [
            'status'       => 0,
            'uid'       => $param['cpdefinepart'],//自定义信息
//            'order_number' => $param['orderid'],      //订单号
            'order_number' => $param['cpdefinepart'].rand(100, 999).time(),      //自定义订单号
            'deal_number'  => $param['orderid'],      //交易订单号
            'money'  => $param['amount'],//交易金额，为分
            'error'        => '',
        ];

        if($param['status'] != 'success'){
            $res['error'] = '未支付';
            return $res;
        }
        $sign = $param['sign'];
        $signature = $this->arrayToURL($param, $key);
        if($signature != $sign){
            $res['error'] = '验签失败';
            return $res;
        }
        $res['status'] = 1;
        return $res;
    }

    public function arrayToURL($data, $key) {
        $signPars = $data['appid'].$data['orderid'].$data['amount'].$data['unit'].$data['status'].$data['paychannel'].$key;
        return md5($signPars);
    }

    //返回第三方需要生成的报文
//    public function callback($param, $config){
//        $key = \DB::table('pay_config')
//            ->where('partner_id', $param['AppID'])
//            ->where('channel_id', $config['channel_id'])
//            ->value('app_secret');
//        $re['APPID'] = $param['AppID'];
//        $re['ResultCode'] = 1;
//        $re['ResultMsg'] = 'success';
//        $re['Sign'] = md5($re['APPID'].$re['ResultCode'].$key);
//        return json_encode($re);
//    }

}
