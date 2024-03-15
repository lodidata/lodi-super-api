<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class AT
 */
class AT extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_at';
    protected $game_type = 'AT';

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
            $startTime = $now - 1800; //取30分钟内的数据
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
                if (\DB::table($this->orderTable)->where('order_number', $val['id'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT-8");
            $createdAt = strtotime($val['createdAt']);//注單建立時間
            $updatedAt = strtotime($val['updatedAt']); //注單結算時間
            //$endAt = isset($val['endAt']) ? strtotime($val['endAt']) : time();
            date_default_timezone_set($default_timezone);
            $insertData[] = [
                'tid' => intval(ltrim($val['player'], 'game')),
                'order_number' => $val['id'],
                'player' => $val['player'],
                //'playerId' => $val['playerId'] ?? 0,
                //'parent' => $val['parent'] ?? '',
                //'parentId'  => $val['parentId'] ?? 0,
                'bet' => $val['bet'],
                'validBet' => $val['validBet'],
                'result' => $val['result'],
                'createdAt' => date('Y-m-d H:i:s', $createdAt),
                'updatedAt' => date('Y-m-d H:i:s', $updatedAt),
                //'endAt' => date('Y-m-d H:i:s', $endAt),
                'productId' => $val['productId'],
                'setId' => $val['setId'],
                'gameType' => $val['gameType'],
                'currency' => $val['currency'] ?? 'PHP',
                'game' => $val['game'],
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
        if (!$this->checkStatus()) {
            return false;
        }
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
        //每次最大拉取区间1 小时内
        if ($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }
        //当前小时不拉数据
        if (date('Y-m-d H', $now) == date('Y-m-d H', $endTime) || (date('Y-m-d H', $endTime) == date('Y-m-d H', $startTime)) || $endTime > $now) {
            return true;
        }

        $params = [
            'start' => $startTime . '000',
            'end' => $endTime . '000',
        ];

        $res = $this->requestParam('/api/v1/profile/currencyStats', $params, false, true, true);
        if (!$res['responseStatus']) {
            return false;
        }
        if (!isset($res['data']) || empty($res['data'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }
        //总订单数
        $betCount = 0;
        //下注金额
        $betAmount = 0;
        //输赢金额
        $winAmount = 0;
        foreach ($res['data'] as $val) {
            $betAmount += $val['bet'];
            $betCount += $val['totalSize'];
            $winAmount += $val['win'];
        }

        if ($betAmount == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        $result = \DB::table($this->orderTable)
            ->where('createdAt', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('createdAt', '<=', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(bet) as betAmount, sum(win) as winAmount"))->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
                return true;
            }
        }
        //订单数不对补单
        $this->orderByTime(date("Y-m-d H:i:s", $startTime), date("Y-m-d H:i:s", $endTime));

        //第二次校验
        $result = \DB::table($this->orderTable)
            ->where('createdAt', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('createdAt', '<=', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(bet) as betAmount, sum(win) as winAmount"))->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
                return true;
            }
        }
        //订单数不对
        $this->addGameOrderCheckError($this->game_type, $now, $params, date("Y-m-d H:i:s", $startTime), date("Y-m-d H:i:s", $endTime), $betAmount, $winAmount, $result->betAmount, $result->winAmount);

        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('createdAt', '>=', $start_time)
            ->where('createdAt', '<=', $end_time)
            ->selectRaw("sum(bet) as bet,sum(validBet) as valid_bet,sum(win) as win_loss")
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
            'username' => 'player',
            'bet' => 'bet',
            'win' => 'win',
            'profit' => 'result',
            'gameDate' => 'createdAt'
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
            ->where('createdAt', '>=', $start_time)
            ->where('createdAt', '<=', $end_time)
            ->where('player', 'like', "%$user_prefix%")
            ->selectRaw("id,createdAt,order_number,bet,validBet as valid_bet,win as win_loss");
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
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //拉单去重，开始时间+1秒
        $startTime = $startTime + 1;
        //每次最大拉取区间24 小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT");

        $fields = [
            'isChildren' => false,
            'status' => 'finish', //該局狀態 playing, finish, cancel, finish 為完成狀態
            'pageSize' => 10000, //單頁顯示筆數，預設 10 筆，單次最大查詢 10,000 筆
            'lang' => 'en',
            'updatedStart' => $startTime . '000', //注單更新起始時間，時區 +0, 需補到毫秒 Ex. 1566230400000
            'updatedEnd' => $endTime . '000',
            'page' => 1,
        ];
        date_default_timezone_set($default_timezone);
        while (1) {
            $res = $this->requestParam('/api/v1/profile/rounds', $fields, false, true, true);
            if (!$res['responseStatus']) {
                break;
            }
            if ($res['pageSize'] == 0) {
                if ($is_redis) {
                    $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
                }
                break;
            }

            $this->updateOrder($res['data']);
            if ($is_redis) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            }
            if ($fields['page'] * $fields['pageSize'] >= $res['totalSize']) {
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
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @param bool $is_post 是否为post请求
     * @param bool $is_header 是否带头部信息
     * @param bool $is_order 是否为获取注单
     * @return array|string
     */
    public function requestParam(string $action, array $param, bool $is_post = true, $is_header = true, $is_order = false)
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
        $url = rtrim($is_order ? $config['orderUrl'] : $config['apiUrl'], '/') . $action;
        $headers = [];
        if ($is_header) {
            $token = $this->getJWTToken();
            if (!$token) {
                return [
                    'responseStatus' => false,
                    'msg' => 'get jwt token error'
                ];
            }

            $headers = array(
                "Authorization: Bearer " . $token
            );
        }
        if ($is_post) {
            $re = Curl::post($url, null, $param, null, true, $headers);
        } else {
            $queryString = http_build_query($param, '', '&');
            if ($queryString) {
                $url .= '?' . $queryString;
            }
            $re = Curl::get($url, null, true, $headers);
        }
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

    /**
     * 取得密鑰 Get JWT token
     */
    public function getJWTToken()
    {
        $config = $this->initConfigMsg($this->game_type);
        $jwtToken = $this->redis->get('game_authorize_at');
        if (is_null($jwtToken)) {
            $fields = [
                'username' => $config['cagent'],
                'password' => $config['key']
            ];
            $res = $this->requestParam('/login', $fields, true, false);
            if ($res['responseStatus']) {
                $jwtToken = $res['token'];
                $this->redis->setex('game_authorize_at', 86400, $res['token']);
            }
        }
        return $jwtToken;
    }
}
