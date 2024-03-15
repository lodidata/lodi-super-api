<?php
use Utils\Www\Action;
use Logic\Recharge\Recharge;
return new class extends Action {
    const TITLE = '平台请求支付接口';
    const QUERY = [
        'order_number' => 'string(optional) #订单ID',
        'money' => 'int(optional) #金额：分',
        'third' => 'int(optional) #通道ID',
        'back_code' => 'string(optional) #银行卡CODE',
        'return_url' => 'int(optional) #同步回调地址',
        'client_ip' => 'string(optional) #客户IP地址',
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            'code' => 'int #状态码0为OK：只有为0才会有way以下的信息',
            'msg'  => 'string #错误信息，统一 SUCCESS为成功',
            'way' =>'string #类型 （取值 code:二维码，jump,url:跳转链接）',
            'str' =>'string #支付二维码',
            'money' =>'int #第三方实际金额',
            'id' => 'int #通道ID',
            'pay_id' => 'int #渠道ID',
            'payname' => 'string #渠道名',
            'vendername' => 'string #通道名',
        ],
    ];
    public function run() {
        $order_number = $this->request->getParam('order_number');
        $money = $this->request->getParam('money');
        $third = $this->request->getParam('third');
        $return_url = $this->request->getParam('return_url');
        $back_code = $this->request->getParam('back_code');
        $client_ip = $this->request->getParam('client_ip');
        $pl = isset($this->request->getHeaders()['HTTP_PL']) ? $this->request->getHeaders()['HTTP_PL'][0] : null;
        $data = array_merge($this->request->getParams(),['customer'=>CUSTOMER]);
        Recharge::addLogByTxt($data,'order');
        Recharge::logger($this,$data,'order');
        $pay = new Logic\Recharge\Recharge($this->ci);
        $verify = $pay->allowPay((int)$third,(float)$money,(string)$order_number);
        if (!$pay->allowNext()) {
            return $verify;
        }
        //开始调用相应第三方支付请求
        $re = $pay->runThirdPay((int)$third,(float)$money,(string)$order_number,(string)$return_url,(string)$back_code,(string)$client_ip,$pl);
        if (!$pay->allowNext()) {
            Recharge::addLogByTxt($re,'pay_error');
            Recharge::logger($this,$re,'pay_error');
        }
        return $re;
    }
};