<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class VIVO
 */
class VIVO extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_evoplay';
    protected $game_type = 'VIVO';

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
     * 延迟通常不会超过5分钟，最大拉单区间1天
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
            $startTime = $now - 86400; //取1天内的数据
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
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('OCode', $val['round_id'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT");
            $gameDate = strtotime($val['bet_time']);//注單建立時間
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid' => intval(ltrim($val['user_name'], 'game')),
                'OCode' => $val['round_id'],
                'Username' => $val['user_name'],
                'gameDate' => date('Y-m-d H:i:s', $gameDate),
                'gameCode' => $val['gameCode'],
                'betAmount' => bcmul($val['bet_amount'], 100, 0),
                'winAmount' => bcmul($val['win_amount'], 100, 0),
                'income' => bcmul($val['win_amount']-$val['bet_amount'], 100, 0),
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
        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startDay = $r_time;
        } else {
            $startDay = $day; //取1天前的数据
        }

        //校验3次不通过则跳过
        $check_count = $this->redis->incr(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type);
        if($check_count > 3){
            $startDay = date("Y-m-d", strtotime('+1 day', strtotime($startDay)));
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startDay);
            $this->redis->set(CacheKey::$perfix['gameOrderCheckCount'] .  $this->game_type, 1);
        }


        $endDay = date("Y-m-d", strtotime('+1 day', strtotime($startDay)));
        //正常拉单时间
        $lastTime = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);

        //取1天前的数据 当前过12时,正常拉单时间小于汇总时间
        if (($startDay == $day && date('H') < 12) || (!is_null($lastTime) && $lastTime < strtotime($endDay . ' 12:00:00'))) {
            return true;
        }
        $config = $this->initConfigMsg($this->game_type);
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT");

        $params = [
            'start_time' => date('Y-m-d H:i:s', $startDay),
            'end_time' => date('Y-m-d H:i:s', $endDay),
            'OperatorKey' => $config['cagent'],
            'ReportType' => 'TOTALS_PER_TABLES',
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam($params);
        if (!(isset($res['status']) && $res['status'] == 200)) {
            return false;
        }

       //Total Bets;Total Wins;Table ID;Game ID;Game Description
       //{Total Rows=2}{10.2500;0.0000;18;8; BlackJack Live TB2[NL]1100.0000;1120.0000;16;8;BlackJackLive TB1}
        if(stripos($res['content'], "{Total") === false){
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }
        $contents = explode('}{', $res['content']);
        if(!isset($contents[0])){
            return true;
        }
        $totalArr = explode('=', $contents[0]);
        if(!isset($totalArr[0])){
            return true;
        }
        //总记录数
        $total = $totalArr[1];
        if ($total === 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        //记录内容
        $recodes = explode('[NL]', $contents[1]);
        //记录数不一致
        if($total != count($recodes)){
            $this->logger->error('VIVO synchronousCheckData 记录数不一致 :' . $res['content']);
            return false;
        }
        //总订单数
        $betCount = 0;
        //下注金额
        $betAmount = 0;
        //输赢金额
        $winAmount = 0;
        foreach ($recodes as $val) {
            $val = explode(';', $val);
            $betAmount += $val[0];
            $winAmount += $val[1];
        }

        $betAmount = bcmul($betAmount, 100, 0);
        $winAmount = bcmul($winAmount, 100, 0);

        if ($betAmount == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        $result = \DB::table($this->orderTable)
            ->where('gameDate', '>=', $startDay . ' 12:00:00')
            ->where('gameDate', '<', $endDay . ' 12:00:00')
            ->select(\DB::raw("count(0) as betCount,sum(bet) as betAmount, sum(win) as winAmount"))->first();

        //金额正确
        if (bccomp($betAmount, $result->betAmount, 0) == 0 && bccomp($betCount, $result->betCount, 0) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
        }

        //金额不对,重新拉单
        $this->orderByTime($startDay . ' 12:00:00', $endDay . ' 12:00:00');

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('gameDate', '>=', $startDay . ' 12:00:00')
            ->where('gameDate', '<', $endDay . ' 12:00:00')
            ->select(\DB::raw("count(0) as betCount,sum(bet) as betAmount, sum(win) as winAmount"))->first();
        if (!(bccomp($betAmount, $result2->betAmount, 0) == 0
            && bccomp($betCount, $result2->betCount, 0) == 0)
        ) {
            $this->addGameOrderCheckError($this->game_type, time(), $params, $startDay . ' 12:00:00', $endDay . ' 12:00:00', $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
            return false;
        }
        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {

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
        //$startTime = $startTime + 1;
        //每次最大拉取区间24 小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT");

        $fields = [
            'start_time' => date('Y-m-d H:i:s', $startTime),
            'end_time' => date('Y-m-d H:i:s', $endTime),
            'OperatorKey' => 1000,
            'ReportType' => 1,
        ];
        date_default_timezone_set($default_timezone);
        while (1) {
            $res = $this->requestParam('Game/getRoundsInfoByPeriod', $fields);
            if (!$res['responseStatus']) {
                break;
            }
            if ($res['total'] == 0) {
                if ($is_redis) {
                    $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
                }
                break;
            }

            $this->updateOrder($res['page_result']);
            if ($is_redis) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            }
            if ($fields['page'] == $res['last_page']) {
                break;
            }
            //下一页
            $fields['page']++;
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
     * @param array $param 请求参数
     * @return array|string
     */
    public function requestParam(array $param)
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

        $queryString = http_build_query($param, '', '&');
        $url = $config['orderUrl'] . '?' . $queryString;
        $re = Curl::get($url, null, true);
        //echo $url.PHP_EOL;die;
        GameApi::addRequestLog($url, 'VIVO', $param, json_encode($re, JSON_UNESCAPED_UNICODE));
        return $re;
    }


}
