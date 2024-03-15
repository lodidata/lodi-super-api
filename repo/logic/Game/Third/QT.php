<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameLogic;
use Logic\Game\GameApi;
use Utils\Curl;

/**
 * Explain: QT 游戏接口
 *
 * OK
 */
class QT extends GameLogic
{
    protected $game_type = 'QT';
    protected $orderTable = 'game_order_qt';

    /**
     * 玩家身份验证
     * 玩家令牌的默认时效为6小时
     * @return mixed|string
     */
    public function authorize()
    {
        $authtoken = $this->redis->get('game_authorize_qt_super');
        if (empty($authtoken)) {
            $config = $this->initConfigMsg($this->game_type);
            $lobby = json_decode($config['lobby'], true);

            $param = [
                'grant_type' => "password",
                'response_type' => "token",
                'username' => $config['cagent'],
                'password' => $lobby['api_password']
            ];
            $res = $this->requestParam('v1/auth/token', $param, false);
            if ($res['status']) {
                $authtoken = $res['access_token'];
                $this->redis->setex('game_authorize_qt_super', $res['expires_in']/1000, $res['access_token']);
            } else {
                $authtoken = '';
            }
        }
        return $authtoken;
    }

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
        date_default_timezone_set("GMT-8");
        $param = [
            'from' => date("Y-m-d\TH:i:s", $startTime),
            'to' => date("Y-m-d\TH:i:s", $endTime),
            'rangeFilter' => 'COMPLETED',   //查询游戏局基于完成时间
            'status' => 'COMPLETED'
        ];
        date_default_timezone_set($default_timezone);

        $header[] = 'Authorization: Bearer ' . $this->authorize();
        $res = $this->requestParam('v1/game-rounds', $param, false, $header);

        while(1){
            if (empty($res['items'])) {
                break;
            }
            $this->updateOrder($res['items']);

            if (!isset($res['links'][0]['href'])) {
                break;
            }
            $res = $this->requestParam(ltrim($res['links'][0]['href'], '/api'), [], false, $header);
        }

