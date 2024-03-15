<?php

namespace Las\Pay;


namespace Logic\Recharge\Pay;

use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * KG支付
 * @author
 * @date
 */
class KG extends BASES {

    static function instantiation() {
        return new KG();
    }

    //与第三方交互
    public function start() {
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }

    //组装数组
    public function initParam() {
        $this->parameter = [
            'HashKey'        => $this->pubKey,
            'HashIV'         => $this->partnerID,
            'MerTradeID'     => $this->orderID,
            'MerProductID'   => 'KG_GOODS',
            'MerUserID'      => $this->orderID,                   //店家消费者ID
            'Amount'         => $this->money / 100,
            'TradeDesc'      => 'KG_GOODS',                       //交易描述，必填
            'ItemName'       => 'KG_GOODS',
            'BankCode'       => $this->data['bank_code'] ? : 'ICBC',
            'USER_CLIENT_IP' => Client::getClientIp(),
            'WAP'            => '',                                     //1:手机版页面；非1或空值：WEB页面。目前只支持WEB页面
            'NotifyUrl'      => $this->notifyUrl,
        ];

        //支付宝不需要USER_CLIENT_IP字段
        if ($this->data['scene'] == 'alipay') {
            unset($this->parameter['USER_CLIENT_IP']);
        }

        //扫码支付传入该参数，只需返回url
        if ($this->data['show_type'] == 'code') {
            $this->parameter['ReturnQRCode'] = '1';
        }

        $this->parameter['Validate'] = $this->sytMd5($this->parameter);  //sign
    }

    public function parseRE() {
        $url = null;
        //返回值格式 <center>报错信息</center> 就是报错
        preg_match('/\<center\>([^\<]+)\<\/center\>/', $this->re, $result);

        if ($result && !empty($result[1])) {
            $this->return['code'] = 23;
            $this->return['msg'] = 'KG: ' . $result[1];
            $this->return['way'] = $this->data['return_type'];
            $this->return['str'] = '';
            return;
        }

        //扫码返回json
        if ($this->data['return_type'] == 'jump' && $this->data['show_type'] == 'code') {

            $result = json_decode($this->re, true);

            if (!$result || empty($result['QRCodeURL'])) {
                $this->return['code'] = 23;
                $this->return['msg'] = 'KG: 微信扫码请求失败，请联系客服';
                $this->return['way'] = $this->data['return_type'];
                $this->return['str'] = '';

                return;
            }
            $url = $result['QRCodeURL'];
        }else if ($this->data['return_type'] == 'jump' || $this->data['return_type'] == 'url') {

            //请求返回表单，通过正则处理返回到系统前端处理跳转

            //支付宝的WAP正则匹配获取URL
            if ($this->data['scene'] == 'alipay') {
                preg_match_all("/href=\"([^\"]+)/i", $this->re, $match);
                $url = $match[1][0];
            } else {
                //返回数据不是表单，就是提交错误
                if (!preg_match('/\<form/', $this->re)) {
                    $this->return['code'] = 23;
                    $this->return['msg'] = 'KG：支付错误，请联系客服。';
                    $this->return['way'] = $this->data['return_type'];
                    $this->return['str'] = null;
                    return;
                }

                preg_match('/action=\'([^\']+)\'/', $this->re, $action);
                preg_match_all('/<input type=\'hidden\' name=\'([^\']+)\' value=\'([^\']+)\'\/>/', $this->re, $matches);

                $action = $action[1];
                $data = [];
                foreach ($matches[1] as $index => $match) {
                    $data[$match] = $matches[2][$index];
                }

                $this->parameter = $data;
                $this->parameter = $this->arrayToURL();
                $this->parameter .= '&url=' . $action;
                $this->parameter .= '&method=POST';

                $url = $this->jumpURL . '?' . $this->parameter;
            }
        }

        $this->return['code'] = 0;
        $this->return['msg'] = 'success';
        $this->return['way'] = $this->data['return_type'];
        $this->return['str'] = $url;
    }

    //MD5(ValidateKey.HashKey.MerTradeID.MerProductID.MerUserID.Amount)
    public function sytMd5($pieces) {
        $signArr['ValidateKey'] = $this->key;
        $signArr['HashKey'] = $pieces['HashKey'];
        $signArr['MerTradeID'] = $pieces['MerTradeID'];
        $signArr['MerProductID'] = $pieces['MerProductID'];
        $signArr['MerUserID'] = $pieces['MerUserID'];
        $signArr['Amount'] = $pieces['Amount'];

        return md5(implode('', $signArr));
    }

    //MD5(ValidateKey=ASDWDWDF&HashKey=FEFRGFEFWEF&RtnCode=回传代码&TradeID=MerTradeID&UserID=MerUserID&Money=Amount)
    public function returnVerify($pieces) {
        $res = [
            'status'       => 0,
            'order_number' => $pieces['MerTradeID'],
            'third_order'  => '',
            'third_money'  => $pieces['Amount'] * 100,
            'error'        => '',
        ];

        $sign = $pieces['Validate'];
        $config = Recharge::getThirdConfig($pieces['MerTradeID']);

        $signArr['ValidateKey'] = $config['key'];
        $signArr['HashKey'] = $config['pub_key'];
        $signArr['RtnCode'] = $pieces['RtnCode'];
        $signArr['TradeID'] = $pieces['MerTradeID'];
        $signArr['UserID'] = $pieces['MerUserID'];
        $signArr['Money'] = $pieces['Amount'];

        if (md5(urldecode(http_build_query($signArr))) == $sign) {
            $res['status'] = 1;
        } else {
            $res['error'] = '该订单验签不通过或已完成';
        }
        
        return $res;
    }
}
