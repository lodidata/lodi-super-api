<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

class CHUANGH extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->parseRE();
    }

    //组装数组
    public function initParam(){
        $this->parameter = [
            'merId' => $this->partnerID,
            'orderNo' => $this->orderID,
            'amount' => $this->money/100,
            'payType' => $this->payType,
            'goodsName' => 'P'.time().'',
            'notifyUrl' => $this->notifyUrl,
        ];
        ksort($this->parameter);
        $str = implode('',$this->parameter);
        $this->formatPrivateKey();
        openssl_sign($str,$sign_info,$this->key,OPENSSL_ALGO_MD5);
        $this->parameter['sign'] = base64_encode($sign_info);
    }
    public function parseRE(){
        //扫码直接curl请求
        if($this->showType == 'code'){
            $this->basePost();
            $re = json_decode($this->re,true);
            if($re['code'] == 1) {
                $this->return['code'] = 0;
                $this->return['msg'] = '';
                $this->return['way'] = $this->showType;
                $this->return['str'] = $re['data']['url'];
            }else {
                $this->return['code'] = 39;
                $this->return['msg'] = 'CHUANGH:'.$re['msg'];
                $this->return['way'] = $this->showType;
                $this->return['str'] = '';
            }
        }else {
            $this->parameter['sign'] = urlencode($this->parameter['sign']);
            $this->parameter['url'] = urlencode($this->payUrl);
            $this->parameter['method'] = 'POST';
            $this->return['code'] = 0;
            $this->return['msg'] = '';
            $this->return['way'] = $this->showType;
            $this->return['str'] = $this->jumpURL . '?' . $this->arrayToURL();
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
            'order_number' => $parameters['orderNo'],
            'third_order' => $parameters['trxNo'],
            'third_money' => $parameters['amount']*100,
            'error' => '',
        ];

        if(isset($parameters['status']) && $parameters['status'] == 1){
            $config = Recharge::getThirdConfig($parameters['orderNo']);
            if($this->verifyData($parameters,$config['pub_key'])){
                $res['status'] = 1;
            }else
                $res['error'] = '该订单验签不通过或已完成';
        }else
            $res['error'] = '该订单未支付';
        return $res;
    }
    public function verifyData($parameters,$key) {
        $sign = $parameters['sign'];
        unset($parameters['sign']);
        ksort($parameters);
        $str = implode('',$parameters);
        $this->formatPublicKey($key);
        $publicKey = openssl_get_publickey($this->pubKey);
        return openssl_verify($str, base64_decode($sign), $publicKey,OPENSSL_ALGO_MD5);
    }

}
