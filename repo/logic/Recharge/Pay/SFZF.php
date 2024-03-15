<?php

namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 *
 * 瞬付支付(薯条)
 * @author Lion
 */
class SFZF extends BASES {

    static function instantiation(){
        return new SFZF();
    }

    //与第三方交互
    public function start(){
        $this->initParam();       // 数据初始化
        $this->basePost();        // POST请求
        $this->parseRE();         // 处理结果
    }


    //组装数组
    public function initParam(){
        $this->parameter['fxid']    = $this->partnerID;                          // 平台分配商户号
        $this->parameter['fxddh']     = $this->orderID;                          // 订单号唯一, 字符长度20
        $this->parameter['fxdesc'] = 'VIP基础服务';                                // 商品名称
        $this->parameter['fxfee']      = $this->money/100;                       // 商品金额
        $this->parameter['fxnotifyurl']   = $this->notifyUrl;                    // 异步通知地址
        $this->parameter['fxbackurl']     = $this->returnUrl;                    // 同步通知地址
        $this->parameter['fxpay'] = $this->payType;                              // 请求类型 【支付宝wap：zfbwap】【支付宝电脑端：zfbpc】【支付宝H5：zfbh5】
        $this->parameter['fxsign']     = $this->sign();                          // 签名
        $this->parameter['fxip']       = $this->data['client_ip'];
    }

    //生成签名
    public function sign(){
        // 签名【md5(商务号+商户订单号+支付金额+异步通知地址+商户秘钥)】
        $md5str = $this->parameter['fxid']
            . $this->parameter['fxddh']
            . $this->parameter['fxfee']
            . $this->parameter['fxnotifyurl']
            . $this->key;

        $sign = md5($md5str);
        return $sign;
    }

    //处理结果
    public function parseRE(){
        $re = json_decode($this->re,true);
        if(isset($re['status']) && $re['status']== 1) {
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = $re['payurl'];
        }else {
            $this->return['code'] = 1;
            $this->return['msg']  = 'SFZF:'.$re['error'];
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = '';
        }
    }

    //签名验证
    public function returnVerify($parameters) {
        $res = [
            'status' => 0,
            'order_number' => $parameters['fxddh'],        //商户系统内部订单号
            'third_order' => $parameters['fxorder'],       //支付系统流水号
            'third_money' => $parameters['fxfee'] * 100,   //订单金额，分
            'error' => '',
        ];
        $config = Recharge::getThirdConfig($parameters['fxddh']);

        if (!$config) {
            $res['status'] = 0;
            $res['error'] = '未有该订单';
        }
        $result=$this->returnVail($parameters,$config);
        if ($result) {
            $res['status'] = 1;
        } else {
            $res['status'] = 0;
            $res['error'] = '验签失败！';
        }
        return $res;
    }

    public function returnVail($input, $config) {
        $data['fxstatus'] = $input['fxstatus'];        // 订单状态
        $data['partner_id'] = $config['partner_id'];   // 商户id
        $data['fxddh'] = $input['fxddh'];              // 商户订单号
        $data['fxfee'] = $input['fxfee'];             // 支付金额
        $data['key'] = $config['key'];                 // 商户秘钥
        $sign = md5(implode(null, $data));
        return $sign == $input['fxsign'];
    }

}