        if ($is_redis) {
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
        }
        return true;
    }

    /**
     * 订单校验
     * @return bool
     */
    public function synchronousCheckData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = strtotime(date('Y-m-d H:00:00', $now - 86400)); //取1天前的数据
        }

        //校验3次不通过则跳过
        $check_count = $this->redis->incr(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type);
        if($check_count > 3){
            $startTime = $startTime + 3600;
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startTime);
            $this->redis->set(CacheKey::$perfix['gameOrderCheckCount'] .  $this->game_type, 1);
        }
        
        $endTime = strtotime(date('Y-m-d H:00:00', $now - 7200)); //取2小时前的数据，延迟30分钟
        //每次最大拉取区间1小时
        if ($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("GMT-8");
        $params = [
            'from' => date("Y-m-d\TH:i:s", $startTime),
            'to' => date("Y-m-d\TH:i:s", $endTime)
        ];
        date_default_timezone_set($default_timezone);

        $header[] = 'Authorization: Bearer ' . $this->authorize();
        $res = $this->requestParam('v1/ngr-player', $params,false, $header);

        //接口错误
        if ($res['code']) {
            return false;
        }

        //无数据
        if (!isset($res['code']) && empty($res['items'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        $betAmount = $res['items'][0]['totalBet'];
        $payoutAmount = $res['items'][0]['totalPayout'];

        $result = \DB::table($this->orderTable)
                        ->where('completed', '>=', date('Y-m-d H:i:s', $startTime))
                        ->where('completed', '<=', date('Y-m-d H:i:s', $endTime))
                        ->select(\DB::raw("sum(totalBet) as betAmount, sum(totalPayout) as winAmount"))->first();
        //金额正确
        if (bccomp($betAmount, $result->betAmount, 0) == 0 && bccomp($payoutAmount, $result->winAmount, 0) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        //金额不对,重新拉单
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime));

        //第二次校验
        $result2 = \DB::table($this->orderTable)
                        ->where('completed', '>=', date('Y-m-d H:i:s', $startTime))
                        ->where('completed', '<=', date('Y-m-d H:i:s', $endTime))
                        ->select(\DB::raw("sum(totalBet) as betAmount, sum(totalPayout) as winAmount"))->first();
        if (!(bccomp($betAmount, $result2->betAmount, 0) == 0 && bccomp($payoutAmount, $result2->winAmount, 0) == 0)) {
            $this->addGameOrderCheckError($this->game_type, time(), $params, date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), $betAmount, $payoutAmount, $result2->betAmount, $result2->winAmount);
            return true;
        }

        //金额匹配完全正确
        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
                        ->where('completed', '>=', $start_time)
                        ->where('completed', '<=', $end_time)
                        ->selectRaw("sum(totalBet) as bet,sum(totalBet) as valid_bet,sum(totalPayout) as win_loss")
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
            'username' => 'playerId',
            'bet' => 'totalBet',
            'win' => 'totalPayout',
            'profit' => 'totalPayout-totalBet',
            'gameDate' => 'completed'
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
            ->where('completed', '>=', $start_time)
            ->where('completed', '<=', $end_time)
            ->where('playerId', 'like', "%$user_prefix%")
            ->selectRaw("id,completed,gameProviderRoundId as order_number,totalBet as bet,totalBet as valid_bet,totalPayout as win_loss");
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
     * @param int $gameId
     * @return bool
     */
    public function updateOrder($data)
    {
        $insertData = [];
        $default_timezone = date_default_timezone_get();

        foreach ($data as $val) {
            $gameTypeArr = explode('/', $val['gameCategory']);

            date_default_timezone_set("GMT-8");
            $initiated = $this->timeFormat($val['initiated']);
            $completed = $this->timeFormat($val['completed']);
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'round_id' => $val['id'],
                'tid' => intval(ltrim($val['playerId'], 'game')),
                'status' => $val['status'],
                'totalBet' => $val['totalBet'],
                'totalPayout' => $val['totalPayout'],
                'totalBonusBet' => $val['totalBonusBet'],
                'currency' => $val['currency'],
                'operatorId' => intval($val['operatorId']),
                'playerId' => $val['playerId'],
                'device' => $val['device'],
                'gameProvider' => $val['gameProvider'],
                'gameId' => $val['gameId'],
                'gameCategory' => $gameTypeArr[1],
                'gameClientType' => $val['gameClientType'],
                'gameProviderRoundId' => $val['gameProviderRoundId'],
                'totalJpContribution' => $val['totalJpContribution'],
                'totalJpPayout' => $val['totalJpPayout'],
                'tableId' => $val['tableId'],
                'initiated' => $initiated,
                'completed' => $completed
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
    public function requestParam($action, array $param, bool $is_post = true, $header = [], $status = false)
    {
        $config = $this->initConfigMsg($this->game_type);
        $apiUrl = $config['orderUrl'];

        $url = $apiUrl . $action;

        if ($is_post) {
            $re = Curl::post($url, null, $param, '', null, $header);
        } else {
            if ($param) {
                $queryString = http_build_query($param, '', '&');
                $url .= '?' . $queryString;
            }
            $re = Curl::get($url, null, false, $header);
        }

        GameApi::addRequestLog($url, 'QT', $param, $re, isset($re['status']) ? 'status:' . $re['status']:'');
        $res = json_decode($re, true);

        if ($status) {
            return $res;
        }
        if (!is_array($res)) {
            $res['message'] = $re;
            $res['status'] = false;
        } elseif (isset($res["code"])) {
            $res['status'] = false;
        } else {
            $res['status'] = true;
        }
        return $res;
    }

    //把UTC时间转为标准格式时间
    public function timeFormat($time)
    {
        $time = rtrim($time, '[Asia/Shanghai]');

        return date('Y-m-d H:i:s', strtotime($time));
    }

}