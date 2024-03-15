<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * KMQM棋牌
 */
class KMQM extends GameLogic
{

    protected $game_type = 'KMQM';
    protected $orderTable = 'game_order_kmqm';


    /**
     * 第三方注单同步
     * 拉单时间不能超过30分钟
     * @throws \Exception
     */
    public function synchronousData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('betupdatedon');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 2 * 60; //取2分钟前的数据
        }
        //$startTime = strtotime('2022-03-11 19:00:00');
        $endTime = $now;
        //接数据时间间隔不能超过30分钟
        if ($endTime - $startTime > 1800) {
            $endTime = $startTime + 1800;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $stime = date('c', $startTime);
        $etime = date('c', $endTime);
        $param = [
            'startdate' => $stime,
            'enddate' => $etime,
            'issettled' => true,
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/api/history/bets', $param, false, true);
        //接口报错
        if (!$res['responseStatus']) {
            return false;
        }
        //接口内容报错
        if (isset($res["err"]) && $res["err"] > 0) {
            return false;
        }
        unset($res['responseStatus']);
        if (empty($res)) { // 未有任何订单数据
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            return true;
        }
        $this->updateOrder($res);

        $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);

    }


    /**
     * 订单校验
     * 按小时拉数据，拉取2小时前到1小时之前的数据
     */
    public function synchronousCheckData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = $now - 86400; //1天前
        }

        //校验3次不通过则跳过
        $check_count = $this->redis->incr(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type);
        if($check_count > 3){
            $startTime = $startTime + 3600;
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startTime);
            $this->redis->set(CacheKey::$perfix['gameOrderCheckCount'] .  $this->game_type, 1);
        }

        $endTime = $startTime + 3600;
        //当前小时不拉数据
        if (date('H', $now) == date('H', $endTime) || (date('Y-m-d H', $endTime) == date('Y-m-d H', $startTime)) || $endTime > $now) {
            return true;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $stime = date('Y-m-d\TH:00:00P', $startTime);
        $etime = date('Y-m-d\TH:00:00P', $endTime);
        $params = [
            'startdate' => $stime,
            'enddate' => $etime,
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/api/history/bets/total', $params, false, true);
        //接口报错
        if (!$res['responseStatus']) {
            return false;
        }
        //接口内容报错
        if (isset($res["err"]) && $res["err"] > 0) {
            return false;
        }
        unset($res['responseStatus']);
        //无数据
        if (empty($res)) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }
        $betAmount = 0;
        $winAmount = 0;
        foreach ($res as $val) {
            $betAmount = bcadd($betAmount, $val['riskamt'], 2);
            $winAmount = bcadd($betAmount, $val['winamt'], 2);
        }
        $betAmount = abs($betAmount);
        $result = \DB::table($this->orderTable)
            ->where('betupdatedon', '>=', date('Y-m-d H:00:00', $startTime))
            ->where('betupdatedon', '<', date('Y-m-d H:00:00', $endTime))
            ->select(\DB::raw("sum(riskamt) as betAmount, sum(winloss) as winAmount"))->first();

        if (bccomp($betAmount, $result->betAmount, 0) == 0 && bccomp($winAmount, $result->winAmount, 0) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        //金额不对,重新拉单
        $this->orderByTime(date('Y-m-d H:00:00', $startTime), date('Y-m-d H:30:00', $startTime));
        sleep(5);
        $this->orderByTime(date('Y-m-d H:30:00', $startTime), date('Y-m-d H:00:00', $endTime));

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('betupdatedon', '>=', date('Y-m-d H:00:00', $startTime))
            ->where('betupdatedon', '<', date('Y-m-d H:00:00', $endTime))
            ->select(\DB::raw("sum(riskamt) as betAmount, sum(winloss) as winAmount"))->first();
        //订单金额不对补单
        if (!(bccomp($betAmount, $result2->betAmount, 0) == 0
            && bccomp($winAmount, $result2->winAmount, 0) == 0)
        ) {
            $this->addGameOrderCheckError($this->game_type, $now, $params, date("Y-m-d H:00:00", $startTime), date('Y-m-d H:00:00', $endTime), $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
            return false;
        }

        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
        return true;
    }

    public function updateOrder($data, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            if ($val['roundstatus'] != 'Closed') {
                continue;
            }
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('ugsbetid', $val['ugsbetid'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT-8");
            $beton = strtotime($val['beton']);
            $betupdatedon = strtotime($val['betupdatedon']);
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid' => intval(ltrim($val['userid'], 'game')),
                'userid' => $val['userid'],
                'ugsbetid' => $val['ugsbetid'],
                'riskamt' => abs($val['riskamt']),
                'validbet' => $val['validbet'],
                'winloss' => $val['winloss'],
                'winamt' => $val['winamt'],
                'betupdatedon' => date('Y-m-d H:m:i', $betupdatedon),
                'beton' => date('Y-m-d H:m:i', $beton),
                'roundid' => $val['roundid'] ?? 0,
                'gamename' => $val['gamename'] ?? '',
                'gameid' => $val['gameid'],
                'cur' => $val['cur'] ?? 'PHP',
            ];
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('betupdatedon', '>=', $start_time)
            ->where('betupdatedon', '<', $end_time)
            ->selectRaw("sum(riskamt) as bet,sum(riskamt) as valid_bet,sum(winloss) as win_loss")
            ->first();
        if ($result) {
            $result = (array)$result;
            $result['bet'] = bcmul($result['bet'], 100, 0);
            $result['valid_bet'] = bcmul($result['valid_bet'], 100, 0);
            $result['win_loss'] = bcmul($result['win_loss'], 100, 0);
        }
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
            'username' => 'userid',
            'bet' => 'riskamt',
            'win' => 'winamt',
            'profit' => 'winloss',
            'gameDate' => 'betupdatedon'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->game_type, $data, false);
    }


    public function queryHotOrder($user_prefix, $startTime, $endTime, $args = [])
    {
        return [];

    }

    public function queryLocalOrder($user_prefix, $start_time, $end_time, $page = 1, $page_size = 500)
    {
        $query = \DB::table($this->orderTable)
            ->where('betupdatedon', '>=', $start_time)
            ->where('betupdatedon', '<=', $end_time)
            ->where('userid', 'like', "%$user_prefix%")
            ->selectRaw("id,betupdatedon,ugsbetid as order_number,riskamt as bet,riskamt as valid_bet,winloss as win_loss");
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
     * 按分钟检索事务
     * @param $stime
     * @param $etime
     */
    public function orderByTime($stime, $etime)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //接数据时间间隔不能超过30分钟
        if ($endTime - $startTime > 1800) {
            $endTime = $startTime + 1800;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $stime = date('c', $startTime);
        $etime = date('c', $endTime);
        $param = [
            'startdate' => $stime,
            'enddate' => $etime,
            'issettled' => true,
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/api/history/bets', $param, false, true);
        //接口报错
        if (!$res['responseStatus']) {
            return false;
        }
        //接口内容报错
        if (isset($res["err"]) && $res["err"] > 0) {
            return false;
        }
        unset($res['responseStatus']);
        $this->updateOrder($res);
        true;
    }

    /**
     * 按小时拉取
     * @param $stime
     * @param $etime
     */
    public function orderByHour($stime, $etime)
    {
        return $this->orderByTime($stime, $etime);
    }


    /**
     * 发送请求
     * @param string $action
     * @param array $param 请求参数
     * @param bool $is_post 是否为post请求
     * @param bool $status 是否返回请求状态
     * @param bool $is_order 是否请求订单接口
     * @return array|string
     */
    public function requestParam($action, array $param, bool $is_post = true, $is_order = false)
    {
        $config = $this->initConfigMsg($this->game_type);
        if(!$config){
            $ret = [
                'responseStatus' => false,
                'message' => 'api not config'
            ];
            GameApi::addElkLog($ret, $this->game_type);
            return $ret;
        }
        $apiUrl = $is_order ? $config['orderUrl'] : $config['apiUrl'];
        $header = [
            'X-QM-Accept:json',
            'Accept:application/json',
            'X-QM-ClientId:' . $config['cagent'],
            'X-QM-ClientSecret:' . $config['key'],
        ];

        $url = rtrim($apiUrl, '/') . $action;

        if ($is_post) {
            $re = Curl::post($url, null, $param, null, true, $header);
        } else {
            if ($param) {
                $queryString = http_build_query($param, '', '&');
                $url .= '?' . $queryString;
            }
            $re = Curl::get($url, null, true, $header);
        }

        if ($re['status'] == 200) {
            $re['content'] = json_decode($re['content'], true);
        }
        GameApi::addRequestLog($url, $this->game_type, $param, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ret = [];
        if ($re['status'] == 200) {
            $ret = $re['content'];
            $ret['responseStatus'] = true;
        } else {
            $ret['responseStatus'] = false;
            $ret['message'] = $re['content'];
        }
        return $ret;
    }

}
