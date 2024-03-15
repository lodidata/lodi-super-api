<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameLogic;
use Logic\Game\GameApi;
use Utils\Curl;

/**
 * Explain: NG 游戏接口
 *
 * OK
 */
class NG extends GameLogic
{
    protected $game_type = 'NG';
    protected $orderTable = 'game_order_ng';

    /**
     * 检查接口状态
     * @return bool
     */
    public function checkStatus()
    {
        return true;
    }

    /**
     * 同步第三方游戏订单（每次最大拉取100条）
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
        $config = $this->initConfigMsg($this->game_type);
        $lobby = json_decode($config['lobby'], true);

        $startTime = strtotime($stime);
        $endTime = strtotime($etime);

        //每次最大拉取区间24小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set('Etc/UTC');

        $param = [
            'startDate' => date("Y-m-d\TH:i:s\Z", $startTime),
            'endDate' => date("Y-m-d\TH:i:s\Z", $endTime),
            'skip' => 0,     //跳过的记录数
            'limit' => 100,  //最大查询数100
            'apiKey' => $lobby['apikey']
        ];
        date_default_timezone_set($default_timezone);

        $res = $this->requestParam('/client/player/bet-histories', $param, false);

        while (1) {
            $this->updateOrder($res['data']);

            $param['skip'] = 100 + $param['skip'];
            if($res['total'] <= $param['skip']) {
                break;
            }

            $res = $this->requestParam('/client/player/bet-histories', $param, false);
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
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->selectRaw("sum(amount) as bet,sum(amount) as valid_bet,sum(earn) as win_loss")
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
            'username' => 'playerNativeId',
            'bet' => 'amount',
            'win' => 'earn',
            'profit' => 'earn-amount',
            'gameDate' => 'created'
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
            ->where('created', '>=', $start_time)
            ->where('created', '<=', $end_time)
            ->where('playerNativeId', 'like', "%$user_prefix%")
            ->selectRaw("id,created,roundId as order_number,amount as bet,amount as valid_bet,earn as win_loss");
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
            date_default_timezone_set('Etc/UTC');
            $created = $this->timeFormat($val['created']);
            $updated = $this->timeFormat($val['updated']);
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'roundId' => (string)$val['id'],
                'tid' => intval(ltrim($val['player']['nativeId'], 'game')),
                'amount' => $val['amount'],
                'earn' => $val['earn'],
                'betSize' => $val['betSize'],
                'betStatus' => $val['betStatus'],
                'channel' => $val['channel'],
                'playerBalance' => $val['playerBalance'],
                'playerNativeId' => $val['player']['nativeId'],
                'gameName' => $val['game']['name'],
                'gameCode' => $val['game']['code'],
                'created' => $created,
                'updated' => $updated
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

        $header = [
            'accept: application/json'
        ];

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

        GameApi::addRequestLog($url, 'NG', $param, $re, isset($re['status']) ? 'status:' . $re['status'] : '');
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