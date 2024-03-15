<?php
//namespace Las\Pay;
//use Las\BASES;
//use Las\Utils\Client;
//use phpDocumentor\Reflection\Types\Array_;

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 新全付通
 * @author ben
 */

class XQFT extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->parseRE();
    }

    //组装数组
    public function initParam(){

        // paytype       微信：公众号支付: OPENPAY， H5支付：H5PAY
        //支付宝：H5支付：DIRECT_PAY
        //QQ支付:Wap支付：QQ_WAP
        //京东支付:扫码支付：JD_SCAN ，Wap支付：JD_WAP
        //银联支付:快捷支付：UN_QUICK，网关支付：UN_GATEWAY
        $wayAndtype = explode(':',$this->payType);
        $this->parameter = [
            'mechno'=>$this->partnerID,//商户号
            'orderip'=>$this->data['client_ip'],//ip地址，必须填写下单客户的手机IP地址，不能填服务器IP
            'amount' => $this->money,//支付的总金额，单位为分
            'body' => '充值',//商品的名称，原值参与签名后用UrlEncoder进行编码
            'notifyurl'=> $this->notifyUrl,
            'returl'=> $this->returnUrl,
            'orderno' => $this->orderID,//
            'timestamp' => time(),//
            'payway' => $wayAndtype[0],//微信：WEIXIN，支付宝：ALIPAY，QQ支付：QQPAY，京东支付：JDPAY，银联网关: UNIONPAY
            'paytype' => $wayAndtype[1],//

        ];

        $this->parameter['sign'] = $this->getSign($this->parameter);
        $this->parameter['body'] = urlencode($this->parameter['body']);

    }


    //md加密方式
    public function getSign($params){

        ksort($params);
        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k.'='.$v.'&';
        }
        $sign_str = $str.'key='.$this->key;
        $sign = strtoupper(md5($sign_str));

        return $sign;

    }

    public function parseRE()
    {
        if($this->showType == 'code'){
            $this->basePost();
            $re = json_decode($this->re, true);
//            print_r($this->curlError);
//            print_r($this->re);
            if (isset($re['status']) && $re['status'] == 0) {
                $this->return['code'] = 0;
                $this->return['msg'] = 'SUCCESS';
                $this->return['way'] = $this->showType;
                $this->return['str'] = $re['payCode'];
            } else {
                $this->return['code'] = 886;
                $this->return['msg'] = 'XQFT:第三方未知错误';
                $this->return['way'] = $this->showType;
                $this->return['str'] = '';
            }
        }else{
            $this->parameter['url'] = $this->payUrl .'?';
            $this->parameter['method'] = 'GET';
            $this->parameter['sign'] = urlencode($this->parameter['sign']);

            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $this->jumpURL . '?' . $this->arrayToURL();
        }

    }


    public  function returnVerify($params) {

//        tradestate 取值说明：100：成功，0：初始化，1：进行中，3：退款 , 4：取消
        $stateArr = [
            '初始化','进行中','退款' , '取消'
        ];
        $res = [
            'status' => 0,
            'order_number' => $params['extra'],//outorderno
            'third_order'  => $params['transactionid'],
            'third_money'  => $params['totalfee'],
            'error' => ''
        ];

        $sign = $params['sign'];

        $config = Recharge::getThirdConfig($params['extra']);

        ksort($params);
        $string='';
        foreach ($params as $key=>$value){
            if($value !='' && $value !=null && $key !='sign'){
                $string=$string.$key.'='.$value.'&';
            }
        }
        $string=$string.'key='. $config['key'];
        $mySign = strtoupper(md5($string));

        if($sign == $mySign){

            if($params['status'] == 0){
                $res['status']  = 1;
            }else{
                $res['error'] = isset($stateArr[$params['tradestate']]) ? $stateArr[$params['tradestate']] : '第三方未知状态：'.$params['tradestate'];
            }

        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }

        return  $res;
    }
}
