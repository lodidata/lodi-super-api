<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Logic\Logic;
use Utils\Client;
use DB;
use Utils\Curl;

class CQ9 extends GameLogic
{

    protected $game_type = 'CQ9';
    protected $orderTable = 'game_order_cq9';

    public function synchronousData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            //为了防止redis里的时间错误,每次都格式化整10分钟
            $startTime = strtotime(date('Y-m-d H:i:00', $r_time));
        } else {
            $startTime = strtotime(date('Y-m-d H:i:00', $now)) - 120; //只能取两小时两分钟前的数据
        }
        $endTime = $now;
        //接数据时间间隔不能超过24小时
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $fields = [
            'starttime' => date("c", $startTime),
            'endtime' => date("c", $endTime),
            'page' => 1,
            'pagesize' => 500,
        ];
        date_default_timezone_set($default_timezone);
        $page = 1;
        while (1) {
            $fields['page'] = $page;
            //var_dump($fields);
            $res = $this->request($fields, 'gameboy/order/view', false);
            //var_dump($res);
            //接口错误
            if (!$res['responseStatus']) {
                break;
            }
            if ($res['status']['code'] > 0 && $res['status']['code'] != 8) {
                break;
            }
            if ($res['status']['code'] == 8 || !isset($res['data']['TotalSize']) || $res['data']['TotalSize'] == 0 || empty($res['data']['Data'])) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
                break;
            }

            $this->updateOrder($res['data']['Data']);

            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            if ($res['data']['TotalSize'] <= $page * $fields['pagesize']) {
                break;
            }
            $page++;
        }
    }

    /**
     * 订单校验
     * @return bool
     * @throws \Exception
     */
    public function synchronousCheckData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('endroundtime');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 86400; //1天前
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
        if (date('Y-m-d H', $now) == date('Y-m-d H', $endTime) || (date('Y-m-d H', $endTime) == date('Y-m-d H', $startTime)) || $endTime > $now) {
            return true;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $params = [
            'starttime' => date("Y-m-d\TH:00:00P", $startTime),
            'endtime' => date("Y-m-d\TH:59:59P", $startTime),
            'groupby' => 'hour',
            'page' => 1,
            'pagesize' => 2000,
            'gametype' => 'slot'
        ];
        $gameTypes = [
            'slot',
            'fish',
            'arcade',
            'table',
        ];
        date_default_timezone_set($default_timezone);
        foreach ($gameTypes as $type) {
            $params['gametype'] = $type;
            //var_dump($params);
            $res = $this->request($params, 'gameboy/report/ss', false);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            //var_dump($res);die;
            if ($res['status']['code'] > 0) {
                return false;
            }
            //无数据
            if ($res['status']['code'] == 0 && $res['data']['totalsize'] == 0) {
                continue;
            }

            //下注金额
            $betAmount = 0;
            //返还金额 (包含下注金额)
            $winAmount = 0;
            foreach ($res['data']['data'] as $val) {
                $betAmount += $val['bets'];
                $winAmount += $val['wins'];
            }
            $betAmount = bcmul($betAmount, 100, 0);
            $winAmount = bcmul($winAmount, 100, 0);
            //echo $betAmount . '---' . $winAmount . PHP_EOL;
            $result = \DB::table($this->orderTable)
                ->where('gametype', $type)
                ->where('createtime', '>=', date("Y-m-d H:00:00", $startTime))
                ->where('createtime', '<=', date('Y-m-d H:59:59', $startTime))
                ->select(\DB::raw("count(0) as betCount,sum(bet) as betAmount, sum(win) as winAmount"))->first();
            if (bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                continue;
            }

            //金额不对,重新拉单
            $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime));

            //第二次校验
            $result2 = \DB::table($this->orderTable)
                ->where('gametype', $type)
                ->where('createtime', '>=', date("Y-m-d H:00:00", $startTime))
                ->where('createtime', '<=', date('Y-m-d H:59:59', $startTime))
                ->select(\DB::raw("count(0) as betCount,sum(bet) as betAmount, sum(win) as winAmount"))->first();
            if (!bccomp($betAmount, $result2->betAmount, 2) == 0 && bccomp($winAmount, $result2->winAmount, 2) == 0) {
                $this->addGameOrderCheckError($this->game_type, $now, $params, date("Y-m-d H:00:00", $startTime), date('Y-m-d H:59:59', $startTime), $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
                return false;
            }

        }

        //处理完成
        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
        return true;
    }

    public function updateOrder($data, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            if ($val['status'] != 'complete') {
                continue;
            }
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('round', $val['round'])->count()) {
                    continue;
                }
            }
           /* $val['bet'] = bcmul($val['bet'], 100, 0);
            $val['win'] = bcmul($val['win'], 100, 0);
            $val['validbet'] = bcmul($val['validbet'], 100, 0);
            $val['jackpot'] = bcmul($val['jackpot'], 100, 0);
            $val['jackpotcontribution'] = json_encode($val['jackpotcontribution']);
            $val['detail'] = json_encode($val['detail']);
            $val['gameresult'] = json_encode($val['gameresult']);*/

            date_default_timezone_set("Etc/GMT+4");
            $bettime = strtotime($val['bettime']);
            $endroundtime = strtotime($val['endroundtime']);
            $createtime = strtotime($val['createtime']);
            date_default_timezone_set($default_timezone);
            /*$val['bettime'] = date('Y-m-d H:i:s', $bettime);
            $val['endroundtime'] = date('Y-m-d H:i:s', $endroundtime);
            $val['createtime'] = date('Y-m-d H:i:s', $createtime);
            $val['tid'] = intval(ltrim($val['account'], 'game'));
            $val['bettype'] = json_encode($val['bettype']);*/

            $insertData[] = [
                'tid' => intval(ltrim($val['account'], 'game')),
                'round' => $val['round'],
                'account' => $val['account'],
                'bettime' => date('Y-m-d H:i:s', $bettime),
                'endroundtime' => date('Y-m-d H:i:s', $endroundtime),
                'createtime' => date('Y-m-d H:i:s', $createtime),
                'tableid' => $val['tableid'],
                'gamecode' => $val['gamecode'],
                'gametype' => $val['gametype'],
                 'bet' => bcmul($val['bet'], 100, 0),
                'win' => bcmul($val['win'], 100, 0),
                'validbet' => bcmul($val['validbet'], 100, 0),
                'jackpot' => bcmul($val['jackpot'], 100, 0),
                'currency' => $val['currency'] ?? 'PHP',
            ];
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('createtime', '>=', $start_time)
            ->where('createtime', '<=', $end_time)
            ->selectRaw("sum(bet) as bet,sum(bet) as valid_bet,sum(win) as win_loss")
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
            'username' => 'account',
            'bet' => 'bet',
            'win' => 'win',
            'profit' => 'win-bet',
            'gameDate' => 'createtime'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->game_type, $data);
    }

    public function queryHotOrder($user_prefix, $stime, $end, $config, $args = [])
    {

        return [];
    }

    public function queryLocalOrder($user_prefix, $stime, $end, $page = 1, $page_size = 500)
    {

        $start_time = date(DATE_ATOM, strtotime($stime));
        $end_time = date(DATE_ATOM, strtotime($end));

        $query = \DB::table($this->orderTable)
            ->where('createtime', '>=', $start_time)
            ->where('createtime', '<=', $end_time)
            ->where('account', 'like', "%$user_prefix%")
            ->selectRaw("id,createtime,round as order_number,bet,bet as valid_bet,(win) as win_loss");
        $total = $query->count();

        $result = $query->orderBy('id')->forPage($page, $page_size)->get()->toArray();
        $attributes['total'] = $total;
        $attributes['number'] = $page;
        $attributes['size'] = $page_size;
        if (!$attributes['total'])
            return [];

        return $this->lang->set(0, [], $result, $attributes);
    }

    public function orderByTime($stime, $etime)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $fields = [
            'starttime' => date("c", $startTime),
            'endtime' => date("c", $endTime),
            'page' => 1,
            'pagesize' => 500,
        ];
        date_default_timezone_set($default_timezone);
        $page = 1;
        while (1) {
            $fields['page'] = $page;
            //var_dump($fields);
            $res = $this->request($fields, 'gameboy/order/view', false);
            //var_dump($res);
            //接口错误
            if (!$res['responseStatus']) {
                break;
            }
            if ($res['status']['code'] > 0 && $res['status']['code'] != 8) {
                break;
            }
            if ($res['status']['code'] == 8 || !isset($res['data']['TotalSize']) || $res['data']['TotalSize'] == 0 || empty($res['data']['Data'])) {
                break;
            }

            $this->updateOrder($res['data']['Data']);

            if ($res['data']['TotalSize'] <= $page * $fields['pagesize']) {
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
     * @return bool
     */
    public function orderByHour($stime, $etime)
    {
        return $this->orderByTime($stime, $etime);
    }

    public function request(array $param, $action = '', $is_post = true)
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

        $headers = [
            "Authorization: " . $config['key'],
        ];
        if ($is_post) {
            $headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
        }

        $url = $config['apiUrl'];
        if ($action) {
            $url .= '/' . $action;
        }

        $queryString = urldecode(http_build_query($param, '', '&'));

        if ($is_post) {
            $re = Curl::commonPost($url, null, $queryString, $headers, true);
        } else {
            $url .= '?' . $queryString;
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

}