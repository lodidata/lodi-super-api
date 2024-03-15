<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class UG
 */
class UG extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_ug';
    protected $game_type = 'UG';

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
            $SortNo = $r_time;
        } else {
            $sortNo = \DB::table($this->orderTable)->max('SortNo');
            $SortNo = $sortNo ? $sortNo : 0; //最小从0开始
        }
        return $this->orderByTime($SortNo, '', true);
    }

    /**
     * 更新订单表
     * @param array $data
     * @param $SortNo
     * @param int $updateStatus
     * @return bool
     */
    private function updateOrder($data, $SortNo, $updateStatus = 0)
    {
        $insertData = [];
        $default_timezone = date_default_timezone_get();
        foreach ($data as $key => $val) {
            //5为投注状态已结算
            if ($val['status'] != 5) {
                continue;
            }
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('BetID', $val['ticketId'])->count()) {
                    continue;
                }
            }
            $SortNo = $val['sortNo'] > $SortNo ? $val['sortNo'] : $SortNo;
            date_default_timezone_set("Etc/GMT-8");
            $betTime = strtotime($val['betTime']);//注單建立時間
            $ReportDate = strtotime($val['reportTime']);//注单报表时间
            $UpdateTime = strtotime($val['updateTime']);//注单更新时间
            date_default_timezone_set($default_timezone);
            $val['betTime'] = date('Y-m-d H:i:s', $betTime);
            $val['reportTime'] = date('Y-m-d H:i:s', $ReportDate);
            $val['updateTime'] = date('Y-m-d H:i:s', $UpdateTime);

            $insertData[] = [
                'tid' => intval(ltrim($val['userId'], 'game')),
                'BetID' => $val['ticketId'],
                'GameID' => $val['detail'][0]['sportId'],
                'SubGameID' => $val['detail'][0]['sportId'],
                'Account' => $val['userId'],
                'Currency' => $val['currencyId'],
                'BetAmount' => $val['stake'],
                'BetOdds' => $val['detail'][0]['odds'],
                'AllWin' =>$val['stake'],
                'DeductAmount' => $val['netStake'],
                'BackAmount' => $val['payout'],
                'Win' => $val['winLose'],
                'Turnover' => $val['turnover'],
                'OddsStyle' => $val['oddsExpressionShortName'],
                'BetDate' => $val['betTime'],
                'Status' => $val['status'],
                'Result' => $val['result'],
                'ReportDate' => $val['reportTime'],
                'BetIP' => $val['ip'],
                'UpdateTime' => $val['updateTime'],
                'BetInfo' => json_encode($val['detail'], JSON_UNESCAPED_UNICODE),
                'BetResult' => json_encode($val['insurance'], JSON_UNESCAPED_UNICODE),
                'BetType' => $val['parlayTypeName'],
                'BetPos' => 1,
                'AgentID' => $val['agentId'],
                'SortNo' => $val['sortNo'],
            ];
        }
        $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
        return $SortNo;
    }

    /**
     * 订单校验 校验前一天
     * @return bool
     * @throws \Exception
     */
    public function synchronousCheckData()
    {
        if (!$this->checkStatus()) {
            return false;
        }
        $now = time();
        $today = date('Y-m-d');
        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = date('Y-m-d', strtotime('-1 day'));
        }
        $endDay = date('Y-m-d', strtotime($startTime, '+1 day'));
        //当天12点前不拉昨天数据,今天数据不校验
        if (strtotime($endDay.' 12:00:00') > $now || $today == $startTime || $startTime == $endDay) {
            return true;
        }

        $params = [
            'status' => 5,
            'reportDate' => $startTime,
        ];

        $res = $this->requestParam('/api/transfer/getTicketTotalByReportTime', $params, true, true);
        if (!$res['responseStatus']) {
            return false;
        }
        if (!isset($res['data']) || empty($res['data'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }
        //总订单数
        $betCount = 0;
        //下注金额
        $betAmount = 0;
        //输赢金额
        $winAmount = 0;
        foreach ($res['data'] as $val) {
            $betAmount += $val['stake'];
            $betCount += $val['ticketCount'];
            $winAmount += $val['winLose'];
        }

        if ($betAmount == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        $result = \DB::table($this->orderTable)
            ->where('ReportDate', '>=', $startTime)
            ->where('ReportDate', '<', $endDay)
            ->select(\DB::raw("count(0) as betCount,sum(BetAmount) as betAmount, sum(Win) as winAmount"))->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
                return true;
            }
        }
        //订单数不对补单
        $SortNo = \DB::table($this->orderTable)
            ->where('ReportDate', '>=', $startTime)->min('SortNo');
        $this->orderByTime($SortNo ?: 0, $endDay);

        //第二次校验
        $result = \DB::table($this->orderTable)
            ->where('ReportDate', '>=', $startTime)
            ->where('ReportDate', '<', $endDay)
            ->select(\DB::raw("count(0) as betCount,sum(BetAmount) as betAmount, sum(Win) as winAmount"))->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
                return true;
            }
        }
        //订单数不对
        $this->addGameOrderCheckError($this->game_type, $now, $params, $startTime, $endDay, $betAmount, $winAmount, $result->betAmount, $result->winAmount);

        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('BetDate', '>=', $start_time)
            ->where('BetDate', '<=', $end_time)
            ->selectRaw("sum(BetAmount) as bet,sum(Turnover) as valid_bet,sum(Win) as win_loss")
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
            'username' => 'Account',
            'bet' => 'BetAmount',
            'win' => 'BackAmount',
            'profit' => 'Win',
            'gameDate' => 'BetDate'
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
            ->where('BetDate', '>=', $start_time)
            ->where('BetDate', '<=', $end_time)
            ->where('Account', 'like', "%$user_prefix%")
            ->selectRaw("id,BetDate,BetID as order_number,BetAmount,Turnover as valid_bet,Win as win_loss");
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
     * @param bool $is_redis
     * @return bool
     */
    public function orderByTime($stime, $etime, $is_redis = false)
    {
        $fields = [
            'agentId' => 0,
            'sortNo' => $stime,//最小排序编号
            'row' => 1000,
        ];
        $res = $this->requestParam('/api/transfer/getTicketBySortNo', $fields, true);
        if (!$res['responseStatus']) {
            return false;
        }
        $SortNo = $this->updateOrder($res['data'], $stime);
        if($is_redis){
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $SortNo);
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
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @param bool $is_post 是否为post请求
     * @param bool $is_order 是否为获取注单
     * @return array|string
     */
    public function requestParam(string $action, array $param, bool $is_post = true, $is_order = false)
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
        $url = rtrim($is_order ? $config['orderUrl'] : $config['apiUrl'], '/') . $action;

        $param['apiKey'] = $config['key'];
        $param['operatorId'] = $config['des_key'];

        $re = Curl::post($url, null, $param, null, true);
        if ($re['status'] != 200) {
            $ret['responseStatus'] = false;
            $ret['status'] = $re['status'];
            $ret['msg'] = $re['content'];
            GameApi::addRequestLog($url, $config['type'], $param, json_encode($re, JSON_UNESCAPED_UNICODE));
        } else {
            $ret = json_decode($re['content'], true);
            if (isset($ret['code']) && $ret['code'] == '000000') {
                $ret['responseStatus'] = true;
            } else {
                $ret['responseStatus'] = false;
            }
            GameApi::addRequestLog($url, $config['type'], $param, json_encode($ret, JSON_UNESCAPED_UNICODE));
        }
        return $ret;
    }

}
