<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;


/**
 * 星付
 * @author viva
 */


class XF extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        //扫码
        $this->parameter['service'] = $this->data['action'];
        $this->parameter['version'] = '1.0.0.0';
        $this->parameter['merId'] = $this->partnerID;
        $this->parameter['typeId'] = $this->payType;
        $this->parameter['tradeNo'] = $this->orderID;
        $this->parameter['tradeDate'] = date('Ymd');
        $this->parameter['amount'] = $this->money/100;
        $this->parameter['notifyUrl'] = $this->notifyUrl;    //返回 success
        $this->parameter['extra'] = 'extra';
        $this->parameter['summary'] = 'title';
        $this->parameter['expireTime'] = 20*60;
        $this->parameter['clientIp'] = $this->data['client_ip'] ?? '127.0.0.1';
        if($this->data['action'] == 'TRADE.B2C') {
            unset($this->parameter['typeId']);
            $this->parameter['bankId'] = $this->payType;
        }
        $this->sort = false;

        $this->parameter['sign'] = MD5($this->arrayToURL().$this->key);
        // 准备待签名数据
        $this->parameter = $this->arrayToURL();
    }

    public function parseRE(){
        if($this->data['action'] == 'TRADE.SCANPAY') {
            $this->basePost();
            $re = $this->parseXML($this->re);
            $str = $re['detail']['qrCode'] ? base64_decode($re['detail']['qrCode']) : '';
        }else{
            $this->parameter .= '&url=' . $this->payUrl;
            $this->parameter .= '&method=POST';
            $str = $this->jumpURL.'?'.$this->parameter;
        }
        if($str){
            $this->return['code'] = 0;
            $this->return['msg'] = 'SUCCESS';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $str;
        }else{
            $re['detail']['desc'] = $re['detail']['desc'] ?? '';
            $this->return['code'] = 886;
            $this->return['msg'] = 'XF:'.$re['detail']['desc'];
            $this->return['way'] = $this->showType;
            $this->return['str'] = '';
        }
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
        $res = [
            'status' => 0,
            'order_number' => $parameters['tradeNo'],
            'third_order' => $parameters['opeNo'],
            'third_money' => $parameters['amount']*100,
            'error' => '',
        ];
        if($parameters['status'] == 1){
            $config = Recharge::getThirdConfig($parameters['tradeNo']);
            if($this->verifyData($parameters,$config['pub_key'])){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';
        return $res;
    }
    public function verifyData($data,$key) {
        $str = sprintf(
            "service=%s&merId=%s&tradeNo=%s&tradeDate=%s&opeNo=%s&opeDate=%s&amount=%s&status=%s&extra=%s&payTime=%s",
            $data['service'],
            $data['merId'],
            $data['tradeNo'],
            $data['tradeDate'],
            $data['opeNo'],
            $data['opeDate'],
            $data['amount'],
            $data['status'],
            $data['extra'],
            $data['payTime']
        );
        $mySign = md5($str.$key);
        if (strcasecmp($mySign, $data['sign']) == 0) {
            return true;
        } else {
            return false;
        }
    }
}
