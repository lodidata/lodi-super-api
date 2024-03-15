<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Logic;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * TF电竞
 */
class TF extends GameLogic
{
    protected $game_type = 'TF';
    protected $orderTable = 'game_order_tf';


    /**
     * 延期2分钟
     * 接数据时间间隔不能超过一天
     * @throws \Exception
     */
    public function synchronousData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('settlement_datetime');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 2 * 60; //取2分钟前的数据
        }
        $endTime = $now;
        //接数据时间间隔不能超过一天
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $page = 1;
        $default_timezone = date_default_timezone_get();
        while (1) {
            date_default_timezone_set("Etc/GMT");
            $param = [
                'from_settlement_datetime' => date('Y-m-d\TH:i:s\Z', $startTime),
                'to_settlement_datetime' => date('Y-m-d\TH:i:s\Z', $endTime),
                'page' => $page,
                'page_size' => 1000,
            ];
            date_default_timezone_set($default_timezone);
            $res = $this->requestParam('/api/v2/bet-transaction/', $param, false, true);
            if (!$res['status']) {
                break;  //请求超时，不做任何处理
            }
            if ($res['content']['count'] == 0) { // 未有任何订单数据
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
                break;
            }

            $this->updateOrder($res['content']['results']);

            if (is_null($res['content']['next'])) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
                break;
            }
            $page++;
        }
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
            $last_datetime = \DB::table($this->orderTable)->max('settlement_datetime');
            $startTime = $last_datetime ? strtotime($last_datetime) : strtotime(date('Y-m-d H:00:00', $now - 86400)); //取1天前的数据
        }
        $endTime = strtotime(date('Y-m-d H:00:00', $now - 3600)); //取1小时前的数据
        //每次最大拉取区间1 小时内
        if ($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT");

        $params = [
            'from_settlement_datetime' => date('Y-m-d\TH:00:00\Z', $startTime),
            'to_settlement_datetime' => date('Y-m-d\TH:00:00\Z', $endTime),
            'page' => 1,
            'page_size' => 10000,
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/api/v2/member-summary/', $params, false, true);
        if (!$res['status']) {
            return false;  //请求超时，不做任何处理
        }
        if ($res['content']['count'] == 0) { // 未有任何订单数据
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }
        //下注金额
        $betAmount = 0;
        //输赢额
        $winAmount = 0;
        foreach ($res['content']['results'] as $val) {
            $betAmount += $val['amount'];
            $winAmount += $val['earnings'];
        }
        $result = \DB::table($this->orderTable)
            ->where('settlement_datetime', '>=', date("Y-m-d H:00:00", $startTime))
            ->where('settlement_datetime', '<=', date("Y-m-d H:00:00", $endTime))
            ->select(\DB::raw("sum(amount) as betAmount, sum(earnings) as winAmount"))->first();
        //金额正确
        if (bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        //金额不对,重新拉单
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime));

        $result2 = \DB::table($this->orderTable)
            ->where('settlement_datetime', '>=', date("Y-m-d H:00:00", $startTime))
            ->where('settlement_datetime', '<=', date("Y-m-d H:00:00", $endTime))
            ->select(\DB::raw("sum(amount) as betAmount, sum(earnings) as winAmount"))->first();
        //金额不正确
        if (!(bccomp($betAmount, $result2->betAmount, 2) == 0 && bccomp($winAmount, $result2->winAmount, 2) == 0)) {
            $this->addGameOrderCheckError($this->game_type,$now, $params, date("Y-m-d H:00:00", $startTime), date('Y-m-d H:00:00', $endTime), $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
            return false;
        }

        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
        return true;
    }

    public function updateOrder($data, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $key => $val) {
            //已结算
            if ($val['settlement_status'] != 'settled') {
                continue;
            }
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('order_id', $val['order_id'])->count()) {
                    continue;
                }
            }
            $val['order_id'] = $val['id']; //订单号
            unset($val['id']);
            date_default_timezone_set("Etc/GMT");
            $settlement_datetime = strtotime($val['settlement_datetime']); //结算时间
            $date_created = strtotime($val['date_created']); //下注时间
            $modified_datetime = strtotime($val['modified_datetime']);
            $event_datetime = strtotime($val['event_datetime']); //赛事开始时间
            date_default_timezone_set($default_timezone);
            $val['tid'] = intval(ltrim($val['member_code'], 'game'));
            $val['settlement_datetime'] = date('Y-m-d H:m:i', $settlement_datetime);
            $val['date_created'] = date('Y-m-d H:m:i', $date_created);
            $val['modified_datetime'] = date('Y-m-d H:m:i', $modified_datetime);
            $val['event_datetime'] = date('Y-m-d H:m:i', $event_datetime);
            $val['amount'] = abs($val['amount']);

            $insertData[] = $val;
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('settlement_datetime', '>=', $start_time)
            ->where('settlement_datetime', '<=', $end_time)
            ->selectRaw("sum(amount) as bet,sum(amount) as valid_bet,sum(earnings) as win_loss")
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
            'username' => 'member_code',
            'bet' => 'amount',
            'win' => 'amount+earnings',
            'profit' => 'earnings',
            'gameDate' => 'settlement_datetime'
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
            ->where('settlement_datetime', '>=', $start_time)
            ->where('settlement_datetime', '<=', $end_time)
            ->where('member_code', 'like', "%$user_prefix%")
            ->selectRaw("id,settlement_datetime,order_id as order_number,amount as bet,amount as valid_bet,earnings as win_loss");
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
     * @return bool
     */
    public function orderByTime($stime, $etime)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //接数据时间间隔不能超过一天
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $page = 1;
        $default_timezone = date_default_timezone_get();
        while (1) {
            date_default_timezone_set("Etc/GMT");
            $param = [
                'from_settlement_datetime' => date('Y-m-d\TH:i:s\Z', $startTime),
                'to_settlement_datetime' => date('Y-m-d\TH:i:s\Z', $endTime),
                'page' => $page,
                'page_size' => 1000,
            ];
            date_default_timezone_set($default_timezone);
            $res = $this->requestParam('/api/v2/bet-transaction/', $param, false, true);
            if (!$res['status']) {
                break;  //请求超时，不做任何处理
            }
            if ($res['content']['count'] == 0) { // 未有任何订单数据
                break;
            }

            $this->updateOrder($res['content']['results']);

            if (is_null($res['content']['next'])) {
                break;
            }
            $page++;
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
     * 发送请求
     * @param string $action
     * @param array $param 请求参数
     * @param bool $is_post 是否为post请求
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
            'Authorization:Token ' . $config['key'],
        ];

        $url = rtrim($apiUrl, '/') . $action;

        if ($is_post) {
            $re = Curl::post($url, null, $param, null, true, $header);
        } else {
            if ($param) {
                $url .= '?' . http_build_query($param, '', '&');
            }
            $re = Curl::get($url, null, true, $header);
        }

        GameApi::addRequestLog($url, 'TF', $param, $re['content'], 'status:' . $re['status']);
        /**
         * 3. 回应都是以 HTTP 状态码为主.
         * a. 2xx – 成功
         * b. 4xx – 请求错误
         * c. 5xx – 服务器错误
         */
        if (!is_array($re)) {
            $re['content']['errors'] = $re;
            $re['status'] = false;
        } elseif ($is_order && $re["status"] == 404) {
            //汇总无数据
            $re['status'] = true;
        } elseif (isset($re["status"]) && $re["status"] >= 200 && $re["status"] < 300) {
            $re['status'] = true;
        } else {
            $re['status'] = false;
        }
        $re['content'] = json_decode($re['content'], true);
        return $re;
    }
}