<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class BNG
 */
class BNG extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_bng';
    protected $game_type = 'BNG';

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
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('round', $val['transaction_id'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT");
            $CreateAt = strtotime($val['c_at']);//建立時間
            date_default_timezone_set($default_timezone);
            $val['c_at'] = date('Y-m-d H:i:s', $CreateAt);
            $val['tid'] = intval(ltrim($val['player_id'], 'game'));
            $betAmount = bcmul($val['bet'], 100, 0);
            $winAmount = bcmul($val['win'], 100, 0);
            $insertData[] = [
                'tid' => $val['tid'],
                'round' => $val['transaction_id'],
                'username' => $val['player_id'],
                'gameDate' => $val['c_at'],
                'game_id' => $val['game_id'],
                'game_name' => $val['game_name'],
                'status' => $val['status'],
                'betAmount' => $betAmount,
                'winAmount' => $winAmount,
                'gameplat' => $val['platform'],
                'income' => bcsub($winAmount, $betAmount,0),
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
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type); //上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = $now - 86400; //1天前
        }

        $startTime = strtotime(date('Y-m-d H:00:00', $startTime));

        //校验3次不通过则跳过
        $check_count = $this->redis->incr(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type);
        if($check_count > 3){
            $startTime = $startTime + 3600;
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startTime);
            $this->redis->set(CacheKey::$perfix['gameOrderCheckCount'] .  $this->game_type, 1);
        }

        $endTime = $startTime + 3600;
        //当前小时不拉数据
        if (strtotime(date('Y-m-d H:00:00', $endTime)) >= strtotime(date('Y-m-d H:00:00', $now - 3600))) {
            return true;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("UTC/GMT");

        $params = [
            'start_date' => date('Y-m-d 00:00:00P', $startTime),
            'end_date' => date('Y-m-d H:00:00P', $endTime),
            "brand" => $this->lobby('brand')
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/api/v1/transaction/aggregate/', $params, true);

        if (!$res['responseStatus']) {
            return false;
        }
        if (empty($res['items'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        //总订单数
        $betCount = 0;
        //下注金额
        $betAmount = 0;
        //返还金额 (包含下注金额)
        $winAmount = 0;
        foreach ($res['items'] as $val) {
            $betCount += bcmul($val['bets'], 100, 0);
            $betAmount += bcmul($val['wins'], 100, 0);
            $winAmount += $val['transactions'];
        }
        $result = \DB::table($this->orderTable)
            ->where('gameDate', '>=', date("Y-m-d 00:00:00", $startTime))
            ->where('gameDate', '<=', date("Y-m-d H:00:00", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        //金额正确
        if (bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }
        //订单数不对补单
        $this->orderByTime(date("Y-m-d H:i:s", $startTime), date("Y-m-d H:i:s", $endTime));

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('gameDate', '>=', date("Y-m-d 00:00:00", $startTime))
            ->where('gameDate', '<=', date("Y-m-d H:00:00", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        if (!bccomp($betAmount, $result2->betAmount, 2) == 0 && bccomp($winAmount, $result2->winAmount, 2) == 0) {
            $this->addGameOrderCheckError($this->game_type, $now, $params, date("Y-m-d 00:00:00", $startTime), date("Y-m-d H:00:00", $endTime), $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
            return false;
        }
        //金额匹配完全正确
        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
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
            'username' => 'username',
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
        date_default_timezone_set("Etc/GMT");
        $fields = [
            'start_date' => date("c", $startTime),
            'end_date' => date("c", $endTime),
            "status" => "OK",  // string, 交易状态, 其中之一:("ALL", "OK", "NEW", "LOCKED"), "OK" 为预设值
            "brand" => $this->lobby('brand'),
            'fetch_size' => 1000,
        ];
        date_default_timezone_set($default_timezone);
        while (1){
            $res = $this->requestParam('/api/v1/transaction/list/', $fields, true);
            if (!$res['responseStatus']) {
                return false;
            }
            $this->updateOrder($res['items']);
            if (is_null($res['fetch_state'])) {
                break;
            }
            $fields['fetch_state'] = $res['fetch_state'];
        }
        if($is_redis){
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
     * 发送请求
     * @param $action
     * @param array $param 请求参数
     * @param bool $is_order
     * @return array|string
     */
    public function requestParam($action, array $param, $is_order = false)
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

        $param['api_token'] = $config['key'];
        if($is_order){
            $url = $config['orderUrl'] . $config['cagent'] . $action;
        }else{
            $url = $config['apiUrl'] . $config['cagent'] . '/wallet/'.$this->lobby('WL').'/' . $action;
        }

        $re = Curl::post($url, null, $param, null, true);
        if (isset($re['status']) && $re['status'] == 200) {
            $re['content'] = json_decode($re['content'], true);
        }

        GameApi::addRequestLog($url, $this->game_type, $param, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($re['status'] == 200) {
            $ret = $re['content'];
            $ret['responseStatus'] = true;
        } else {
            if(isset($re['content']) && $this->is_json($re['content'])){
                $re['content'] = json_decode($re['content'], true);
                $ret['error'] = $re['content']['error'];
            }else{
                $ret['error'] = $re['content'];
            }
            $ret['responseStatus'] = false;
        }
        return $ret;
    }


    private function is_json($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    public function lobby($key){
        $config = $this->initConfigMsg($this->game_type);
        $lobby = json_decode($config['lobby'], true);
        return $lobby[$key];
    }
}
