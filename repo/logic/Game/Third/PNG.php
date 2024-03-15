<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * PNG电子
 */
class PNG extends GameLogic
{
    protected $game_type = 'PNG';
    protected $orderTable = 'game_order_png';


    /**
     * 同步订单
     */
    public function synchronousData()
    {
        return true;
    }

    /**
     * 订单校验
     */
    public function synchronousCheckData()
    {
        return true;
    }

    /**
     * PNG推送订单消息
     * @param $data
     * @return bool
     */
    public function updateOrder($data)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            date_default_timezone_set("Etc/GMT");
            $startDate = strtotime($val['Time']);
            date_default_timezone_set($default_timezone);
            $insertData2 = [
                'tid' => intval(ltrim($val['ExternalUserId'], 'game')),
                'OCode' => $val['TransactionId'],
                'Username' => $val['ExternalUserId'],
                'gameDate' => date('Y-m-d H:i:s', $startDate),
                'gameCode' => $val['GameId'],
                'betAmount' => bcmul($val['RoundLoss'], 100, 0),
                'winAmount' => bcmul($val['Amount'], 100, 0),
                'income' => bcmul($val['Amount'] - $val['RoundLoss'], 100, 0),
            ];
            unset($val['TransactionId'], $val['Time'], $val['ExternalUserId'], $val['GameId'], $val['TotalLoss'], $val['TotalGain'], $val['MessageType'], $val['MessageTimestamp']);
            $insertData[] = array_merge($insertData2, $val);
        }
        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
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
            ->selectRaw("id,gameDate,TransactionId as order_number,betAmount as bet,betAmount as valid_bet,Amount as win_loss");
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
        return true;
    }

    /**
     * 按小时拉取
     * @param $stime
     * @param $etime
     * @return array
     */
    public function orderByHour($stime, $etime)
    {
        return $this->orderByTime($stime, $etime);
    }

}
