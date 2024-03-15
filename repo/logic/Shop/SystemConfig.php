<?php
namespace Logic\Shop;

class SystemConfig extends \Logic\Logic{
    public static function getGlobalSystemConfig($key=null){
        $config = [
            'market'=>[
                'down_url'=>'',
                'app_name'=>'棋牌辅助软件',
                'app_desc'=>'棋牌辅助软件',
                'h5_url'=>'',
                'pc_url'=>'',
                'spread_url'=>'',
            ],
            'market2'=>[
                'certificate_url' => 'https://XXXyyyy',
                'certificate_switch' => false,
                'app_spead_url' => 'http://123445',
            ],
            'recharge'=>[
                'recharge_money' => ['recharge_min'=>0, 'recharge_max'=>2000000],
                'recharge_money_set' => true,
                'recharge_money_value' => [10000, 20000, 50000, 100000],
                'stop_deposit' => false,//暂停充值
                'deposit_float_point' => false,//充值小数点
                'canNotDepositOnPending' => true,//线下重复提交订单
            ],
        ];
        if($key) return $config[$key];
        else return $config;
    }

    //启动后配置参数
    public function getStartGlobal() {
        $_SERVER['REQUEST_SCHEME'] = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : '';
        $_SERVER['HTTP_HOST'] = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

//        $pusherio = $this->ci->get('settings')['pusherio'];
//        $config = SystemConfig::getGlobalSystemConfig();
        $config = self::getGlobalSystemConfig();
        $recharge = $config['recharge']['recharge_money_value'] ?? [10000, 20000, 50000, 100000];
        $coin = [
            'min_money' => $config['recharge']['recharge_money']['recharge_min'] ?? 0,// 充值金额，以分为单位
            'max_money' => $config['recharge']['recharge_money']['recharge_max'] ?? 0,//充值最高金额，以分为单位
            'recharge_money_set' => $config['recharge']['recharge_money_set'] ?? true,
            'recharge_money_value' => $recharge,
            'recharge_money_value_list' => array_values($recharge),
//            'stop_withdraw' => $config['lottery']['stop_withdraw'] ?? false, // 暂停提现开关
            'stop_deposit' => $config['recharge']['stop_deposit'] ?? false, // 暂停充值
        ];
        $spread = [
            'down_url' => $config['market']['down_url'],//APP下载地址
            'app_name' => $config['market']['app_name'],//APP名称
            'app_desc' => $config['market']['app_desc'],//APP描述
            'h5_url' => $config['market']['h5_url'],//H5地址
            'pc_url' => $config['market']['pc_url'],//pc地址
            'spread_url' => $config['market']['spread_url'],//推广地址
            'certificate_url' => $config['market2']['certificate_url'],
            'certificate_switch' => $config['market2']['certificate_switch'],
            'app_spead_url' => $config['market2']['app_spead_url'],
            'service_url' => 'https://m.baowangys.com/?nodeId=701&hideHeader=1',//客服地址
        ];
//        $lottery = [
//            'stop_bet' => $config['lottery']['stop_bet'] ?? false, // 暂停下单开关
//            'stop_chasing' => $config['lottery']['stop_chasing'] ?? false, // 暂停追号下单开关
//            'unusual_period_auto' => $config['lottery']['unusual_period_auto'] ?? true,  //彩期异常销售自动开关
//        ];

        $server = $_SERVER;
        //判断是否有传Authorization，如果没传则为游客
        if (!isset($server['HTTP_AUTHORIZATION'])) {
            //若是游戏同UUID为当前同一个人
            $device = $this->request->getHeaders()['HTTP_UUID'] ?? '';
            $device = is_array($device) ? array_shift($device) : '';
            if($device && ($user_id = $this->redis->get('customer_service_tourist_' . $device))){
                $original_user_id = $user_id; //原始user_id
            }else{
                //判断是否存在游客缓冲键，如果没有则初始化为200亿
                $key = 'visitor_user_id';
                $visitor_user_id = $this->redis->get($key);
                if (empty($visitor_user_id)) {
                    $this->redis->set($key, 20000000000);
                    $user_id = 20000000000; //游客原user_id 和加百亿后的 user_id值相等。
                } else {
                    $user_id = $this->redis->incr($key);
                }
                $original_user_id = $user_id; //原始user_id
                $this->redis->setex('customer_service_tourist_' . $device, 86400, $user_id);
            }
        } else {
            $this->auth->verfiyToken();
            $user_id = $this->auth->getUserId(); //user_id
            $original_user_id = $user_id; //原始user_id
        }

        $app_id = $this->ci->get('settings')['shop']['pusherio']['app_id'];
        $app_secret = $this->ci->get('settings')['shop']['pusherio']['app_secret'];
        $hashids = new \Hashids\Hashids($app_id . $app_secret, 8, 'abcdefghijklmnopqrstuvwxyz');
        $hashids_user_id = $hashids->encode($user_id);
        $spread['user_id'] = base_convert($hashids_user_id, 36, 10);
        $spread['user_id'] = (int)$spread['user_id'];

        if ($original_user_id == 0 || $user_id == 0) {
            return $this->lang->set(2222);
        }

        return array_merge($coin, $spread);
    }
}
