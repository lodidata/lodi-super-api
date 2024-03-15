<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * @author viva
 */

class OEM extends BASES {

    private $tokenUrl;
    private $orderUrl;
    //与第三方交互
    public function start(){
        //测试商户号，密钥 ，请求域名
        $this->initParam();
        $this->payUrl = $this->tokenUrl;
        $this->basePost();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->tokenUrl = $this->payUrl . 'unipaycenter/otc/getToken';
        $this->orderUrl = $this->payUrl . 'unipaycenter/otc/gatepay';
        $this->parameter = array(
            'uid'    => $this->partnerID,
            'orderId'    => $this->orderID,
            'orderAmount'    => $this->money/100,
            'timestamp'    => time(),
        );
        $this->parameter['sign'] = strtoupper($this->currentMd5());
    }
    
    public function parseRE(){
        $re = json_decode($this->re,true);
        if(isset($re['code']) && $re['code'] == 200) {
            $para = [
                'token' => $re['token'],
                'uid' => $this->partnerID,
                'orderId' => $this->orderID,
                'orderAmount' => $this->money/100,
                'productName' => 'Goods',
                'customerName' => 'cus'.time(),
                'customerPhone' => random_int(13500000001,13599999991),
                'notify_url' => $this->notifyUrl,
                'version' => '1.3',
                'paymentType' => $this->payType,
            ];
            $this->parameter = $para;
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->showType;
            $this->return['str']  = $this->orderUrl.'?'.$this->arrayToURL();
        }else {
            $this->return['code'] = 55;
            $this->return['msg'] = 'OEM:'.$re['msg'] ?? '第三方未知错误';
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
    public function returnVerify($input) {
        $res = [
            'status' => 0,
            'order_number' => $input['orderId'],
            'third_order'  => isset($input['otcpayOrderId']) ? $input['otcpayOrderId'] : '',
            'third_money'  => isset($input['orderAmount']) ? $input['orderAmount']*100 : 0,
            'error'        => '',
        ];
        if($input['status'] == '88') {
            $config = Recharge::getThirdConfig($input['orderId']);
            if (!$config) {
                $res['error'] = '订单已处理';
            } else if ($this->verify($input, $config['pub_key'])) {
                $res['status'] = 1;
            } else {
                $res['error'] = '该订单验签不通过';
            }
        }else{
            $res['error'] = '该订单未支付';
        }
        return $res;
    }

    public function verify($input,$puk){
        $this->key = $puk;
        $sign = $input['sign'];
        unset($input['sign']);
        $this->parameter = $input;
        $tmpSign = $this->currentMd5();
        return strtolower($sign) == strtolower($tmpSign);
    }
}
