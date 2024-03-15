<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * @author viva
 */

class XXM extends BASES {

    //与第三方交互
    public function start(){

        $this->initParam();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter = array(
            'money'    => $this->money/100,
            'merchantId'    => $this->partnerID,
            'notifyURL'    => $this->notifyUrl,
            'returnURL'    => $this->returnUrl,
            'merchantOrderId'    => $this->orderID,
            'timestamp'    => time(),
        );
        $tmp = [
            'type'    => 'json',
            'goodsName'    => 'goods',
            'merchantUid'    => time(),
            'returnUrl'    => $this->returnUrl,
            'paytype'    => $this->payType,
        ];

        $this->parameter['sign'] = urlencode(md5(implode('&',$this->parameter).'&'.$this->key));
        $this->parameter = array_merge($tmp,$this->parameter);
    }
    
    public function parseRE(){
        $this->parameter['url'] = $this->payUrl;
        $this->parameter['method'] = 'POST';
        $this->return['code'] = 0;
        $this->return['msg']  = 'SUCCESS';
        $this->return['way']  = $this->data['return_type'];
        $this->return['str']  = $this->jumpURL.'?'.$this->arrayToURL();
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
            'order_number' => $input['merchantOrderNo'],
            'third_order'  => $input['orderNo'],
            'third_money'  => $input['payAmount']*100,
            'error'        => '',
        ];
        $config = Recharge::getThirdConfig($input['merchantOrderNo']);
        if (!$config) {
            $res['error'] = '订单已完成或不存在';
        } else if ($this->verify($input,$config['pub_key'])) {
                $res['status'] = 1;
        } else {
            $res['error'] = '该订单验签不通过';
        }
        return $res;
    }

    public function verify($input,$puk){
        $str = $input['orderNo'].'&'.$input['merchantOrderNo'].'&'.$input['money'].'&'.$input['payAmount'].'&'.$puk;
        return strtolower($input['sign']) == strtolower(md5($str));
    }
}
