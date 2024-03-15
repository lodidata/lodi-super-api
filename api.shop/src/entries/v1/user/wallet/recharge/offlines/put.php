<?php
use Utils\Shop\Action;
return new class extends Action {
    const TITLE = "PUT 线下充值  (提交)";
    const TYPE = "text/json";
    const PARAMs = [
       "type" => "int(required) #存款类型ID",
       "bank" => "(required) #类型存款ID或存款账号",
//           "deposit_type" => "int(required) #存款方式ID   三端统一，弃用",
       "deposit_name" => "string(required) #存款人",
       "receipt_id" => "string(required) #收款通道ID",
       "money" => "int(required) #id(存款金额)",
       "deposit_time" => "int(required) #id(存款时间)",
       "discount_active" => "string(required) #优惠活动id"
   ];
    const SCHEMAs = [
       200 => []
   ];

    public function run(){
        $verify = $this->auth->verfiyToken();
        if (!$verify->allowNext()) {
            return $verify;
        }

        $user['id']= $this->auth->getUserId();
        $req = $this->request->getParams();
        $global = \Logic\Shop\SystemConfig::getGlobalSystemConfig('recharge');
        if(isset($global['canNotDepositOnPending']) && $global['canNotDepositOnPending'] == false ) {
            $sql = "select count(1) as cnt from funds_deposit where user_id={$user['id']} and  status='pending' and pay_type !=0 and !FIND_IN_SET('online',state)";
            $depositData = DB::select($sql);
            if ($depositData && isset($depositData[0]) && isset($depositData[0]->cnt) && $depositData[0]->cnt > 0) {
              return  $this->lang->set(895);
            }
        }

        $recharge_repeat = $this->redis->get('recharge_repeat'.$user['id']);
        if($recharge_repeat && $recharge_repeat == json_encode($req)){
            return $this->lang->set(885);
        }else{
            $this->redis->setex('recharge_repeat'.$user['id'], 5, json_encode($req));
        }
        $type = $req['type'] ?? 0;// 1银行 2支付宝  3微信 4QQ  5京东
        //银行
        if($type == 1) {
            $customer_bank = $req['bank'];//  付款银行ID  或者付款账号
            $customer_card = '';
        }else{
            $customer_bank = 0;//  付款银行ID  或者付款账号
            $customer_card = $req['bank'];
        }
//        $deposit_type = $req['deposit_type'] ;  //存款方式 银行转账才有该参数
        $deposit_type = null; //三端统一去掉该参数
        $deposit_name = $req['deposit_name']; //付款人名
        $boss_bank = $req['receipt_id']; //收款ID
        $deposit_money = $req['money']; //存款金额
        $deposit_time = $req['deposit_time'] ?? date("Y-m-d H:i:s");
        $discount_active = empty($req['discount_active'])? 0 : $req['discount_active'];   // 活动ID
        //限额
        if(!$this->limitMoney($boss_bank, $deposit_money)) {
            return $this->lang->set(896);
        }
//        $config = \Logic\Set\SystemConfig::getGlobalSystemConfig('lottery');
        if ($global['stop_deposit']) {
            return $this->lang->set(300);
        }

        if($type ==  '2'){  //  支付宝
           $deposit_type = '6';
        }else if ($type ==  '3'){  //  微信
           $deposit_type = '7';
        }else if ($type ==  '5'){  // 京东
           $deposit_type = '9';
        }
        if($req['bank'] && $deposit_name && $boss_bank && $deposit_money) {
            $ip = Utils\Client::getIp();
            $recharge = new Logic\Shop\Recharge($this->ci);
            $recharge->handDeposit(
                $user['id'], $deposit_money, $deposit_name, $deposit_time,
                (int)$deposit_type, (int)$customer_bank, (int)$boss_bank,
                $ip, $discount_active, $customer_card, $type
            );
            return $this->lang->set(884);
        }else{
            return $this->lang->set(10);
        }
    }

    public function limitMoney($pass_id,$money){
        // 按分存储的
        $base = \Logic\Shop\SystemConfig::getGlobalSystemConfig('recharge')['recharge_money'];
        $pass_money = \DB::table('bank_account')->where('id', $pass_id)->first(['limit_once_min','limit_once_max']);
        if( $base['recharge_min'] == 0 ) {
            $min = $pass_money->limit_once_min;
        }else {
            $min = max($base['recharge_min'] , $pass_money->limit_once_min);
        }

        if( $base['recharge_max'] == 0 ) {
            $max = $pass_money->limit_once_max;
        }else {
            $max = min($base['recharge_max'] , $pass_money->limit_once_max);
        }
        if($max != 0) {
            if ($money >= $min && $money <= $max) {
                return true;
            }else {
                return false;
            }
        }else if ($money >= $min) {
            return true;
        }else {
            return false;
        }
    }
};
