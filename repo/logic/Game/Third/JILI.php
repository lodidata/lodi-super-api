<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;
use function GuzzleHttp\Psr7\str;

/**
 * Explain: JILI 游戏接口  电子，捕鱼游戏
 *
 * OK
 */
class JILI extends GameLogic
{

    protected $game_type = 'JILI';
    protected $orderTable = 'game_order_jili';

    /**
     * 同步订单
     * ncg延期两小时，最大时间间隔不能超过5分钟，2022-07-28 改成 lodi一样
     * lodi 延期10分钟，最大时间间隔不能超过60分钟
     * @return bool
     */
    public function synchronousData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            //为了防止redis里的时间错误,每次都格式化整10分钟
            $startTime = strtotime(date('Y-m-d H:i:00', $r_time));
            $startTime += 1; //去掉最后一秒拉单重复
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('gameDate');
            $startTime = $last_datetime ? strtotime($last_datetime) : strtotime(date('Y-m-d H:i:00', $now)) - 720; //只能12分钟前的数据
        }

        $lastTime = strtotime(date('Y-m-d H:i:00', $now - 600));
        //接数据时间间隔不能超过60分钟
        $endTime = $startTime + 3599;
        if ($endTime > $lastTime) {
            $endTime = $lastTime;
        }

        if($startTime > $endTime || $endTime > $now){
            return true;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $fields = [
            'StartTime' => date("Y-m-d\TH:i:s", $startTime),
            'EndTime' => date("Y-m-d\TH:i:s", $endTime),
            'Page' => 1,
            'PageLimit' => 10000,
        ];
        date_default_timezone_set($default_timezone);
        $page = 1;
        while (1) {
            $fields['Page'] = $page;
            $res = $this->requestParam('GetBetRecordByTime', $fields);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            if ($res['ErrorCode']) {
                break;
            }

            if (!empty($res['Data']['Result'])) {
                $this->updateOrder($res['Data']['Result']);
            }
            if ($res['Data']['Pagination']['TotalPages'] <= $page) {
                break;
            }
            $page++;
        }
        $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
    }

    /**
     * 订单校验
     * 校验前一天的订单金额，正常拉单延期2小时，所以校验在2小时后
     * @return bool
     */
    public function synchronousCheckData()
    {
        $now = time();
        //当前小时是否跑过数据

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

        $endTime = strtotime(date('Y-m-d H:00:00', $now - 10800)); //取3小时前的数据
        //每次最大拉取区间1 小时内
        if ($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }
        //当前小时不拉数据,延期2小时
        if (date('Y-m-d H', $endTime) <= date('Y-m-d H', $startTime) || $endTime > $now || (date('Y-m-d H:20', $endTime) > date('Y-m-d H:i', $startTime))) {
            return true;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $params = [
            'StartTime' => date("Y-m-d\TH:i:s", $startTime),
            'EndTime' => date("Y-m-d\TH:i:s", $endTime),
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('GetBetRecordSummary', $params);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        //请求失败
        if ($res['ErrorCode']) {
            return false;
        }
        //无数据
        if (!isset($res['Data']) || empty($res['Data'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        $betAmount = bcmul($res['data']['BetAmount'], 100, 0);
        $winAmount = bcmul($res['data']['PayoffAmount'], 100, 0);
        $numCount = $res['data']['WagersCount'];
        $result = \DB::table($this->orderTable)
            ->where('gameDate', '>=', date('Y-m-d H:i:s', $startTime))
            ->where('gameDate', '<=', date('Y-m-d H:i:s', $endTime))
            ->select(\DB::raw("sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        //金额正确
        if (bccomp($betAmount, $result->betAmount, 0) == 0 && bccomp($winAmount, $result->winAmount, 0) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        //金额不对,重新拉单
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime));

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('gameDate', '>=', date('Y-m-d H:i:s', $startTime))
            ->where('gameDate', '<=', date('Y-m-d H:i:s', $endTime))
            ->select(\DB::raw("sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        if (!(bccomp($betAmount, $result2->betAmount, 0) == 0 && bccomp($winAmount, $result2->winAmount, 0) == 0)) {
            $this->addGameOrderCheckError($this->game_type, time(), $params, date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
            return true;
        }

        //金额匹配完全正确
        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
        return true;
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
            ->selectRaw("id,gameDate,OCode as order_number,betAmount as bet,betAmount as valid_bet,winAmount as win_loss");
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
     * 6.2.按分钟检索事务
     * @param $stime
     * @param $etime
     * @return bool
     */
    public function orderByTime($stime, $etime)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $fields = [
            'StartTime' => date("Y-m-d\TH:i:s", $startTime),
            'EndTime' => date("Y-m-d\TH:i:s", $endTime),
            'Page' => 1,
            'PageLimit' => 10000,
        ];
        date_default_timezone_set($default_timezone);
        $page = 1;
        while (1) {
            $fields['Page'] = $page;
            $res = $this->requestParam('GetBetRecordByTime', $fields);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            if ($res['ErrorCode']) {
                break;
            }

            if (!empty($res['Data']['Result'])) {
                $this->updateOrder($res['Data']['Result']);
            }
            if ($res['Data']['Pagination']['TotalPages'] <= $page) {
                break;
            }
            $page++;
        }
        return true;
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
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('OCode', (string)$val['WagersId'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT+4");
            $WagersTime = strtotime($val['WagersTime']);
            $PayoffTime = strtotime($val['PayoffTime']);
            $SettlementTime = strtotime($val['SettlementTime']);
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid' => intval(ltrim($val['Account'], 'game')),
                'OCode' => (string)$val['WagersId'],
                'Username' => $val['Account'],
                'gameDate' => date('Y-m-d H:i:s', $WagersTime),
                'gameCode' => $val['GameId'],
                'betAmount' => abs(bcmul($val['BetAmount'], 100, 0)), //返回负数
                'winAmount' => bcmul($val['PayoffAmount'], 100, 0),
                'income' => bcmul($val['PayoffAmount'] + $val['BetAmount'], 100, 0),
                'Type' => $val['Type'],
                'PayoffTime' => date('Y-m-d H:i:s', $PayoffTime),
                'Status' => $val['Status'],
                'SettlementTime' => date('Y-m-d H:i:s', $SettlementTime),
                'GameCategoryId' => $val['GameCategoryId'],
            ];
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @return array|string
     */
    public function requestParam(string $action, array $param)
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
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        //日: 一位数或两位数, 1~9 前面请不要补 0
        $keyG = md5(date('ymj') . $config['cagent'] . $config['key']);
        date_default_timezone_set($default_timezone);
        $querystring = urldecode(http_build_query($param, '', '&'));
        $querystring .= '&AgentId=' . $config['cagent'];
        $md5string = md5($querystring . $keyG);
        $key = str_random(6) . $md5string . str_random(6);
        $querystring .= '&FilterAgent=1';
        $url = $config['orderUrl'] . $action . '?' . $querystring . '&Key=' . $key;
        //echo $url;die;
        $re = Curl::get($url, null, true);
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

}
