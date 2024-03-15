<?php
use Utils\Shop\Action;

return new class extends Action {
    const TITLE = "PUT pc端 在线充值  (提交)";
    const TYPE = "text/json";
    const PARAMs = [
       "receipt_id" => "int(required) #支付通道ID",
       "money" => "string(required) #充值金额",
       "discount_active" => "pay_code(required) #优惠活动id",
       "pay_code" => "pay_code(required) #非必传参数， 某些第三方银行必须要银行则必传参数 ，取bank_data中的pay_code值"
   ];
    const SCHEMAs = [
       200 => [
           "url" => "string(required) #支付URL",
           "showType" => "string(required) #方式（code:二维码，url,jump跳转--jump是由于APP内部浏览器打不开原因所加）",
           "money" => "string(required) #支付金额"
       ]
   ];
//{"money":3000,"discount_active":"0","receipt_id":10298,"pay_type":"2","type":2}

    public function run(){
        $verify = $this->auth->verfiyToken();
        if (!$verify->allowNext()) {
            return $verify;
        }
        $user['id'] = $this->auth->getUserId();
        $req = $this->request->getParams();
        $receipt_id = $req['receipt_id'] ?? '';//第三方支付id
        $deposit_money = $req['money'] ?? 0;//提现金额，以分为单位
        $discount_active = $req['discount_active'] ?? 0;//活动id
        $pay_code = $req['pay_code'] ?? '';

        $config = \Logic\Shop\SystemConfig::getGlobalSystemConfig('recharge');
        if ($config['stop_deposit']) {
            return $this->lang->set(300);
        }
        if ($receipt_id && $deposit_money && $user['id']){//线上充值
            //是否开启小数点
            $deposit_float_point = $config['deposit_float_point'] ?? true;
            $deposit_point_money = 0;
            if($deposit_float_point && ceil($deposit_money) == $deposit_money){
                $deposit_point_money = rand(1, 9) * 10;
            }
            $deposit_money = $req['money'] + $deposit_point_money;//按分存储
            $ip = \Utils\Client::getIp();
            $result = (new Logic\Shop\Recharge($this->ci))->onlinePayWebSite($deposit_money, $user['id'], $ip, $discount_active, $receipt_id,$pay_code);
            if($result['code'] != 0){
                return $this->lang->set(886,[$result['msg']]);
            }
            return ['url' => $result['str'], 'showType' => $result['way'],'money'=>$result['money']];//pay_info
        }else{
           return $this->lang->set(13);
       }
    }
};
