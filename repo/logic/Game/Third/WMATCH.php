<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;
use function GuzzleHttp\Psr7\str;

/**
 * Explain: WMATCH 游戏接口
 *
 * OK
 */
class WMATCH extends GameLogic
{

    protected $game_type = 'WMATCH';
    protected $orderTable = 'game_order_wmatch';

    /**
     * 同步订单
     * @return bool
     */
    public function synchronousData()
    {
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type); //上次的结束时间
        $this->orderByTime($r_time, '', true);

    }

    public function orderByTime($r_time, $e_time, $is_redis = false)
    {
        $now = time();
        if ($r_time) {
            // 上次拉单结束时间超过当前时间，从当前时间往前半小时开始拉单
            if ($r_time >= $now) {
                $startTime = $now - 1800;
            } else {
                $startTime = $r_time;
            }
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('roundEndTime');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 1800;
        }

        $config = $this->initConfigMsg($this->game_type);
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $fields = [];
        $date_ymd = date("Ymd", $startTime);
        $date_h = date("H", $startTime);
        $date_i = date("i", $startTime);
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/platform/feed/export/json/' . $config['key'] . '/' . $date_ymd . '/' . $date_h . '/' . $date_i . '?interval=30&jackpot=0', $fields, false, true, true);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        if (!empty($res['content'])) {
            $this->updateOrder($res['content']);
        }
        if ($is_redis) {
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $startTime + 30 * 60);
        }
    }

    /**
     * 订单校验
     */
    public function synchronousCheckData()
    {
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('roundEndTime', '>=', $start_time)
            ->where('roundEndTime', '<=', $end_time)
            ->selectRaw("sum(totalBetAmount) as bet,sum(totalBetAmount) as valid_bet,sum(totalWinAmount) as win_loss")
            ->first();
        return (array) $result;
    }

    /**
     * 游戏统计
     * @param null $date 日期
     * @return bool
     */
    public function queryOperatesOrder($date = null)
    {
        $data = [
            'username' => 'externalUserId',
            'bet' => 'totalBetAmount',
            'win' => 'totalWinAmount',
            'profit' => 'totalWinAmount-totalBetAmount',
            'gameDate' => 'roundEndTime'
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
            ->where('roundEndTime', '>=', $start_time)
            ->where('roundEndTime', '<=', $end_time)
            ->where('externalUserId', 'like', "%$user_prefix%")
            ->selectRaw("id,roundEndTime,roundId as order_number,totalBetAmount as bet,totalBetAmount as valid_bet,totalWinAmount as win_loss");
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
     * @param $data
     * @param int $updateStatus
     * @return bool
     */
    public function updateOrder($data, $updateStatus = 0)
    {
        $gameList = $this->redis->get('super_game_wmatch_3th');
        if (is_null($gameList) || $gameList == "null" || empty($gameList)) {
            $game3th = \DB::table('game_3th')->whereIn('game_id', [140, 141, 142, 143])->get(['kind_id', 'game_id'])->toArray();
            foreach ($game3th as $val) {
                $val = (array) $val;
                $gameList[$val['kind_id']] = $val['game_id'];
            }
            $this->redis->setex('super_game_wmatch_3th', 86400, json_encode($gameList));
        } else {
            $gameList = json_decode($gameList, true);
        }
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('roundId', (string) $val['A'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT+4");
            $roundStartTime = strtotime($val['I']);
            $roundEndTime = strtotime($val['J']);
            date_default_timezone_set($default_timezone);
            $bet = [];
            $bet['roundStartTime'] = date('Y-m-d H:i:s', $roundStartTime);
            $bet['roundEndTime'] = date('Y-m-d H:i:s', $roundEndTime);
            $bet['tid'] = intval(ltrim($val['F'], 'game'));
            //$bet['walletInitialBalance'] = bcmul($val['K'], 100, 0);
            //$bet['walletFinalBalance'] = bcmul($val['L'], 100, 0);
            $bet['totalBetAmount'] = bcmul($val['M'], 100, 0);
            $bet['totalWinAmount'] = bcmul($val['N'], 100, 0);
            $bet['jackpotBetAmount'] = bcmul($val['O'], 100, 0);
            $bet['jackpotWinAmount'] = bcmul($val['P'], 100, 0);
            $bet['roundId'] = $val['A'];
            $bet['gameIdentify'] = explode('-', $val['B'])[1];
            $bet['currency'] = $val['C'];
            //$bet['walletType'] = $val['D'];
            //$bet['externalReference'] = $val['E'];
            $bet['externalUserId'] = $val['F'];
            //$bet['userToken'] = $val['G'];
            //$bet['sessionToken'] = $val['H'];
            $bet['gameTypeId'] = isset($gameList[explode('-', $val['B'])[1]]) ? $gameList[explode('-', $val['B'])[1]] : 140;

            $insertData[] = $bet;
        }
        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @return array|string
     */
    public function requestParam(string $action, array $param, bool $is_post = true, $status = true, $is_order = false)
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
        $url = rtrim($is_order ? $config['orderUrl'] : $config['apiUrl']) . $action;
        $headers = array(
            'Content-Type: application/json',
        );
        if ($is_post) {
            $re = Curl::commonPost($url, null, json_encode($param), $headers, $status);
        } else {
            $queryString = http_build_query($param, '', '&');
            if ($queryString) {
                $url .= '?' . $queryString;
            }
            $re = Curl::get($url, null, $status, $headers);
        }
        GameApi::addRequestLog($url, $config['type'], $param, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ret['content'] = json_decode($re['content'], true);
        if ($re['status'] == 200) {
            $ret['responseStatus'] = true;
        } else {
            $ret['responseStatus'] = false;
            $ret['msg'] = isset($ret['content']) ?? 'api error';
        }
        return $ret;
    }

}