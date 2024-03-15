<?php
namespace Logic\Recharge\Traits;

use Utils\Utils;

trait RechargePay{
    /*
     * 数据检验
     */
    public function allowPay(int $third_id,float $money,string $order_number){
        $customer_id = \DB::table('customer')->where('customer',CUSTOMER)->value('id');
        if($customer_id){
            $third = \DB::table('passageway')->where('id',$third_id)->where('customer_id',$customer_id)->first();
            if($third && $money && $order_number) {
                $third = (array)$third;
                if( $third['min_money'] != 0 && $money < $third['min_money'] ||
                    $third['max_money'] != 0 && $money > $third['max_money']) {
                    $res['code'] = 890;
                    $res['msg'] = \Logic\Define\Lang::get(890,[$third['min_money']/100,$third['max_money']/100]);
                }elseif( $third['money_day_stop'] != 0 && $money + $third['money_day_used']  > $third['money_day_stop'] ||
                    $third['money_stop'] != 0 && $money + $third['money_used'] > $third['money_stop']) {
                    $t1 = $third['money_day_stop'] - $third['money_day_used'];
                    $t2 = $third['money_stop'] - $third['money_used'];
                    $tmp = $t1 > $t2  ? $t1 : $t2;
                    $res['code'] = 891;
                    $res['msg'] = \Logic\Define\Lang::get(891,[$tmp/100]);
                }else {
                    $this->next = true;
                    $res = true;
                }
            }else {
                $res['code'] = 897;
                $res['msg'] = \Logic\Define\Lang::get(897);
            }
        }else{
            $res['code'] =  894;
            $res['msg'] = \Logic\Define\Lang::get(894);
        }
        return $res;
    }

    //开始调用第三方支付
    public function runThirdPay(int $third_id,float $money,string $order_number,string $return_url = null,string $bank_code = null,string $client_ip = null,$pl){
        $data = \DB::table('passageway AS way')->leftJoin('pay_config AS config','way.pay_config_id','=','config.id')
            ->leftJoin('pay_channel AS channel','config.channel_id','=','channel.id')
            ->where('way.id',$third_id)
            ->first([
                'way.id','way.scene','way.action','way.name as vendername','way.payurl','way.bank_data','way.return_type','way.field','way.customer_id',
                'way.return_type','way.show_type','way.active_rule',
                'channel.code','channel.rule','channel.moneys','channel.id as pay_id','channel.name as payname',
                'config.app_id','config.app_secret','config.app_site','config.key','config.pub_key',
                'config.token','config.terminal','config.partner_id'
            ]);
        if($data){
            $data = (array)$data;
            $data = self::decryptConfig($data);
            $notify = \DB::table('notify')->where('customer_id',0)
                ->where('status','=','enabled')->pluck('admin_notify');
            if($notify) {
                $res = $this->moneyRules($data['rule'],$money,$data['moneys']);
                if($res === true) {
                    if ($this->verifyData($data) && $this->existThirdClass($data['code'])) {
                        try {
                            $code = strtolower($data['code']);
                            $n = array_random($notify->toArray()).DIRECTORY_SEPARATOR.$code.DIRECTORY_SEPARATOR.'callback';

                            $randStr = 'O'.Utils::randStr(12).time();
                            $n .= '/' . $randStr;
                            $this->redis->setex('payAllowOrder:'.$order_number, 24*60*60, $randStr);

                            $data['url_return'] = $return_url;
                            $data['url_notify'] = $n;
                            $data['order_number'] = $order_number;
                            $data['money'] = $money;
                            $data['bank_code'] = $bank_code;
                            $data['client_ip'] = $client_ip;
                            $data['pl'] = $pl;
                            $order = [
                                'customer_id' => $data['customer_id'],
                                'passageway_id' => $third_id,
                                'order_number' => $order_number,
                                'order_money' => $money,
                                'client_ip' => $client_ip,
                            ];
                            self::addLogBySql($order, 'order');

                            $obj = $this->getThirdClass($data['code']);
                            $result = $obj->run($data);
                            if($result['code'] == 0) {
                                $result['id'] = $data['id'];
                                $result['pay_id'] = $data['pay_id'];
                                $result['active_rule'] = json_decode($data['active_rule'],true);
                                $result['payname'] = $data['payname'];
                                $result['scene'] = $data['scene'];
                                $result['vendername'] = $data['vendername'];
                            }
                            $this->next = true;
                        } catch (\Exception $e) {

                            if($e instanceof \Illuminate\Database\QueryException){
                                $result['code'] = 1062;
                                $result['msg'] = \Logic\Define\Lang::get(1062);
                            }else{
                                $result['code'] = 900;
                                $result['msg'] = \Logic\Define\Lang::get(900);
                            }


                        }
                    } else {
                        $result['code'] = 892;
                        $result['msg'] = \Logic\Define\Lang::get(892);
                    }
                }else{
                    $result = $res;
                }
            }else{
                $result['code'] = 898;
                $result['msg'] = \Logic\Define\Lang::get(898);
            }
        }else{
            $result['code'] = 893;
            $result['msg'] = \Logic\Define\Lang::get(893);
        }
        return $result;
    }

    public function verifyData($data){
        $vefifys = ['payurl','return_type','code','key','pub_key'];
        foreach ($vefifys as $val){
            if(!(isset($data[$val]) && $data[$val])){
                return false;
            }
        }
        return true;
    }

    public function moneyRules($rule,$money,$moneys = null){
//        0：不限，1：整数，2：小数，3:固定金额  与数据库 pay_channel   rule 取值对应
        switch ($rule){
            case 1:
                if($money%100) return ['code'=>902,'msg'=>\Logic\Define\Lang::get(902)];
                break;
            case 2:
                if(!($money%100)) return ['code'=>901,'msg'=>\Logic\Define\Lang::get(901)];
                break;
            case 3:
                if(!in_array($money/100,explode(',',$moneys))) return ['code'=>903,'msg'=>\Logic\Define\Lang::get(903,[$moneys])];
                break;
            case 4:
                if ($money < 10000 || ($money / 100 % 100)!=0)  return ['code'=>904,'msg'=>\Logic\Define\Lang::get(904)];
                break;
            default:
                return true;
        }
        return true;
    }
}