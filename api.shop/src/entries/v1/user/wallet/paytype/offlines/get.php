<?php
use Utils\Shop\Action;

return new class extends Action {
    const TITLE = "GET 获取支付通道";
    const DESCRIPTION = "金额限制都按分来比较";
    const TYPE = "text/json";
    const QUERY = [
       "type" => "int(required) #支付方式 1 网银支付 2 支付宝 3 微信 4 QQ 5 京东",
       "money" => "int(required) #支付方式1 网银支付 2 支付宝 3 微信 4 QQ 5 京东",
   ];
    const SCHEMAs = [
       200 => [
           "id" => "int(required) #支付通道ID",
           "name" => "string(required) #收款姓名",
           "card" => "string(required) #收款卡号",
           "bank_id" => "string(required) #收户行ID（银联）",
           "bank_name" => "string(required) #收户行（银联）",
           "address" => "string(required) #开户行地址（银联）",
           "code" => "string(required) #开户行CODE（银联）",
           "payname" => "string(required) #通道名称",
           "qrcode" => "string(required) #二维码地址 ",
           "max_money" => "int(required) #最大值",
           "min_money" => "int(required) #最小值",
           "comment" => "string(required) #备注",
       ]
   ];

    public function run(){
        $verify = $this->auth->verfiyToken();
        if (!$verify->allowNext()) {
            return $verify;
        }
//        $user = (new \Logic\User\User($this->ci))->getInfo($this->auth->getUserId());
//        $userLevel = $user['ranting'];//用户层级
        $type = $this->request->getParam('type');
        $money = $this->request->getParam('money');
        if (!in_array($type,\Logic\Shop\Pay::getPayType('type')) || empty($type)){
            return $this->lang->set(13);
        }
        //是否开启小数点
        $deposit_float_point = \Logic\Shop\SystemConfig::getGlobalSystemConfig('recharge')['deposit_float_point'] ?? true;
        $temp_float = $deposit_float_point ? 100 : 0;
        $money += $temp_float;
        //线上充值限额，第三方及平台综合
        $pay = new \Logic\Shop\Pay($this->ci);
        $round_money = $pay->aroundMoney($type);
        $min = $round_money['min_money'];
        $max = $round_money['max_money'];
        if(($money < $min && $min != 0 || $money > $max && $max != 0)){
            return $this->lang->set(881, [$min/100, ($max-$temp_float)/100]);
        }
        $offline = $pay->offline($type) ?? [];
        $result = array();
        $names = ['一','二','三', '四','五','六','七','八','九','十','十一','十二'];
        $i=0;$j=0;
        $max = array(); //若充值数据过大，今日额度或者累计额度提示
        //  筛选满足金额条件的支付通道
        foreach ($offline as $val){
            $val = (array)$val;
            if($money >= $val['limit_once_min']  && $money <= $val['limit_once_max']
                || $money >= $val['limit_once_min'] && $val['limit_once_max'] == 0
                || $money <= $val['limit_once_max'] && $val['limit_once_min'] == 0 //  0 表示不限额，每个支付渠道限额
                ){  //测试不限额
                $deposit = $pay->stateMoney($val['id']);  //  第三方充值情况
                //  今日限额限制
                $max[$i] = $val['limit_day_max'] == 0 ? 0 : $val['limit_day_max'] - $deposit['day_money'];
                if($deposit['day_money']+$money <= $val['limit_day_max'] || $val['limit_day_max'] == 0
                    ){
                    //  累计限额限制
                    if($j > 11) break;
                    if($deposit['sum_money']+$money <= $val['limit_max'] || $val['limit_max'] == 0){
                        $result[$j]['pay_name'] = '通道'.$names[$j];
                        $result[$j]['id'] = $val['id'];
                        $result[$j]['bank_id'] = $val['bank_id'];
                        $result[$j]['bank_name'] = $val['bank_name'];
                        $result[$j]['bank_img'] = $val['bank_img'];
                        $result[$j]['card'] = $val['card'];
                        $result[$j]['name'] = $val['name'];
                        $result[$j]['code'] = $val['code'];
                        $result[$j]['qrcode'] = $val['qrcode'];
                        $result[$j]['address'] = $val['address'];
                        $result[$j]['min_money'] = $val['limit_once_min'];
                        $result[$j]['max_money'] = $val['limit_once_max'];
                        $result[$j]['comment'] = $val['comment'];
                        $j++;
                    }
                    $temp = $val['limit_max'] == 0 ? 0 : $val['limit_max'] - $deposit['sum_money'];
                    // 获取今日限额，累计限额中最小值，允许充值范围
                    $max[$i] = $temp == 0 ? $max[$i] : $max[$i] < $temp && $max[$i] != 0 ? $max[$i] : $temp;
                    $i++;
                }
            }
        }
        if($result)
            return $result;
        elseif($max && max($max) > 0){
            return $this->lang->set(882, [max($max)/100]);
        }else{
            return $this->lang->set(883);
        }
    }
};
