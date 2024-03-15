<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class WM
 */
class WM extends GameLogic {
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_wm';
    protected $game_type = 'WM';

    /**
     * 检查接口状态
     * @return bool
     */
    public function checkStatus() {
        return true;
    }

    /**
     * 同步第三方游戏订单
     * 拉单延迟30分钟，最大拉单区间1天
     * @return bool
     * @throws \Exception
     */
    public function synchronousData() {
        if(!$this->checkStatus()) {
            return false;
        }
        $now    = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = $now - 24*60*60; //取60分钟内的数据
        }
        $endTime = $now - 1800;
        if($startTime > $endTime){
            return false;
        }
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), true);
    }

    /**
     * 更新订单表
     * @param array $data
     * @param int   $updateStatus
     * @return bool
     */

    public function updateOrder($data, $updateStatus = 0) {
        $default_timezone = date_default_timezone_get();
        $insertData       = [];
        foreach($data as $val) {
            //校验更新，存在不处理
            if($updateStatus) {
                if(\DB::table($this->orderTable)->where('order_number', $val['betId'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT-8");
            $CreateAt = strtotime($val['betTime']);//建立時間
            $ResultAt = strtotime($val['settime']);//结果时间
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid' => intval(ltrim(strtolower($val['user']), 'game')),
                'order_number' => $val['betId'],
                'user' => $val['user'],
                'bet' => $val['bet'],
                'validbet' => $val['validbet'],
                'winLoss' => $val['winLoss'],
                'betTime' => date('Y-m-d H:i:s', $CreateAt),
                'settime' => date('Y-m-d H:i:s', $ResultAt),
                'GameCategoryId' => !empty($val['slotGameId']) ? 2 : 1,
                'prize_amount' => bcadd($val['winLoss'], $val['bet'], 2),
                'tableId' => $val['tableId'] ?? 0,
                'gid' => $val['gid'],
                'gname' => $val['gname'],
                'gameResult' => $val['gameResult'],
                'result' => $val['result']?? ''
            ];
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 订单校验
     * @return bool
     * @throws \Exception
     */
    public function synchronousCheckData() {

        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = strtotime(date('Y-m-d H:00:00', $now - 86400)); //取1天前的数据
        }

        //校验3次不通过则跳过
        $check_count = $this->redis->incr(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type);
        if($check_count > 3){
            $startTime = $startTime + 3600;
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startTime);
            $this->redis->set(CacheKey::$perfix['gameOrderCheckCount'] .  $this->game_type, 1);
        }
        $endTime = strtotime(date('Y-m-d H:00:00', $now - 3600)); //取1小时前的数据，延迟30分钟

        //每次最大拉取区间1 小时内
        if ($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }
        //当前小时不拉数据
        if (date('Y-m-d H', $now) == date('Y-m-d H', $endTime) || $endTime >= $now || $startTime >= $endTime) {
            return true;
        }

        $status = $this->orderByTime(date("Y-m-d H:i:s", $startTime), date("Y-m-d H:i:s", $endTime));
        if(!$status){
            return false;
        }

        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);

        return true;

    }

    public function querySumOrder($start_time, $end_time) {
        return [];
    }

    /**
     * 游戏统计
     * @param bool $yestaday
     * @return bool
     */
    public function queryOperatesOrder($date = null)
    {
        $data = [
            'username' => 'user',
            'bet' => 'bet',
            'win' => 'prize_amount',
            'profit' => 'winLoss',
            'gameDate' => 'betTime'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->game_type, $data, false);
    }

    public function queryHotOrder($user_prefix, $startTime, $endTime, $args = []) {
        return [];
    }

    public function queryLocalOrder($user_prefix, $start_time, $end_time, $page = 1, $page_size = 500) {
        return [];
    }

    /**
     * 按分钟检索事务
     * @param      $stime
     * @param      $etime
     * @param bool $is_redis
     * @return bool
     */
    public function orderByTime($stime, $etime, $is_redis = false) {
        $startTime = strtotime($stime);
        $endTime   = strtotime($etime);
        //每次最大拉取区间24小时内
        if($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $fields = [
            'cmd'        => 'GetDateTimeReport',
            'startTime' => date('YmdHis', $startTime),
            'endTime'   => date('YmdHis', $endTime),
            'syslang'    => 1,//英文
            'timestamp'  => time(),
            'timetype'   => 1,
            'datatype'   => 2
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam($fields);
        //接口报错
        if(!$res['responseStatus']) {
            return false;
        }
        if(!empty($res['result'])) {
            $this->updateOrder($res['result']);
        }
        if($is_redis) {
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
        }
        return true;
    }

    /**
     * 按小时拉取
     * @param $stime
     * @param $etime
     * @return bool
     */
    public function orderByHour($stime, $etime) {
        return $this->orderByTime($stime, $etime);
    }

    /**
     * 发送请求
     * @param array $param 请求参数
     * @return array|string
     */
    public function requestParam(array $param) {
        $config = $this->initConfigMsg($this->game_type);
        if(!$config) {
            $ret = [
                'responseStatus' => false,
                'message'        => 'api not config'
            ];
            GameApi::addElkLog($ret, $this->game_type);
            return $ret;
        }
        $param['vendorId']  = $config['cagent'];
        $param['signature'] = $config['key'];

        $headers = [
            "Content-Type: application/x-www-form-urlencoded"
        ];

        $queryString = http_build_query($param);

        $url = $config['orderUrl'];

        $re = Curl::commonPost($url, null, $queryString, $headers, true);

        if($re['status'] != 200) {
            $ret['responseStatus'] = false;
            $ret['networkStatus']  = $re['status'];
            $ret['msg']            = $re['content'];
            GameApi::addRequestLog($url, 'WM', $param, json_encode($re, JSON_UNESCAPED_UNICODE));
        } else {
            $ret                  = json_decode($re['content'], true);
            $ret['networkStatus'] = $re['status'];
            if(isset($ret['errorCode']) && ($ret['errorCode'] === 0 || $ret['errorCode'] === 107)) {
                $ret['responseStatus'] = true;
            } else {
                $ret['responseStatus'] = false;
            }
            GameApi::addRequestLog($url, 'WM', $param, json_encode($ret, JSON_UNESCAPED_UNICODE));
        }
        return $ret;
    }

}
