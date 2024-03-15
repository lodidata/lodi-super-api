<?php
namespace Logic\Recharge\Pay;
use Logic\Recharge\Bases;
use Logic\Recharge\Recharge;
use Utils\Client;

/**
 * @author viva
 */

class KB extends BASES {

    //与第三方交互
    public function start(){
        $this->initParam();
        $this->basePost();
        $this->parseRE();
    }
    //组装数组
    public function initParam(){

        $this->parameter = array(
            'attach'       => 'a'.time(),             //附加数据
            'body'         => 'b'.time(),             //商品描述
            'create_time'  => date('YmdHis'), //创建时间
            'ip'           => Client::getIp(),        //终端IP
            'jump_url'     => $this->returnUrl,       //支付成功跳转地址
            'mch_id'       => $this->partnerID,       //商户号
            'nonce_str'    => 'c'.time(),             //随机字符串
            'out_trade_no' => $this->orderID,         //商户订单号
            'pay_type'     => $this->payType,         //支付類型
            'total_fee'    => $this->money,           //总金额
        );
        //第三方需要把回调地址 https 转换 http
        $this->parameter['notify_url'] = $this->notifyUrl;  //异步通知地址
        $this->parameter['sign'] = $this -> sign();

    }

    /**
     * 生成签名
     */
    public function sign(){

        $str = $this -> parameter;
        $signstr = 'attach='.$str['attach'].'&body='.$str['body'].'&create_time='.$str['create_time'].'&ip='.$str['ip'].'&jump_url='.$str['jump_url'].'&mch_id='.
            $str['mch_id'].'&nonce_str='.$str['nonce_str'].'&notify_url='.$str['notify_url'].'&out_trade_no='.$str['out_trade_no'].'&pay_type='.$str['pay_type'].'&total_fee='.$str['total_fee'];
        $sign = $signstr.'&key='.$this->pubKey;
        $sign = strtolower(MD5($sign));
        return $sign;
    }

    public function parseRE(){

        $re = json_decode($this->re,true);

        if($re['return_code'] == 'success' && $re['return_msg'] == 'ok'){
            $this->return['code'] = 0;
            $this->return['msg']  = 'SUCCESS';
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  = $re['pay_url'];
        }else{
            $this->return['code'] = 5;
            $this->return['msg']  = 'KB:'.$re['return_msg'];
            $this->return['way']  = $this->data['return_type'];
            $this->return['str']  =  '';

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
            'order_number' => $input['out_trade_no'],
            'third_order'  => $input['transaction_id'],
            'third_money'  => $input['total_fee'],
            'error'        => '',
        ];

        $config = Recharge::getThirdConfig($input['out_trade_no']);
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

        $str = 'attach='.$input['attach'].'&mch_id='.$input['mch_id'].'&nonce_str='.$input['nonce_str'].
            '&order_no='.$input['order_no'].'&out_trade_no='.$input['out_trade_no'].'&pay_status='.$input['pay_status'].'&pay_time='.$input['pay_time'].
            '&pay_type='.$input['pay_type'].'&total_fee='.$input['total_fee'].'&transaction_id='.$input['transaction_id'];

        $sign = $str.'&key='.$puk;
        $sign = strtolower(MD5($sign));

        if($sign == $input['sign']){
            return true;
        }else{
            return false;
        }
    }
}
