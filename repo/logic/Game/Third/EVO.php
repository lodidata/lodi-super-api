<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Logic;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * EVO真人
 */
class EVO extends GameLogic
{

    protected $game_type = 'EVO';
    protected $orderTable = 'game_order_evo';

    /**
     * 同步订单 延迟 15 分钟
     * @return bool
     * @throws \Exception
     */
    public function synchronousData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('gameDate');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 120;
        }
        $startTime = $startTime - 900;//取15分钟前的数据
        $default_timezone = date_default_timezone_get();
        $endTime = $now;

        date_default_timezone_set("Etc/GMT");
        $fields = [
            'startDate' => date("Y-m-d\TH:i:s.v\Z", $startTime)
        ];
        date_default_timezone_set($default_timezone);

        $res = $this->requestParam($fields);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }

        if (!empty($res['data'])) {
            $this->updateOrder($res['data']);
        }
        $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
        return true;
    }

    public function synchronousCheckData()
    {
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('gameDate', '>=', $start_time)
            ->where('gameDate', '<=', $end_time)
            ->selectRaw("sum(betAmount) as bet,sum(betAmount) as valid_bet,sum(winAmount) as win_loss")
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
     * 6.2.按分钟检索事务
     */
    public function orderByTime($stime, $etime)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        $default_timezone = date_default_timezone_get();

        date_default_timezone_set("Etc/GMT");
        $fields = [
            'startDate' => date("Y-m-d\TH:i:s.v\Z", $startTime)
        ];
        date_default_timezone_set($default_timezone);

        $res = $this->requestParam($fields);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }

        if (!empty($res['data'])) {
            $this->updateOrder($res['data']);
        }

        return true;
    }

    /**
     * test 按小时拉取
     * @param $stime
     * @param $etime
     */
    public function orderByHour($stime, $etime)
    {
        $this->orderByTime($stime, $etime);
    }

    /**
     * 更新订单
     * @param $data
     * @param int $updateStatus
     */
    public function updateOrder($data, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $valItem) {
            foreach ($valItem['games'] as $valItem3) {
                date_default_timezone_set("Etc/GMT");
                $startTime = strtotime($valItem3['startedAt']);
                $endTimeAt = strtotime($valItem3['settledAt']);
                $gameId = $valItem3['table']['id'];
                date_default_timezone_set($default_timezone);
                foreach ($valItem3['participants'] as $item) {
                    //校验更新，存在不处理
                    if ($updateStatus) {
                        if (\DB::table($this->orderTable)->where('OCode', $item['bets'][0]['transactionId'])->count()) {
                            continue;
                        }
                    }
                    $betAmount = array_sum(array_column($item['bets'], 'stake'));
                    $winAmount = array_sum(array_column($item['bets'], 'payout'));
                    $insertData[] = [
                        'tid' => intval(ltrim($item['playerId'], 'game')),
                        'OCode' => $item['bets'][0]['transactionId'],
                        'Username' => $item['playerId'],
                        'gameDate' => date('Y-m-d H:i:s', $endTimeAt),
                        'gameCode' => $gameId,
                        'betAmount' => bcmul($betAmount, 100, 0),
                        'winAmount' => bcmul($winAmount, 100, 0),
                        'income' => bcmul($winAmount - $betAmount, 100, 0),
                        'startTime' => date('Y-m-d H:i:s', $startTime),
                    ];
                }
            }
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 发送请求
     * @param array $param 请求参数
     * @return array|string
     */
    public function requestParam(array $param)
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
        $querystring = urldecode(http_build_query($param, '', '&'));
        $headers = [
            'Authorization: Basic ' . base64_encode($config['cagent'] . ':' . $config['key'])
        ];
        $url = $config['orderUrl'] . '/api/gamehistory/v1/casino/games?' . $querystring;
        //echo $url;die;
        $re = Curl::get($url, null, true, $headers);
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
