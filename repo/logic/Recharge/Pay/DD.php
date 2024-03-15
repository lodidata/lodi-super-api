<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 豆豆
 * @author viva
 */
class DD extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //组装数组
    public function initParam(){
        $getParams = [
            'pid' => $this->data['app_id'],//签约PID
            'method' => $this->data['action'],        //H5支付
            'timestamp' => time().'',//时间戳
            'randstr' => $this->getRandstr(),//随机32位串
        ];
        $money = $this->money;
        //H5支付参数
        $dataArray = [    //   全是字符串，注意
            'merchant_no' => $this->partnerID, //商户号，必传
            'out_trade_no' => $this->orderID, //商户订单号，字符串型，长度范围在10到32之间,只能是数字和字符串，必传
            'trade_amount' => $money.'', //支付金额，单位为分,无小数点，必传
            'notify_url' => $this->notifyUrl, //异步通知URL，一定外网地址才能收到回调通知信息，必传
            'sync_url' => $this->returnUrl,
            'body' => 'Wap', //订单描述，必传
            'client_ip' => '127.0.0.1',
            'pay_type' => $this->payType, //支付类型,目前支持微信，微信传weixin  必传
        ];
        //扫码支付参数
        if($this->showType == 'code') {
            $dataArray = [    //   全是字符串，注意
                'merchant_no' => $this->partnerID, //商户号，必传
                'order_sn' => $this->orderID, //商户订单号，字符串型，长度范围在10到32之间,只能是数字和字符串，必传
                'pay_amount' => $money . '', //支付金额，单位为分,无小数点，必传
                'notify_url' => $this->notifyUrl, //异步通知URL，一定外网地址才能收到回调通知信息，必传
                'order_desc' => 'scan', //订单描述，必传
                'pay_type' => 'swept',
                'pmt_tag' => $this->payType, //支付类型,目前支持微信，微信传weixin  必传
            ];
        }
        ksort($dataArray);
        $str_query = array_merge($getParams, ['data' => $dataArray]);
        ksort($str_query);
        $str_query = json_encode($str_query, JSON_UNESCAPED_SLASHES);
        $getParams['sign'] = md5($str_query.$this->data['token']);
        ksort($getParams);
        $encrypted = json_encode($dataArray, JSON_UNESCAPED_SLASHES);//数据转换成JSON
        $this->parameter = str_replace('\\\\/','/',$encrypted);
        $post = $this->pukOpenssl();
        $this->parameter = array(
            'data' => $post,
        );
        $this->payUrl .= '?'. $this->arrayToURLALL($getParams);
    }
    public function parseRE(){
        $re = json_decode($this->re, true);
        if($this->showType == 'code'){
            $pay_url = $re['data']['code_url'] ?? '';
        }else
            $pay_url = $re['data']['out_pay_url'] ?? '';
        if (isset($re['errcode']) && $re['errcode'] === '0' && $pay_url) {
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $pay_url;
        } else {
            $re['message'] = $re['msg'] ?? '第三方未知错误';
            $this->return['code'] = 886;
            $this->return['msg'] = 'DD:' . $re['message'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
    }
    public function getRandstr()
    {
        return strtolower(md5(uniqid(mt_rand(), true)));
    }

    /**
     * 返回地址验证
     *
     * @param
     * @return boolean
     */
    //[status=1 通过  0不通过,
    //order_number = '订单',
    //'third_order'=第三方订单,
    //'third_money'='金额',
    //'error'='未有该订单/订单未支付/未有该订单']
    public function returnVerify($parameters) {
        $parameters['total_fee'] = $parameters['total_fee'] ?? 0;
        $res = [
            'status' => 0,
            'order_number' => $parameters['out_trade_no'],
            'third_order' => $parameters['trade_no'],
            'third_money' => $parameters['trade_amount'] ?? $parameters['total_fee'],
            'error' => '',
        ];

        if(isset($parameters['pay_status']) && $parameters['pay_status'] == 1 || isset($parameters['trade_result']) && $parameters['trade_result'] == 'SUCCESS'){
            $config = Recharge::getThirdConfig($parameters['out_trade_no']);
            if($this->verifyData($parameters,$config['token'])){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';
        return $res;
    }
    public function verifyData($parameters,$key) {
        $tenpaySign = strtolower($parameters['sign']);
        unset($parameters['sign']);
        $signPars ='';
        ksort($parameters);
        foreach($parameters as $k => $v) {
            //  字符串0  和 0 全过滤了，所以加上
            if(!empty($v) || $v === 0  || $v === "0" ) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .='key=' . $key;
        $sign = strtolower(md5($signPars));
        return $sign == $tenpaySign;
    }

}
