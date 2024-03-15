<?php
namespace Logic\Shop;
/*
 * 注意，数据库存储的金额都是以分为单位，注意啥时候是分啥时候是元
 */
use Logic\Logic;
use Illuminate\Database\Capsule\Manager as Capsule;

class Pay extends Logic {
    public $vender;
    public $data = [
        1 => ['name' => '网银支付', 'img' => 'http://tccom.oss-cn-shenzhen.aliyuncs.com/bank/bank.png'],
        2 => ['name' => '支付宝', 'img' => 'http://tccom.oss-cn-shenzhen.aliyuncs.com/bank/zfb.png'],
        3 => ['name' => '微信', 'img' => 'http://tccom.oss-cn-shenzhen.aliyuncs.com/bank/wx.png'],
        4 => ['name' => 'QQ', 'img' => 'http://tccom.oss-cn-shenzhen.aliyuncs.com/bank/qq.png'],
        5 => ['name' => '京东钱包', 'img' => 'http://tccom.oss-cn-shenzhen.aliyuncs.com/bank/jdqb.png'],
        6 => ['name' => '云闪付', 'img' => 'https://res1.szzcr.com/fenghcp/0d0f2208c4620726ac6f8a948f62a028.png'],
    ];
    public $return = [
        'code' => 0,     //统一 0 为OK
        'msg'  => '',    //统一 SUCCESS为成功
        'way'  => '',  //返回类型 （取值 code:二维码，url:跳转链接，json：）
        'str'  => '',  //
    ];

    static function getPayType($key = null) {
        $data = [
            'type' => [
                'unionpay' => 1,
                'alipay'   => 2,
                'wx'       => 3,
                'qq'       => 4,
                'jd'       => 5,
                'ysf'      => 6,
            ],
            'name' => [
                'unionpay'=>'网银支付',
                'alipay'=>'支付宝',
                'wx'=>'微信',
                'qq'=>'QQ',
                'jd'=>'京东钱包',
                'ysf'=>'云闪付'
            ],
        ];
        return $key ? $data[$key] : $data;
    }

    //整理获取依据支付平台获取线上充值渠道
    public function tinyOnlineChannel() {
        $data = Pay::getPayType('type');

        $channels = (new Recharge($this->ci))->getOnlineChannel();

        $res = [];
        if ($channels && is_array($channels)) {
            //如果是PC端需要筛选支付渠道，H5的渠道不显示
            if (isset($_SERVER['HTTP_PL']) && $_SERVER['HTTP_PL'] == 'pc') {
                $channels = array_filter($channels, function ($channel) {
                    return $channel['show_type'] == 'code';
                });
            }
            foreach ($channels as $val) {
                $k = $data[$val['scene']];
                if (isset($res[$k])) {
                    if ($res[$k]['min_money'] > $val['min_money']) {
                        $res[$k]['min_money'] = $val['min_money'];
                    }
                    if ($res[$k]['max_money'] < $val['max_money'] && $res[$k]['max_money'] != 0) {
                        $res[$k]['max_money'] = $val['max_money'];
                    }
                } else {
                    $res[$k] = $val;
                    $res[$k]['id'] = $k;
                }
            }
        }

        return $res;
    }

    //获取依据支付平台获取线上充值渠道
    public function getOnlineChannel() {
        $payChannels = $this->tinyOnlineChannel();

        $round = [
            'max_money' => 0,
            'min_money' => 0,
        ];

        if ($payChannels) {
            // 按分存储的
//            $base = SystemConfig::getGlobalSystemConfig('recharge')['recharge_money'];
            $base = \Logic\Shop\SystemConfig::getGlobalSystemConfig('recharge')['recharge_money'];

            $base_min = $base['recharge_min'];
            $base_max = $base['recharge_max'];

            $channel = $this->getChannel('online');

            foreach ($payChannels as &$payChannel) {
                $payChannel['name'] = $this->data[$payChannel['id']]['name'];
                $payChannel['imgs'] = $this->data[$payChannel['id']]['img'];
                $payChannel['d_title'] = $channel[$payChannel['id']]['title'] ?? '';

                if ($base_min > $payChannel['min_money']) {
                    $payChannel['min_money'] = $base_min;
                }

                if ($base_max < $payChannel['max_money'] && $base_max != 0) {
                    $payChannel['max_money'] = $base_max;
                }
            }

            $round = [
                'max_money' => max(array_map(function ($val) {
                    return $val['max_money'];
                }, $payChannels)),

                'min_money' => min(array_map(function ($val) {
                    return $val['min_money'];
                }, $payChannels)),
            ];
        }

        return [
            'money' => $round,
            'type'  => array_values(array_sort($payChannels, 'id')),
        ];
    }

    //线上获取具体通道
    public function getOnlinePassageway($type) {
        $types = array_flip(Pay::getPayType('type'));
        $res = (new Recharge($this->ci))->getOnlineChannel($types[$type]);
        return $res;
    }

    //获取线上银联付款时某些第三方支付的银行
    public function decodeOnlineBank($json) {
        $pay_bank = json_decode($json, true);
        $banks = '"' . implode('","', array_keys((array)$pay_bank)) . '"';
        $data = \Model\Bank::select(['code', 'name', 'logo'])
                           ->whereRaw("code IN ({$banks})")
                           ->get()
                           ->toArray();

        foreach ($data as $k => $val) {
            $result[$k] = $val;
            $result[$k]['pay_code'] = $pay_bank[$val['code']];
        }

        return $result ?? [];
    }

