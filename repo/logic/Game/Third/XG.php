<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class XG视讯
 */
class XG extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_xg';
    protected $game_type = 'XG';

    /**
     * 检查接口状态
     * @return bool
     */
    public function checkStatus()
    {
        return true;
    }

    /**
     * 同步第三方游戏订单
     * 拉单延迟60分钟，最大拉单区间60天
     * @return bool
     * @throws \Exception
     */
    public function synchronousData()
    {
        if (!$this->checkStatus()) {
            return false;
        }
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = $now - 3599; //取60分钟内的数据
        }
        $endTime = $now;
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), true);
    }

    /**
     * 更新订单表
     * @param array $data
     * @param int $updateStatus
     * @return bool
     */

    public function updateOrder($data, $updateStatus = 0)
    {
        $insertData = [];
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('wagersId', $val['WagersId'])->count()) {
                    continue;
                }
            }
            $default_timezone = date_default_timezone_get();
            date_default_timezone_set("Etc/GMT+4");
            $WagersTime = strtotime($val['WagersTime']);
            $PayoffTime = strtotime($val['PayoffTime']);
            $SettlementTime = strtotime($val['SettlementTime']);
            date_default_timezone_set($default_timezone);

            $val['Account'] = strtolower($val['Account']);
            $val['tid'] = intval(ltrim($val['Account'], 'game'));
            $val['WagersTime'] = date('Y-m-d H:i:s', $WagersTime);
            $val['PayoffTime'] = date('Y-m-d H:i:s', $PayoffTime);
            $val['SettlementTime'] = date('Y-m-d H:i:s', $SettlementTime);
            $val['prize_amount'] = bcadd($val['PayoffAmount'], $val['BetAmount'], 2);

            $insertData[] = $val;
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 订单校验
     * @return bool
     * @throws \Exception
     */
    public function synchronousCheckData()
    {
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

        $endTime = strtotime(date('Y-m-d H:00:00', $now - 7200)); //取2小时前的数据，延迟30分钟
        //每次最大拉取区间1 小时内
        if ($endTime - $startTime > 3599) {
            $endTime = $startTime + 3599;
        }
        //当前小时不拉数据
        if (date('Y-m-d H', $now) == date('Y-m-d H', $endTime) || (date('Y-m-d H', $endTime) == date('Y-m-d H', $startTime)) || $endTime > $now) {
            return true;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $params = [
            'StartTime' => date('Y-m-d\TH:i:s', $startTime),
            'EndTime' => date('Y-m-d\TH:i:s', $endTime),
        ];
        date_default_timezone_set($default_timezone);

        $res = $this->requestParam('GetApiReportGroupGameTypeUrl', $params);
        if (!$res['responseStatus']) {
            return false;
        }
        if (!isset($res['Data']) || empty($res['Data'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }
        //总订单数
        $betCount = 0;
        //下注金额
        $betAmount = 0;
        //输赢金额
        $winAmount = 0;
        foreach ($res['Data'] as $val) {
            $betAmount += $val['TotalBetAmount'];
            $betCount += $val['WagersCount'];
            $winAmount += $val['TotalPayoff'];
        }

        if ($betAmount == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        $result = \DB::table($this->orderTable)
            ->where('WagersTime', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('WagersTime', '<', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(BetAmount) as betAmount, sum(PayoffAmount) as winAmount"))->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
                return true;
            }
        }
        //订单数不对补单
        $this->orderByTime(date("Y-m-d H:i:s", $startTime), date("Y-m-d H:i:s", $endTime));

        //第二次校验
        $result = \DB::table($this->orderTable)
            ->where('WagersTime', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('WagersTime', '<', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(BetAmount) as betAmount, sum(PayoffAmount) as winAmount"))->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
                return true;
            }
        }
        //订单数不对
        $this->addGameOrderCheckError($this->game_type, $now, $params, date("Y-m-d H:i:s", $startTime), date("Y-m-d H:i:s", $endTime), $betAmount, $winAmount, $result->betAmount, $result->winAmount);

        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        return [];
    }

    /**
     * 游戏统计
     * @param null $date 日期
     * @return bool
     */
    public function queryOperatesOrder($date = null)
    {
        $data = [
            'username' => 'Account',
            'bet' => 'BetAmount',
            'win' => 'prize_amount',
            'profit' => 'PayoffAmount',
            'gameDate' => 'WagersTime'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->game_type, $data, false);
    }

    public function queryHotOrder($user_prefix, $startTime, $endTime, $args = [])
    {
        return [];
    }

    public function queryLocalOrder($user_prefix, $start_time, $end_time, $page = 1, $page_size = 500)
    {
        return [];
    }

    /**
     * 按分钟检索事务
     * @param      $stime
     * @param      $etime
     * @param bool $is_redis
     * @return bool
     */
    public function orderByTime($stime, $etime, $is_redis = false)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //每次最大拉取区间1小时内
        if ($endTime - $startTime > 3599) {
            $endTime = $startTime + 3599;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $fields = [
            'StartTime' => date('Y-m-d\TH:i:s', $startTime),
            'EndTime' => date('Y-m-d\TH:i:s', $endTime),
            'Page' => '1',
            'PageLimit' => '10000',
        ];
        date_default_timezone_set($default_timezone);
        $page = 1;
        while (1) {
            $fields['Page'] = $page;
            $res = $this->requestParam('GetBetRecordByTime', $fields);
            if (!$res['responseStatus']) {
                return false;
            }
            if (empty($res['Data']['Result'])) {
                break;
            }
            $this->updateOrder($res['Data']['Result']);
            if ($res['Data']['Pagination']['TotalPages'] >= $page) {
                break;
            }
            $page++;
        }
        if ($is_redis) {
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
    public function orderByHour($stime, $etime)
    {
        return $this->orderByTime($stime, $etime, true);
    }

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $params
     * @return array|string
     */
    public function requestParam(string $action, array $params)
    {
        $config = $this->initConfigMsg($this->game_type);
        if (!$config) {
            $ret = [
                'responseStatus' => false,
                'message' => 'api not config'
            ];
            GameApi::addElkLog($ret, $this->game_type);
            return $ret;
        }
        $url = $config['orderUrl'] . $action;
        $params['AgentId'] = $config['cagent'];
        $params['Key'] = $this->getKey($params, $config);

        $re = Curl::post($url, null, $params, null, true);

        if ($re['status'] != 200) {
            $ret['responseStatus'] = false;
            $ret['networkStatus'] = $re['status'];
            $ret['msg'] = $re['content'];
            GameApi::addRequestLog($url, 'XG', $params, json_encode($re, JSON_UNESCAPED_UNICODE));
        } else {
            $ret = json_decode($re['content'], true);
            $ret['networkStatus'] = $re['status'];
            if (isset($ret['ErrorCode']) && $ret['ErrorCode'] === 0) {
                $ret['responseStatus'] = true;
            } else {
                $ret['responseStatus'] = false;
            }
            GameApi::addRequestLog($url, 'XG', $params, json_encode($ret, JSON_UNESCAPED_UNICODE));
        }
        return $ret;
    }

    public function getKey($data, $config)
    {
        return $this->getRandomString(6) . md5($this->paramString($data) . $this->getKeyG($config)) . $this->getRandomString(6);
    }

    public function paramString($data)
    {
        if (empty($data)) {
            return null;
        }
        $str = '';
        foreach ($data as $k => $v) {
            if (empty($v)) {
                continue;
            }
            $str .= $k . '=' . $v . '&';
        }
        $str = trim($str, '&');

        return $str;
    }

    public function getKeyG($config)
    {
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $day = date('ymj');
        date_default_timezone_set($default_timezone);

        $keyG = md5($day . $config['cagent'] . $config['key']);
        return $keyG;
    }

    public function getRandomString($length)
    {
        {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $randomString = '';

            for ($i = 0; $i < $length; $i++) {
                $index = rand(0, strlen($characters) - 1);
                $randomString .= $characters[$index];
            }

            return $randomString;
        }
    }
}
