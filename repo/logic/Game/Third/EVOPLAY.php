<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class EVOPLAY
 */
class EVOPLAY extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_evoplay';
    protected $game_type = 'EVOPLAY';

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
        $gameList = $this->redis->get('super_game_evo_3th');
        if(is_null($gameList) || $gameList =="null" || empty($gameList)){
            $game3th = \DB::table('game_3th')->whereIn('game_id', [125,126,127])->get(['kind_id', 'game_id'])->toArray();
            foreach($game3th as $val){
                $val = (array) $val;
                $gameList[$val['kind_id']] = $val['game_id'];
            }
            $this->redis->setex('super_game_evo_3th', 86400, json_encode($gameList));
        }else{
            $gameList = json_decode($gameList, true);
        }

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
                'gameCode' => $val['game_id'],
                'betAmount' => bcmul($val['bet_amount'], 100, 0),
                'winAmount' => bcmul($val['win_amount'], 100, 0),
                'income' => bcmul($val['win_amount']-$val['bet_amount'], 100, 0),
                'game_id' => isset($gameList[$val['game_id']]) ? $gameList[$val['game_id']] : 125
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
            'page_size' => 1000,
            'page' => 1,
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
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @return array|string
     */
    public function requestParam($action, array $param)
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
        $project = $config['cagent'];
        $version = $config['lobby'];
        $param['signature'] = $this->getSignature($project, $version, $param, $config['key']);
        $param['project'] = $project;
        $param['version'] = $version;
        $url = $config['orderUrl'] . '/' . $action;
        //echo $url.PHP_EOL;die;
        $re = Curl::post($url, null, $param, null, true);

        if ($re['status'] != 200) {
            $ret['responseStatus'] = false;
            $ret['netWorkStatus'] = $re['status'];
            $ret['msg'] = $re['content'];
        } else {
            $ret = json_decode($re['content'], true);
            $ret['networkStatus'] = $re['status'];
            if (isset($ret['error']) && !empty($ret['error'])) {
                $ret['responseStatus'] = false;
            } else {
                $ret['responseStatus'] = true;
            }
        }
        GameApi::addRequestLog($url, 'EVOPLAY', $param, json_encode($ret, JSON_UNESCAPED_UNICODE));
        return $ret;
    }



    function getSignature($project_id, $api_version, array $required_args, $secrete_key)
    {
        $md5 = array();
        $md5[] = $project_id;
        $md5[] = $api_version;
        $required_args = array_filter($required_args, function ($val) {
            return !($val === null || (is_array($val) && !$val));
        });

        foreach ($required_args as $required_arg) {
            if (is_array($required_arg)) {
                if (count($required_arg)) {
                    $recursive_arg = '';
                    array_walk_recursive($required_arg, function ($item) use (& $recursive_arg) {
                        if (!is_array($item)) {
                            $recursive_arg .= ($item . ':');
                        }
                    });
                    $md5[] = substr($recursive_arg, 0, strlen($recursive_arg) - 1); // get rid of last colon-sign
                } else {
                    $md5[] = '';
                }
            } else {
                $md5[] = $required_arg;
            }
        };

        $md5[] = $secrete_key;
        $md5_str = implode('*', $md5);
        return md5($md5_str);
    }

}
