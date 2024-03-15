<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameLogic;
use Logic\Game\GameApi;
use Utils\Curl;

/**
 * Explain: EZUGI 游戏接口
 *
 * OK
 */
class EZUGI extends GameLogic
{
    protected $game_type = 'EZUGI';
    protected $orderTable = 'game_order_ezugi';

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
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = $now - 1800; //取30分钟内的数据
        }
        $endTime = $now;
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), true);
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
        //拉单去重，开始时间+1秒
        $startTime = $startTime + 1;
        //每次最大拉取区间24 小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT");

        $config = $this->initConfigMsg($this->game_type);
        $lobby = json_decode($config['lobby'], true);

        $fields = [
            'DataSet' => 'per_round_report',
            'APIID' => $lobby['appid'],
            'APIUser' => $lobby['api_user'],
            'StartTime' => date("Y-m-d H:i:s", $startTime),
            'EndTime' => date("Y-m-d H:i:s", $endTime),
            'Page' => 1,
            'Limit' => 10000,
        ];
        date_default_timezone_set($default_timezone);
        while (1) {
            $res = $this->requestParam('/get/', $fields);
            if (!$res['responseStatus']) {
                break;
            }

            $this->updateOrder($res['data']);
            if ($is_redis) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            }
            if ($fields['Page'] * $fields['Limit'] >= $res['totalSize']) {
                break;
            }
            //下一页
            $fields['Page']++;
        }
        return true;
    }

    /**
     * 订单校验
     * 校验前一天的订单金额，正常拉单延期2小时，所以校验在2小时后
     * @return bool
     */
    public function synchronousCheckData()
    {
        $now = time();
        //当前小时是否跑过数据

        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = strtotime(date('Y-m-d H:00:00', $now - 86400)); //取1天前的数据
        }
        $endTime = strtotime(date('Y-m-d H:00:00', $now - 10800)); //取3小时前的数据
        //每次最大拉取区间1 小时内
        if ($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }
        //当前小时不拉数据,延期2小时
        if (date('Y-m-d H', $endTime) <= date('Y-m-d H', $startTime) || $endTime > $now || (date('Y-m-d H:20', $endTime) > date('Y-m-d H:i', $startTime))) {
            return true;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $params = [
            'StartTime' => date("Y-m-d\TH:i:s", $startTime),
            'EndTime' => date("Y-m-d\TH:i:s", $endTime),
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('GetBetRecordSummary', $params);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        //请求失败
        if ($res['ErrorCode']) {
            return false;
        }
        //无数据
        if (!isset($res['Data']) || empty($res['Data'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        $betAmount = bcmul($res['data']['BetAmount'], 100, 0);
        $winAmount = bcmul($res['data']['PayoffAmount'], 100, 0);
        $numCount = $res['data']['WagersCount'];
        $result = \DB::table($this->orderTable)
            ->where('gameDate', '>=', date('Y-m-d H:i:s', $startTime))
            ->where('gameDate', '<=', date('Y-m-d H:i:s', $endTime))
            ->select(\DB::raw("sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        //金额正确
        if (bccomp($betAmount, $result->betAmount, 0) == 0 && bccomp($winAmount, $result->winAmount, 0) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        //金额不对,重新拉单
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime));

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('gameDate', '>=', date('Y-m-d H:i:s', $startTime))
            ->where('gameDate', '<=', date('Y-m-d H:i:s', $endTime))
            ->select(\DB::raw("sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        if (!(bccomp($betAmount, $result2->betAmount, 0) == 0 && bccomp($winAmount, $result2->winAmount, 0) == 0)) {
            $this->addGameOrderCheckError($this->game_type, time(), $params, date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
            return true;
        }

        //金额匹配完全正确
        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
                        ->where('date_created', '>=', $start_time)
                        ->where('date_created', '<=', $end_time)
                        ->selectRaw("sum(bet) as bet,sum(bet) as valid_bet,sum(win) as win_loss")
                        ->first();
        return (array)$result;
    }

    /**
     * 游戏统计
     * @param null $date 日期
     * @return bool
     */
    public function queryOperatesOrder($date = null)
    {
        $data = [
            'username' => 'nickname',
            'bet' => 'bet',
            'win' => 'win',
            'profit' => 'win-bet',
            'gameDate' => 'round_date_time'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->game_type, $data, true);
    }

    public function queryHotOrder($user_prefix, $startTime, $endTime, $args = [])
    {
        return [];
    }

    public function queryLocalOrder($user_prefix, $start_time, $end_time, $page = 1, $page_size = 500)
    {
        $query = \DB::table($this->orderTable)
            ->where('gameDate', '>=', $start_time)
            ->where('gameDate', '<=', $end_time)
            ->where('Username', 'like', "%$user_prefix%")
            ->selectRaw("id,gameDate,OCode as order_number,betAmount as bet,betAmount as valid_bet,winAmount as win_loss");
        $total = $query->count();

        $result = $query->orderBy('id')->forPage($page, $page_size)->get()->toArray();
        $attributes['total'] = $total;
        $attributes['number'] = $page;
        $attributes['size'] = $page_size;
        if (!$attributes['total'])
            return [];

        return $this->lang->set(0, [], $result, $attributes);
    }

    /**
     * 按小时拉取
     * @param $stime
     * @param $etime
     */
    public function orderByHour($stime, $etime)
    {
        return [];
    }

    /**
     * 更新订单
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
                if (\DB::table($this->orderTable)->where('order_number', (string)$val['ID'])->count()) {
                    continue;
                }
            }
            $insertData[] = [
                'order_number' => (string)$val['ID'],
                'tid' => intval(ltrim($val['NickName'], 'game')),
                'bet_type_id' => intval($val['BetTypeID']),
                'round_id' => intval($val['RoundID']),
                'studio_id' => intval($val['StudioID']),
                'table_id' => intval($val['TableID']),
                'uid' => intval($val['UID']),
                'operator_id' => intval($val['OperatorID']),
                'session_currency' => $val['SessionCurrency'],
                'skin_id' => $val['SkinID'],
                'bet_sequence_id' => $val['BetSequenceID'],
                'bet' => $val['Bet'],
                'win' => $val['Win'],
                'game_string' => json_encode($val['GameString'], JSON_UNESCAPED_UNICODE) ?? "",
                'bank_roll' => $val['Bankroll'],
                'seat_id' => $val['SeatID'],
                'brand_id' => intval($val['BrandID']),
                'round_date_time' => $val['RoundDateTime'],
                'action_id' => $val['ActionID'],
                'bet_type' => $val['BetType'],
                'platform_id' => intval($val['PlatformID']),
                'date_inserted' => $val['DateInserted'],
                'game_type_id' => intval($val['GameTypeID']),
                'game_type_name' => $val['GameTypeName'],
                'return_reason' => $val['ReturnReason'],
                'nickname' => $val['NickName']
            ];
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @param bool $is_post 是否为post请求
     * @return array|string
     */
    public function requestParam(string $action, array $param, bool $is_post = true, $status = true)
    {
        $config = $this->initConfigMsg($this->game_type);
        $lobby = json_decode($config['lobby'], true);
        $querystring = '';
        foreach($param as $key=>$value) {
            $querystring .= $key.'=' . $value.'&';
        }

        $param['RequestToken'] = hash("sha256", $lobby['api_access'].rtrim($querystring, "&"));

        $url = $config['orderUrl'] . '/api' . $action;

        if ($is_post) {
            $re = Curl::post($url, null, $param, null, $status);
        } else {
            $re = Curl::get($url, null, $status);
        }

        if (isset($re['status']) && $re['status'] == 200) {
            $re['data'] = json_decode($re['content'], true);
        }
        GameApi::addRequestLog($url, 'EZUGI', $param, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ret = [];
        if ($re['status'] == 200) {
            $ret = $re['data'];
            $ret['responseStatus'] = true;
        } else {
            $ret['ErrorCode'] = $re['data']['error_code'];
            $ret['responseStatus'] = false;
            $ret['Message'] = $re['data']['details']['message'] ?? 'API error';
        }
        return $ret;
    }

}