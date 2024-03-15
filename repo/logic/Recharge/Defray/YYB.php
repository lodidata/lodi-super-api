<?php

namespace Logic\Recharge\Defray;


/**
 *
 * 应用宝回调
 */
class YYB {

    //回调数据校验
    /*
     * $resArray 第三方通知数组
     * $deposit  系统流水信息
     * $vender   授权信息
     * */
    public function callbacVerify($param, $config) {
        //"{"amt":"950","appid":"1450002647","appmeta":"111tvg1000014689306111369441356*wechat*wechat","billno":"-APPDJSX31313-20150618-1541181457","cftid":"1000018901201506186151502278","channel_id":"null-android-null-68:f0:6d:01:18:15_10086-wechat","clientver":"android","openid":"11158D06A11EEE703E2","payamt_coins":"0","payitem":"tvp100001001*100*1","providetype":"5","pubacct_payamt_coins":"","token":"85051928D0B36AB3A9B49698371A0B3201039","ts":"1434613284","vbazinga":"1","version":"v3","zoneid":"1","sig":"RI920s01b3uHuvPO2eC0cFnO2As="}"
        //应用宝米大师支付  $param  内容
        //amt	string	Q点/Q币消耗金额或财付通游戏子账户的扣款金额。可以为空，若传递空值或不传本参数则表示未使用Q点/Q币/财付通游戏子账户。允许游戏币、Q点、抵扣券三者混合支付，或只有其中某一种进行支付的情况。用户购买道具时，系统会优先扣除用户账户上的游戏币，游戏币余额不足时，使用Q点支付，Q点不足时使用Q币/财付通游戏子账户。这里的amt的值将纳入结算，参与分成。注意，这里以0.1Q点为单位。即如果总金额为18Q点，则这里显示的数字是180。请开发者关注，特别是对账的时候注意单位的转换。
        //appid string 应用的唯一ID。可以通过appid查找APP基本信息。
        //appmeta	string	在buy_goods_m的设定的自定义参数app_metadata，会透传到appmeta里，格式为 自定义字段*支付方式*平台渠道，比如customkey*qdqb*qq。应用侧这边根据此格式自行提取自定义部分的值。注意如果有比如“-”、“_”等特殊符号，在计算sig时替换为“%2D”、“%5F”等。
        //billno    string	支付流水号（64个字符长度。该字段和openid合起来是唯一的）。
        //cftid string	财付通订单号
        //channel_id	string	前端透传字段，pf部分字段-支付渠道。例如pf=desktop_m_qq-2001-android-2011-xxxx 使用微信支付，则channel_id=2001-android-2011-xxxx-wechat
        //clientver	string	手机端版本号
        //openid	string	与APP通信的用户key，跳转到应用首页后，URL后会带该参数。由平台直接传给应用，应用原样传给平台即可。根据APPID以及QQ号码生成，即不同的appid下，同一个QQ号生成的OpenID是不一样的。
        //payamt_coins	string	扣取的游戏币总数，单位为Q点。可以为空，若传递空值或不传本参数则表示未使用游戏币。允许游戏币、Q点、抵扣券三者混合支付，或只有其中某一种进行支付的情况。用户购买道具时，系统会优先扣除用户账户上的游戏币，游戏币余额不足时，使用Q点支付，Q点不足时使用Q币/财付通游戏子账户。游戏币由平台赠送或由好友打赏，平台赠送的游戏币不纳入结算，即不参与分成；好友打赏的游戏币按消耗量参与结算（详见：货币体系与支付场景）
        //payitem	string	物品信息。（1）接收标准格式为ID*price*num，回传时ID为必传项。批量购买套餐物品则用“;”分隔，字符串中不能包含"|"特殊字符。 （2）ID表示物品ID，price表示单价（以Q点为单位，单价最少不能少于2Q点，1Q币=10Q点。单价的制定需遵循道具定价规范），num表示最终的购买数量。 示例： 批量购买套餐，套餐中包含物品1和物品2。物品1的ID为G001，该物品的单价为10Q点，购买数量为1；物品2的ID为G008，该物品的单价为8Q点，购买数量为2，则payitem为：G001*10*1;G008*8*2 。
        //providetype	string	回调类型。道具直购模式下为 5
        //pubacct_payamt_coins	string	扣取的抵用券总金额，单位为Q点。可以为空，若传递空值或不传本参数则表示未使用抵扣券。允许游戏币、Q点、抵扣券三者混合支付，或只有其中某一种进行支付的情况。用户购买道具时，可以选择使用抵扣券进行一部分的抵扣，剩余部分使用游戏币/Q点。平台默认所有上线支付的应用均支持抵扣券。自2012年7月1日起，金券银券消耗将和Q点消耗一起纳入收益计算（详见：货币体系与支付场景）。
        //token	string	应用调用v3/pay/buy_goods接口成功返回的交易token。
        //ts	string	linux时间戳。注意开发者的机器时间与计费服务器的时间相差不能超过15分钟。
        //vbazinga string	干扰字段，第一个字母动态变化（a~z随机）
        //sig string 请求串的签名，由需要签名的参数生成。
        //（1）签名方法请见文档：《签名生成说明》。
        //
        //（2）按照上述文档进行签名生成时，需注意回调协议里多加了一个步骤： 在构造源串的第3步“将排序后的参数(key=value)用&拼接起来，并进行URL编码”之前，需对value先进行一次编码（编码规则为：除了 0~9 a~z A~Z !*() 之外其他字符按其ASCII码的十六进制加%进行表示，例如："-"编码为“%2D”；如“.”编码为“%2E”；如“_”编码为“%5F”）。
        //
        //回调发货签名可参考文档：http://wiki.open.qq.com/wiki/mobile/购买道具扣款成功回调应用发货
        //
        //（3）以每笔交易接收到的参数为准，接收到的所有参数除sig以外都要参与签名。为方便平台后续对协议进行扩展，请不要将参与签名的参数写死。
        //
        //（4）所有参数都是string型，进行签名时必须使用原始接收到的string型值。 开发商出于本地记账等目的，对接收到的某些参数值先转为数值型再转为string型，导致字符串部分被截断，从而导致签名出错。如果要进行本地记账等逻辑，建议用另外的变量来保存转换后的数值。
        $key = \DB::table('pay_config')
            ->where('partner_id', $param['appid'])
            ->where('channel_id', $config['channel_id'])
            ->value('key');
        $res = [
            'status'       => 0,
            'uid'       => $param['openid'],
            'order_number' => $param['billno'],      //订单号
            'deal_number'  => $param['cftid'],      //交易订单号
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
        if($signature != $sign){
            $res['error'] = '验签失败';
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
