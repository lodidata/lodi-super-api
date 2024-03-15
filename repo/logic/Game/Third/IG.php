<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class IG
 */
class IG extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_ig';
    protected $playTable = 'game_order_ig_fair'; //对局详情表
    protected $game_type = 'IG';
    /**
     * @var string
     */
    private $trace_id;

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
     * 拉单延迟30分钟，最大拉单区间30天
     * @return bool
     * @throws \Exception
     */
    public function synchronousData()
    {
        if (!$this->checkStatus()) {
            return false;
        }
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type); //上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = strtotime(date('Y-m-d', strtotime('-1 day'))); //取1天前的数据
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
        // 子游戏对应分类id
        $gameList = $this->redis->get('super_game_ig_3th');
        if(is_null($gameList) || $gameList =="null" || empty($gameList)){
            $game3th = \DB::table('game_3th')->whereIn('game_id', [102, 155, 156, 157,158])->get(['kind_id', 'game_id'])->toArray();
            foreach($game3th as $val){
                $val = (array) $val;
                $gameList[$val['kind_id']] = $val['game_id'];
            }
            $this->redis->setex('super_game_ig_3th', 86400, json_encode($gameList));
        }else{
            $gameList = json_decode($gameList, true);
        }

        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {

            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('OCode', $val['parent_bet_id'])->count()) {
                    continue;
                }
            }
            if (strpos($val['player_name'], 'game') === false) {
                continue;
            }
            $val['tid'] = intval(ltrim(strtolower($val['player_name']), 'game'));
            if ($val['tid'] == 0) {
                continue;
            }
            date_default_timezone_set("Etc/GMT-8");
            $create_time = strtotime($val['create_time']);
            date_default_timezone_set($default_timezone);
            $insertData[] = [
                'tid' => $val['tid'],
                'Username' => $val['player_name'],
                'OCode' => $val['parent_bet_id'],
                'gameCode' => $val['game_id'],
                'betAmount' => $val['bet_amount'],
                'winAmount' => bcadd($val['transfer_amount'], $val['bet_amount'], 0),
                'income' => $val['transfer_amount'],
                'bill_type' => $val['bill_type'],
                'gameDate' => date('Y-m-d H:i:s', $create_time),
                'balance_before' => $val['balance_before'],
                'balance_after' => $val['balance_after'],
                'game_menu_id' => isset($gameList[$val['game_id']]) ? $gameList[$val['game_id']] : 102,
            ];
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
        $day = date('Y-m-d', strtotime('-1 day'));
        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type); //上次的结束时间
        if ($r_time) {
            $startDay = $r_time;
        } else {
            $startDay = $day; //取1天前的数据
        }

        //校验3次不通过则跳过
        $check_count = $this->redis->incr(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type);
        if ($check_count > 3) {
            $startDay = date("Y-m-d", strtotime('+1 day', strtotime($startDay)));
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startDay);
            $this->redis->set(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type, 1);
        }


        $endDay = date("Y-m-d", strtotime('+1 day', strtotime($startDay)));
        //正常拉单时间
        $lastTime = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);

        //取1天前的数据 当前过02时,正常拉单时间小于汇总时间
        if (($startDay == $day && date('H') < '02') || (!is_null($lastTime) && $lastTime < strtotime($endDay . ' 02:00:00'))) {
            return true;
        }

        $params = [
            'start_date' => $startDay,
            'end_date' => $endDay,
        ];
        $res = $this->requestParam('/api/cf/GetDataReport', $params);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }

        //无数据
        if (!isset($res['Data']) || empty($res['Data'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        $betAmount = 0;
        $winAmount = 0;
        $numCount = 0;

        foreach ($res['Data'] as $val) {
            //校验当天数据
            if ($params['start_date'] == $params['end_date'] && $val['count_date'] == $params['start_date']) {
                $betAmount = $val['bet_amount'];
                $winAmount = $val['transfer_amount'];
                $numCount = $val['bet_count'];
                break;
            }
        }

        $result = \DB::table($this->orderTable)
            ->where('gameDate', '>=', $startDay)
            ->where('gameDate', '<', $endDay)
            ->select(\DB::raw("sum(betAmount) as betAmount, sum(income) as winAmount"))->first();
        //金额正确
        if (bccomp($betAmount, $result->betAmount, 0) == 0 && bccomp($winAmount, $result->winAmount, 0) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        //金额不对,重新拉单
        $this->orderByTime($startDay, $endDay);

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('gameDate', '>=', $startDay)
            ->where('gameDate', '<', $endDay)
            ->select(\DB::raw("sum(betAmount) as betAmount, sum(income) as winAmount"))->first();
        if (!(bccomp($betAmount, $result2->betAmount, 0) == 0 && bccomp($winAmount, $result2->winAmount, 0) == 0)) {
            $this->addGameOrderCheckError($this->game_type, time(), $params, $startDay, $endDay, $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
            return true;
        }

        //金额匹配完全正确
        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
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
            'username' => 'Username',
            'bet' => 'betAmount',
            'win' => 'winAmount',
            'profit' => 'income',
            'gameDate' => 'gameDate'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->game_type, $data);
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
     * @param $stime
     * @param $etime
     * @param bool $is_redis
     * @return bool
     */
    public function orderByTime($stime, $etime, $is_redis = false)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //每次最大拉取区间24小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $fields = [
            'start_time' => date('Y-m-d H:i:s', $startTime),
            'end_time' => date('Y-m-d H:i:s', $endTime),
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/api/cf/GetHistory', $fields);
        //接口报错
        if (!$res['responseStatus']) {
            return false;
        }
        //无数据
        if (!empty($res['data'])) {
            $this->updateOrder($res['data']);
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
        return $this->orderByTime($stime, $etime);
    }

    /**
     * 拉取对局详情
     * @param string $stime
     * @param string $etime
     * @param bool $is_redis
     * @return bool
     */
    public function orderByTimePlayDetail($stime, $etime, $is_redis = false)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //每次最大拉取区间24小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $fields = [
            'start_time' => date('Y-m-d H:i:s', $startTime),
            'end_time' => date('Y-m-d H:i:s', $endTime),
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/api/cf/GetGameDetail', $fields);
        //接口报错
        if (!$res['responseStatus']) {
            return false;
        }
        //无数据
        if (!empty($res['data'])) {
            $this->updatePlayDetail($res['data']);
        }


        if ($is_redis) {
            $this->redis->set(CacheKey::$perfix['gameGetPlayDetailTime'] . $this->game_type, $endTime);
        }
        return true;
    }

    /**
     * 拉取对局详情
     */
    public function synchronousPlayDetail()
    {
        if (!$this->checkStatus()) {
            return false;
        }
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetPlayDetailTime'] . $this->game_type); //上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = strtotime(date('Y-m-d', strtotime('-1 day'))); //取1天前的数据
        }
        $endTime = $now;
        return $this->orderByTimePlayDetail(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), true);
    }

    /**
     * 更新对局详情表
     */
    public function updatePlayDetail($data, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {

            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->playTable)->where('OCode', $val['parent_bet_id'])->count()) {
                    continue;
                }
            }
            if (strpos($val['player_name'], 'game') === false) {
                continue;
            }
            $val['tid'] = intval(ltrim(strtolower($val['player_name']), 'game'));
            if ($val['tid'] == 0) {
                continue;
            }
            date_default_timezone_set("Etc/GMT-8");
            $create_time = strtotime($val['create_time']);
            date_default_timezone_set($default_timezone);
            $insertData[] = [
                'tid' => $val['tid'],
                'Username' => $val['player_name'],
                'OCode' => $val['parent_bet_id'],
                'gameCode' => $val['game_id'],
                'detail' => $val['detail'],
                'gameDate' => date('Y-m-d H:i:s', $create_time),
            ];
        }

        return $this->addGameOrders($this->game_type, $this->playTable, $insertData);

    }

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @param bool $is_post 是否POST请求
     * @return array|string
     */
    public function requestParam(string $action, array $param, $is_post = true)
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
        $option = [
            'operator_token' => $config['key'],
            'secret_key' => $config['pub_key']
        ];
        $params = array_merge($option, $param);

        $url = rtrim($config['orderUrl'], '/') . $action;
        $trace_id = $this->guid();
        $url .= '?trace_id=' . $trace_id;

        if ($is_post) {
            $re = Curl::post($url, null, $params, null, true);
        } else {
            $queryString = http_build_query($params, '', '&');
            if ($queryString) {
                $url .= '&' . $queryString;
            }
            $re = Curl::get($url, null, true);
        }

        if ($re['status'] == 200) {
            $re['content'] = json_decode($re['content'], true);
        }
        GameApi::addRequestLog($url, $this->game_type, $params, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ret = [];
        if ($re['status'] == 200) {
            $ret = $re['content'];
            if (is_null($ret['error'])) {
                $ret['responseStatus'] = true;
            } else {
                $ret['responseStatus'] = false;
            }
        } else {
            $ret['responseStatus'] = false;
            $ret['error']['message'] = $re['content'];
        }
        return $ret;
    }

    /**
     * 请求的唯一标识符（GUID）
     * @return string
     */
    public function guid()
    {
        if (!$this->trace_id) {
            $charid = strtoupper(md5(str_random()));
            $hyphen = chr(45); // "-"
            $this->trace_id = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);
        }
        return $this->trace_id;
    }
}