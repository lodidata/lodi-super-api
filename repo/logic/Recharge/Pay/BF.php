<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;

/**
 * 佰富
 * @author viva
 */

class BF extends BASES {

    static function instantiation(){
        return new BF();
    }

    //与第三方交互
    public function start(){

        $this->initParam();
        if(!$this->payUrl)
            $this->payUrl = 'http://defray.948pay.com:8188/api/smPay.action';
        $this->basePost();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){
        $this->parameter = array(
            'merchantNo'    => $this->partnerID,
            'netwayCode'    => $this->data['bank_data'],
            'randomNum'	    => (string) rand(1000,9999),
            'orderNum'	    => $this->orderID,
            'payAmount'     => ($this->money).'',
            'goodsName'     => 'goods',
            'callBackUrl'   => $this->notifyUrl,
            'frontBackUrl'  => $this->returnUrl,
            'requestIP'     => '103.196.124.252',
        );
        ksort($this->parameter);
        $temp = str_replace('\/','/',json_encode($this->parameter));
        $this->parameter['sign'] = strtoupper(md5($temp.$this->key));
        $temp = str_replace('\/','/',json_encode($this->parameter));
        $this->parameter = array('paramData'=>$temp);;
    }
    
    public function parseRE(){
        $re = json_decode($this->re,true);
        if($re['resultCode'] === '00'){
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = $re['CodeUrl'];
        }else{
            $this->return['code'] = 886;
            $this->return['msg']  = 'BF:'.$re['resultMsg'];
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = '';
        }
    }
    /**
     * 返回地址验证
     *
     * @param
     * @return boolean
     */
    public function returnVerify($input) {

        $rs = json_decode($input['paramData'],true);

        $res = [
            'status' => 0,
            'order_number' => $rs['orderNum'],
            'third_order'  => $rs['merchantNo'],
            'third_money'  => $rs['payAmount'],
            'error'        => '',
        ];
        $config = Recharge::getThirdConfig($rs['orderNum']);

        $temp = $rs['sign'];
        unset($rs['sign']);
        ksort($rs);
        $sign = strtoupper(md5(json_encode($rs).$config['key']));
        if($sign == $temp){
            $res['status']  = 1;
        }else{
            $res['error'] = '该订单验签不通过或已完成';
        }
        return $res;
    }
}