    //获取充值类型
    public function getType() {
        $data = $this->offline(null) ?? [];
        $type = [];
        $channel = $this->getChannel('offline');

        foreach ($data as $key => $val) {
            $val = (array)$val;
            $type[$val['type']]['id'] = $val['type'];
            $type[$val['type']]['name'] = $val['type'] == 1 ? "银行卡入款" : $this->data[$val['type']]['name'];
            $type[$val['type']]['imgs'] = $this->data[$val['type']]['img'];
            $type[$val['type']]['d_title'] = $channel[$val['type']]['title'] ?? '';
        }

        return array_values(array_sort($type, 'id'));
    }

    public function getChannel(string $t) {
        $result = [];
        $channel = \DB::table('funds_channel')
            ->select(['*'])->where('status', '=', 1)
            ->where('show', '=', $t)->get()->toArray();

        foreach ($channel ?? [] as $v) {
            $v = (array)$v;
            $result[$v['type_id']] = $v;
        }

        return $result;
    }

    //依据线下充值数据获取相应数据
    public function offline(int $type = null) {
        $offline = \DB::table('bank_account')->select([
            'bank_account.*',
            'bank.name as bank_name',
            'bank.code as code',
            'bank.h5_logo as bank_img'
        ])->leftJoin('bank', 'bank_account.bank_id', '=', 'bank.id')
            ->whereRaw('FIND_IN_SET("enabled",bank_account.`state`)');

        if ($type) {
            $offline->where('bank_account.type', '=', $type);
        }
        return $offline->orderBy('bank_account.sort')->get()->toArray();
    }

    //存款额度
    public function stateMoney(int $account_id) {
        $day = date('Y-m-d');
        $day_money = \DB::table('funds_deposit')->where('receive_bank_account_id', '=', $account_id)
                         ->where('status', '=', 'paid')
                         ->where('recharge_time', '>=', $day)
                         ->where('recharge_time', '<=', $day . ' 59:59:59')
                         ->value('money');
        $sum_money = \DB::table('funds_deposit')->where('receive_bank_account_id', '=', $account_id)
                         ->where('status', '=', 'paid')
                         ->value('money');

        $re['day_money'] = $day_money ?? 0;
        $re['sum_money'] = $sum_money ?? 0;
        return $re;
    }

    public function limitOffline($type) {
        $offline = \DB::table('bank_account')
                          ->whereRaw("FIND_IN_SET('enabled',state)")
                          ->select([
                              $this->db->getConnection()
                                       ->raw('MIN(limit_once_min) as min_money'),
                              $this->db->getConnection()
                                       ->raw('MAX(limit_once_max) as max_money'),
                              $this->db->getConnection()
                                       ->raw('MIN(limit_once_max) as max_temp'),
                          ]);

        if ($type) {
            $offline->where('type', '=', $type);
        }

        return (array)$offline->first();
    }

    //线上获取第三方及平台设制金额的综合值，第三方平台的并集最大与平台的差集最小
    public function aroundMoney($type = null) {
        $rand = $this->limitOffline($type);

        // 按分存储的
//        $base = SystemConfig::getGlobalSystemConfig('recharge')['recharge_money'];
        $base = \Logic\Shop\SystemConfig::getGlobalSystemConfig('recharge')['recharge_money'];

        $result['min_money'] = $base['recharge_min'] ?? 0;
        $result['max_money'] = $base['recharge_max'] ?? 0;

        if ($rand) {
            $min = $rand['min_money'] ?? 0;
            $max = $rand['max_money'] ?? 0;
            $result['min_money'] = $min > $result['min_money'] ? $min : $result['min_money'];

            if ($rand['max_temp'] > 0) {
                $result['max_money'] = $max > $result['max_money'] || $max == 0 ? $result['max_money'] : $max;
            }
        }

        return $result;
    }

    //获取线下支付渠道配置
    public function getPayChannel() {
        $type = $this->getType() ?? [];
        $re = [];
        foreach ($type as &$val) {
            //  获取每个渠道金额限制
            $round_money = $this->aroundMoney($val['id']);

            if (is_array($round_money)) {
                $val['min_money'] = $round_money['min_money'];
                $val['max_money'] = $round_money['max_money'];

                if ($val['min_money'] <= $val['max_money']) {
                    $re[] = $val;
                }
            }
        }

        $round = $this->aroundMoney(null);
        $round['min_money'] = isset($round['min_money']) ? $round['min_money'] : 0;
        $round['max_money'] = isset($round['max_money']) ? $round['max_money'] : 0;

        return ['money' => $round, 'type' => $re];
    }

    public function getDepositByOrderId($orderNo) {
        return \DB::table('funds_deposit')->where('trade_no', '=', $orderNo)->first();
    }

    public function getFundsById($id) {
        return FundsVender::find($id);
    }

    /**
     * 支付交易信息
     *
     * @param string $platform
     * @param string $pay_scene
     * @param string $trade_no
     * @param int $user_id
     * @param string $trans_id
     * @param int $money
     * @param string $pay_time
     */
    public function noticeInfo($platform, $pay_scene, $trade_no, $user_id, $trans_id, $money, $pay_time) {
        global $app;

        $data['platform'] = $platform;
        $data['pay_scene'] = $pay_scene;
        $data['user_id'] = $user_id;
        $data['trade_no'] = $trade_no;
        $data['trans_id'] = $trans_id;
        $data['money'] = $money;
        $data['pay_time'] = $pay_time ?? date('Y-m-d H:i:s');
        $data['created'] = date('Y-m-d H:i:s');

        $app->getContainer()->db->getConnection()
                                ->table('funds_pay_callback')
                                ->insert($data);
    }
}