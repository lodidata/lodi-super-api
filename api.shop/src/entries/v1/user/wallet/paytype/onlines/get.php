<?php
use Utils\Shop\Action;

return new class extends Action {
    const TITLE = 'GET 获取支付通道';
    const DESCRIPTION = '金额限制都按分来比较';
    const TYPE = 'application/json';
    const QUERY = [
        'type'  => 'int(required) #支付方式 1网银支付 2支付宝 3微信 4QQ 5京东',
        'money' => 'int(required) #支付金额',
    ];
    const SCHEMAs = [
        'data' => [
            'id'          => 'int(required) #支付通道ID',
            'name'        => 'string(required) #通道名称',
            'pay_id'      => 'int(required) #渠道ID',
            'pay_scene'   => 'string(required) #类型',
            'pay_way'     => 'string(required) #支付方式中文',
            'pay_way_str' => 'string(required) #支付方式str',
            'max_money'   => 'int(required) #最大值',  //分
            'min_money'   => 'int(required) #最小值',   //分
            'comment'     => 'string(required) #备注',   //分
            'bank_list'   => 'enum(0,1) #银行类型该参数才有效，为1代表必须跳转到相应银行列表选择相应银行',
            'bank_data'   => [
                'code'     => '(required) #银行代码',
                'logo'     => 'string(required) #LOGO',
                'name'     => 'string(required) #银行名',
                'pay_code' => 'string(required) #支付值（请求支付时传该参数）',
            ],
        ],
    ];

    public function run() {
        $verify = $this->auth->verfiyToken();

        if (!$verify->allowNext()) {
            return $verify;
        }

//        $user = (new \Logic\User\User($this->ci))->getInfo($this->auth->getUserId());
//        $userLevel = $user['ranting'];  //用户层级

        $type = $this->request->getParam('type');
        $money = $this->request->getParam('money');
        if (!in_array($type, \Logic\Shop\Pay::getPayType('type')) || empty($type)) {
            return $this->lang->set(13);
        }

        //是否开启小数点
        $global = \Logic\Shop\SystemConfig::getGlobalSystemConfig('recharge');
        $deposit_float_point = $global['deposit_float_point'] ?? true;
        $temp_float = $deposit_float_point ? 100 : 0;
        $money += $temp_float;

        //线上充值限额，第三方及平台综合
        $pay = new \Logic\Shop\Pay($this->ci);
        $online = $pay->getOnlinePassageway($type) ?? [];

        //如果是PC端需要筛选支付渠道，H5的渠道不显示
        if (isset($_SERVER['HTTP_PL']) && $_SERVER['HTTP_PL'] == 'pc') {
            $online = array_filter($online, function ($item) {
                return $item['show_type'] == 'code';
            });
        }

        $result = [];
        $names = ['一', '二', '三', '四', '五', '六', '七', '八', '九', '十', '十一', '十二'];
        $i = 0;
        $j = 0;
        $max = [];//若充值数据过大，今日额度或者累计额度提示
//        $levelId = \DB::table('user_level')->where('level',$userLevel)->value('id');
//        //支付层级
//        $thirds = \DB::table('level_online')
//                     ->where('level_id', '=', $levelId)
//                     ->pluck('pay_plat')
//                     ->toArray();

        //  筛选满足金额条件的支付通道
        foreach ($online as $val) {
//            if (in_array($val['pay_name'], $thirds)) {  //支付与层级有关
                if ($money >= $val['min_money'] && $money <= $val['max_money']
                    || $money >= $val['min_money'] && $val['max_money'] == 0
                    || $money <= $val['max_money'] && $val['min_money'] == 0 //0 表示不限额，每个支付渠道限额
                ) {
                    //  今日限额限制
                    $max[$i] = $val['money_day_stop'] == 0 ? 0 : $val['money_day_stop'] - $val['money_day_used'];
                    if ($val['money_day_used'] + $money <= $val['money_day_stop'] || $val['money_day_stop'] == 0) {
                        //  累计限额限制
                        if ($j > 11) break;
                        if ($val['money_used'] + $money <= $val['money_stop'] || $val['money_stop'] == 0) {
                            $result[$j]['id'] = $val['id'];
                            $result[$j]['sort'] = $val['sort'];
                            $result[$j]['pay_id'] = $val['pay_id'];
                            $result[$j]['min_money'] = $val['min_money'];
                            $result[$j]['max_money'] = $val['max_money'] != 0 ? $val['max_money'] - $temp_float : $val['max_money'];
                            $result[$j]['pay_scene'] = $val['scene'];
                            $result[$j]['comment'] = $val['comment'];
                            if ($val['show_type'] == 'code') {
                                $result[$j]['pay_way'] = '扫码支付';
                                $result[$j]['pay_way_str'] = 'code';
                            } else {
                                $result[$j]['pay_way'] = 'H5支付';
                                $result[$j]['pay_way_str'] = 'h5';
                            }
                            if ($val['link_data']) {
                                $result[$j]['bank_list'] = 1;
                                $result[$j]['bank_data'] = $pay->decodeOnlineBank($val['link_data']);
                            } else {
                                $result[$j]['bank_list'] = 0;
                                $result[$j]['bank_data'] = [];
                            }
                            $j++;
                        }
                        $temp = $val['money_stop'] == 0 ? 0 : $val['money_stop'] - $val['money_used'];

                        // 获取今日限额，累计限额中最小值，允许充值范围
                        $max[$i] = $temp == 0 ? $max[$i] : $max[$i] < $temp && $max[$i] != 0 ? $max[$i] : $temp;
                        $i++;
                    }
                }
//            }
        }

        //支付通道通过sort排序
        usort($result, function ($prev, $next) {
            return $prev['sort'] - $next['sort'];
        });

        foreach ($result as $index => $item) {
            $result[$index]['name'] = '通道' . $names[$index];

            //删除排序字段
            unset($result[$index]['sort']);
        }

        if ($result) {
            return $result;
        } else if ($max && max($max) > 0) {
            return $this->lang->set(882, [max($max) / 100]);
        } else {
            return $this->lang->set(883);
        }
    }
};
