<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Logic;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * SBO体育
 */
class SBO extends GameLogic
{
    protected $game_type = 'SBO';
    /**
     * @var string 注单类型
     */
    protected $Portfolio = 'SportsBook';
    protected $orderTable = 'game_order_sbo';
    /**
     * @var string 每个伺服器名称需唯一
     */
    protected $serverId = 'caaya_sbo_001';
    protected $agent = 'rt205sbo26';


    /**
     * 资料的搜索范围必须是在60天以内。
     * 修改时间区间需小于或等于30分鐘。
     * @throws \Exception
     */
    public function synchronousData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('orderTime');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 2 * 60; //取2分钟前的数据
        }
        $endTime = $now;
        if ($endTime - $startTime > 1800) {
            $endTime = $startTime + 1800;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $stime = date('Y-m-d\TH:i:00\Z', $startTime);
        $etime = date('Y-m-d\TH:i:00\Z', $endTime);
        $param = [
            'Portfolio' => $this->Portfolio,
            'StartDate' => $stime,
            'EndDate' => $etime,
            'Language' => 'en',
            'IsGetDownline' => false,
        ];
        date_default_timezone_set($default_timezone);
        $action = "/web-root/restricted/report/v2/get-bet-list-by-modify-date.aspx";
        $res = $this->requestParam($action, $param);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        //内容报错
        if (!(isset($res['error']) && isset($res['error']['id']) && $res['error']['id'] == 0)) {
            return false;
        }

        if (!isset($res['result']) || count($res['result']) <= 0) { // 未有任何订单数据
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            return;
        }

        $this->updateOrder($res['result']);

        $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
    }

    /**
     * 订单校验
     * 功能说明
     * 5.1 根据输赢日期获取客户报表
     * 若提供的使用者名称为代理名称，该次请求会回传所有该代理下的会员的报表。
     * 若提供的使用者名称为会员名称，则该次请求回传该会员的报表。
     * 此请求中的日期参数应基于WinLostDate。
     * 资料的搜索范围必须是在60天以内。
     * @return bool
     * @throws \Exception
     */
    public function synchronousCheckData()
    {
        $day = date('Y-m-d', strtotime('-1 day'));

        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type);//上次的结束时间
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
        $strtime = strtotime($startDay);
        $endDay = date("Y-m-d", strtotime('+1 day', $strtime));
        //正常拉单时间
        $lastTime = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);
        //取1天前的数据 当前过2时,正常拉单时间小于汇总时间
        if (($startDay == $day && date('H') < 2) || (!is_null($lastTime) && $lastTime < strtotime($endDay))) {
            return true;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $config = $this->initConfigMsg('SBO');
        if (!$config) {
            return false;
        }
        $params = [
            'Username' => $config['cagent'],
            'Portfolio' => $this->Portfolio,
            'StartDate' => date('Y-m-d H:i:s', $strtime),
            'EndDate' => date('Y-m-d H:i:s', $strtime),
        ];
        date_default_timezone_set($default_timezone);
        $action = "/web-root/restricted/report/get-customer-report-by-win-lost-date.aspx";
        $res = $this->requestParam($action, $params);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        //内容报错
        if (!(isset($res['error']) && isset($res['error']['id']) && $res['error']['id'] == 0)) {
            return false;
        }
        //没有数据
        if (!isset($res['result']) || count($res['result']) <= 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            $this->redis->del(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type);
            return true;
        }

        $betCount = 0;
        $betAmount = 0;
        $winAmount = 0;

        foreach ($res['result'] as $val) {
            if(isset($val['turnover']['total'])){
                $total = $val['turnover']['total'];
            }else{
                $total = bcadd($val['turnover']['lose']+$val['turnover']['draw'], $val['turnover']['won'], 2);
            }

            $betAmount = bcadd($betAmount, $total, 2);
            $winAmount = bcadd($winAmount, $val['winlose'], 2);
        }
        $result = \DB::table($this->orderTable)
            ->where('winLostDate', '>=', $startDay)
            ->where('winLostDate', '<=', $endDay)
            ->select(\DB::raw("sum(stake) as betAmount, sum(winlost) as winAmount"))->first();
        //金额正确
        if (bccomp($betAmount, $result->betAmount, 2) < 0 && bccomp($winAmount, $result->winAmount, 2) < 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            $this->redis->del(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type);
            return true;
        }

        //金额不对,重新拉单
        $this->orderByTime($startDay, $endDay);

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('winLostDate', '>=', $startDay)
            ->where('winLostDate', '<=', $endDay)
            ->select(\DB::raw("sum(stake) as betAmount, sum(winlost) as winAmount"))->first();
        //金额不正确
        if (!(bccomp($betAmount, $result2->betAmount, 2) < 0 && bccomp($winAmount, $result2->winAmount, 2) < 0)) {
            $this->addGameOrderCheckError($this->game_type, time(), $params, $startDay, $endDay, $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
            return false;
        }

        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
        $this->redis->del(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type);
        return true;
    }

    public function updateRptOrdersMiddleDay($day)
    {
        $betCount = 0;
        $betAmount = 0;
        $winAmount = 0;
        $default_timezone = date_default_timezone_get();
        $strtime = strtotime($day);
        $endtime = strtotime('+1 day', $strtime);
        //SportsBook
        date_default_timezone_set("Etc/GMT+4");
        $config = $this->initConfigMsg('SBO');
        if (!$config) {
            return false;
        }
        $params = [
            'Username' => $config['cagent'],
            'Portfolio' => 'SportsBook',
            'StartDate' => date('Y-m-d H:i:s', $strtime),
            'EndDate' => date('Y-m-d H:i:s', $endtime),
        ];
        date_default_timezone_set($default_timezone);
        $action = "/web-root/restricted/report/get-customer-report-by-win-lost-date.aspx";
        $res = $this->requestParam($action, $params);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        //内容报错
        if (!(isset($res['error']) && isset($res['error']['id']) && $res['error']['id'] == 0)) {
            return false;
        }
        if (is_array($res['result'])) {
            foreach ($res['result'] as $val) {
                if(isset($val['turnover']['total'])){
                    $total = $val['turnover']['total'];
                }else{
                    $total = bcadd($val['turnover']['lose']+$val['turnover']['draw'], $val['turnover']['won'], 2);
                }

                $betAmount = bcadd($betAmount, $total, 2);
                $winAmount = bcadd($winAmount, $val['winlose'], 2);
            }
        }
        //VirtualSports
        date_default_timezone_set("Etc/GMT+4");
        $params2 = [
            'Username' => $config['cagent'],
            'Portfolio' => 'VirtualSports',
            'StartDate' => date('Y-m-d H:i:s', $strtime),
            'EndDate' => date('Y-m-d H:i:s', $endtime),
        ];
        date_default_timezone_set($default_timezone);
        $action = "/web-root/restricted/report/get-customer-report-by-win-lost-date.aspx";
        $res2 = $this->requestParam($action, $params2);
        //接口错误
        if (!$res2['responseStatus']) {
            return false;
        }
        //内容报错
        if (!(isset($res2['error']) && isset($res2['error']['id']) && $res2['error']['id'] == 0)) {
            return false;
        }

        if (is_array($res2['result'])) {
            foreach ($res2['result'] as $val) {
                if(isset($val['turnover']['total'])){
                    $total = $val['turnover']['total'];
                }else{
                    $total = bcadd($val['turnover']['lose']+$val['turnover']['draw'], $val['turnover']['won'], 2);
                }

                $betAmount = bcadd($betAmount, $total, 2);
                $winAmount = bcadd($winAmount, $val['winlose'], 2);
            }
        }

        if ($betAmount > 0 || $winAmount > 0) {
            $res = \DB::table('rpt_orders_middle_day')
                ->where('game_type', 'SBO')
                ->where('count_date', $day)
                ->where('tid', 33)
                ->update([
                    'game_bet_amount' => $betAmount,
                    'game_order_profit' => $winAmount,
                    'game_prize_amount' => bcadd($betAmount, $winAmount, 2)
                ]);
        }
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('orderTime', '>=', $start_time)
            ->where('orderTime', '<=', $end_time)
            ->selectRaw("sum(stake) as bet,sum(stake) as valid_bet,sum(winlost) as win_loss")
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
            'username' => 'username',
            'bet' => 'stake',
            'win' => 'winlost+stake',
            'profit' => 'winlost',
            'gameDate' => 'orderTime'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, 'SBO', $data, false);
    }


    public function queryHotOrder($user_prefix, $startTime, $endTime, $args = [])
    {
        return [];
    }

    public function queryLocalOrder($user_prefix, $start_time, $end_time, $page = 1, $page_size = 500)
    {
        $query = \DB::table($this->orderTable)
            ->where('orderTime', '>=', $start_time)
            ->where('orderTime', '<=', $end_time)
            ->where('username', 'like', "%$user_prefix%")
            ->selectRaw("id,orderTime,refNo as order_number,stake as bet,stake as valid_bet,winlost as win_loss");
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
        $default_timezone = date_default_timezone_get();

        $formStartTime = strtotime($stime);
        $formEndTime = strtotime($etime);
        while (1) {
            //拉单不能超过30分钟
            $nextEndTime = $formStartTime + 1800;
            if ($nextEndTime > $formEndTime) {
                $nextEndTime = $formEndTime;
            }

            date_default_timezone_set("Etc/GMT+4");
            $stime = date('Y-m-d\TH:i:00\Z', $formStartTime);
            $etime = date('Y-m-d\TH:i:00\Z', $nextEndTime);
            $param = [
                'Portfolio' => $this->Portfolio,
                'StartDate' => $stime,
                'EndDate' => $etime,
                'Language' => 'en',
                'IsGetDownline' => false,
            ];
            date_default_timezone_set($default_timezone);
            $action = "/web-root/restricted/report/v2/get-bet-list-by-modify-date.aspx";
            $res = $this->requestParam($action, $param);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            //内容报错
            if (!(isset($res['error']) && isset($res['error']['id']) && $res['error']['id'] == 0)) {
                return false;
            }

            if (isset($res['result']) && count($res['result']) > 0) { // 未有任何订单数据
                $this->updateOrder($res['result']);
            }

            if ($formEndTime == $formStartTime) {
                break;
            }
            $formStartTime = $nextEndTime;
            sleep(5);
        }

        return true;
    }

    public function orderByTimeUpdate($stime, $etime)
    {
        $default_timezone = date_default_timezone_get();

        $formStartTime = strtotime($stime);
        $formEndTime = strtotime($etime);
        while (1) {
            //拉单不能超过30分钟
            $nextEndTime = $formStartTime + 1800;
            if ($nextEndTime > $formEndTime) {
                $nextEndTime = $formEndTime;
            }

            date_default_timezone_set("Etc/GMT+4");
            $stime = date('Y-m-d\TH:i:00\Z', $formStartTime);
            $etime = date('Y-m-d\TH:i:00\Z', $nextEndTime);
            $param = [
                'Portfolio' => $this->Portfolio,
                'StartDate' => $stime,
                'EndDate' => $etime,
                'Language' => 'en',
                'IsGetDownline' => false,
            ];
            date_default_timezone_set($default_timezone);
            $action = "/web-root/restricted/report/v2/get-bet-list-by-modify-date.aspx";
            $res = $this->requestParam($action, $param);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            //内容报错
            if (!(isset($res['error']) && isset($res['error']['id']) && $res['error']['id'] == 0)) {
                return false;
            }

            if (isset($res['result']) && count($res['result']) > 0) { // 未有任何订单数据
                foreach ($res['result'] as $key => $val) {
                    \DB::table($this->orderTable)->where('refNo',$val['refNo'])->update(['winlost' => $val['winLost']]);
                }
            }

            if ($formEndTime == $formStartTime) {
                break;
            }
            $formStartTime = $nextEndTime;
            sleep(5);
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
        return true;
    }

    /**
     * 更新订单
     * @param $data
     * @param int $updateStatus
     * @return bool
     */
    public function updateOrder($data, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $key => $val) {
            if (!in_array($val['status'], ['draw', 'lose', 'won'])) {
                continue;
            }
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('refNo', $val['refNo'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT+4");
            $startDate = strtotime($val['orderTime']);
            $settleTime = strtotime($val['settleTime']);
            date_default_timezone_set($default_timezone);
            $insertData[] = [
                'tid' => intval(ltrim($val['username'], 'game')),
                'refNo' => $val['refNo'],
                'username' => $val['username'],
                'stake' => $val['stake'],
                'winlost' => $val['winLost'],
                'orderTime' => date("Y-m-d H:i:s", $startDate),
                'settleTime' => date("Y-m-d H:i:s", $settleTime),
                'sportsType' => $val['sportsType'],
                'odds' => $val['odds'],
                'oddsStyle' => $val['oddsStyle'],
                'status' => $val['status'],
                'subBet' => json_encode($val['subBet'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'currency' => $val['currency'] ?? 'PHP',
            ];
        }

        return $this->addGameOrders('SBO', $this->orderTable, $insertData);
    }

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @param bool $is_post 是否为post请求
     * @return array|string
     */
    public function requestParam(string $action, array $param, bool $is_post = true)
    {
        $config = $this->initConfigMsg("SBO");
        if (!$config) {
            $ret = [
                'responseStatus' => false,
                'message' => 'api not config'
            ];
            GameApi::addElkLog($ret, "SBO");
            return $ret;
        }
        $param['CompanyKey'] = $config['key'];
        $param['ServerId'] = $config['lobby'];
        //$querystring = urldecode(http_build_query($param,'', '&'));
        $url = $config['apiUrl'] . $action;
        if ($is_post) {
            $re = Curl::post($url, null, $param, null, true);
        } else {
            $re = Curl::get($url, null, true);
        }
        if ($re['status'] == 200) {
            $re['content'] = json_decode($re['content'], true);
        }
        GameApi::addRequestLog($url, "SBO", $param, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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