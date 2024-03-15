<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * SGMK 游戏接口  电子，捕鱼，街机，桌游
 * Class SGMK
 * @package Logic\Game\Third
 */
class SGMK extends GameLogic
{
    protected $game_type = 'SGMK';
    protected $orderTable = 'game_order_sgmk';

    /**
     * 注单是以游戏派奖时间为准；拉取当前时间 3 分钟之前 数据；
     * 建议拉取区间为 1-5 分钟，最大不能超过 1 天
     *
     * @throws \Exception
     */
    public function synchronousData()
    {
        $now = time() - 180;
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('ticketTime');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 2 * 60; //取2分钟前的数据
        }
        $endTime = $now;
        //接数据时间间隔不能超过1天
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $page = 1;
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        while (1) {
            $param = [
                'beginDate' => date('Ymd\THis', $startTime),
                'endDate' => date('Ymd\THis', $endTime),
                'pageIndex' => $page,
                'serialNo' => $this->serialNo(),
            ];
            date_default_timezone_set($default_timezone);
            $res = $this->requestParam('getBetHistory', $param, false, true);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            //接口内容报错
            if ($res['code']) {
                break;
            }
            if ($res['resultCount'] == 0) { // 未有任何订单数据
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
                break;
            }
            $this->updateOrder($res['list']);
            if ($res['pageCount'] <= $page) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
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
        $now = time() - 43200; // 12 小时前
        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = strtotime(date('Y-m-d H:00:00', $now - 3600)); //取1小内的数据
        }
        $endTime = strtotime(date('Y-m-d H:00:00', $now)); //取1小时前的数据


        //1. 拉取区间最大不能超过 1 天
        //2. 拉取数据时间必须是当前时间 12 小时前
        //3. 数据是以小时查询
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $page = 1;
        $params = [
            'beginDate' => date('Ymd\THis', $startTime),
            'endDate' => date('Ymd\THis', $endTime),
            'pageIndex' => $page,
        ];
        date_default_timezone_set($default_timezone);
        //总订单数
        $betCount = 0;
        //下注金额
        $betAmount = 0;
        //返还金额 (包含下注金额)
        $winAmount = 0;
        while (1) {
            $res = $this->requestParam('playerDailySumByGame', $params);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            //接口内容报错
            if ($res['code']) {
                break;
            }
            if ($res['resultCount'] > 0) {
                foreach ($res['list'] as $val) {
                    foreach ($val['list'] as $val2) {
                        $betAmount = bcadd($betAmount, $val2['betAmount'], 2);
                        $winAmount = bcadd($winAmount, $val2['winLoss'], 2);
                    }
                }
            }

            if ($res['pageCount'] <= $page) {
                break;
            }
            $page++;
        }
        if ($betAmount == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startTime);
            return true;
        }

        $result = \DB::table($this->orderTable)
            ->where('ticketTime', '>=', date("Y-m-d H:00:00", $startTime))
            ->where('ticketTime', '<=', date("Y-m-d H:00:00", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(betAmount) as betAmount, sum(winLoss) as winAmount"))->first();
        //金额正确
        if (bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }
        //订单数不对补单 重新同步
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime));

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('ticketTime', '>=', date("Y-m-d H:00:00", $startTime))
            ->where('ticketTime', '<=', date("Y-m-d H:00:00", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(betAmount) as betAmount, sum(winLoss) as winAmount"))->first();
        //金额不正确
        if (!(bccomp($betAmount, $result2->betAmount, 2) == 0 && bccomp($winAmount, $result2->winAmount, 2) == 0)) {
            $this->addGameOrderCheckError($this->game_type,time(), $params, date("Y-m-d H:00:00", $startTime), date("Y-m-d H:00:00", $endTime), $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
            return false;
        }

        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startTime);
        return true;
    }

    public function updateOrder($data, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $key => $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('ticketId', $val['ticketId'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT-8");
            $ticketTime = strtotime($val['ticketTime']); //结算时间
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid' => intval(ltrim($val['acctId'], 'GAME')),
                'ticketId' => $val['ticketId'],
                'acctId' => strtolower($val['acctId']),
                'betAmount' => abs($val['betAmount']),
                'winLoss' => $val['winLoss'],
                'ticketTime' => date('Y-m-d H:m:i', $ticketTime),
                'gameCode' => $val['gameCode'],
                'roundId' => $val['roundId'] ?? '0',
                'categoryId' => $val['categoryId'],
                'currency' => $val['currency'] ?? 'PHP',
            ];
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('ticketTime', '>=', $start_time)
            ->where('ticketTime', '<=', $end_time)
            ->selectRaw("sum(betAmount) as bet,sum(betAmount) as valid_bet,sum(winLoss) as win_loss")
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
            'username' => 'acctId',
            'bet' => 'betAmount',
            'win' => 'winLoss+betAmount',
            'profit' => 'winLoss',
            'gameDate' => 'ticketTime'
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
            ->where('ticketTime', '>=', $start_time)
            ->where('ticketTime', '<=', $end_time)
            ->where('acctId', 'like', "%$user_prefix%")
            ->selectRaw("id,ticketTime,ticketId as order_number,betAmount as bet,betAmount as valid_bet,winLoss as win_loss");
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
     */
    public function orderByTime($stime, $etime)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //接数据时间间隔不能超过1天
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $page = 1;
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        while (1) {
            $param = [
                'beginDate' => date('Ymd\THis', $startTime),
                'endDate' => date('Ymd\THis', $endTime),
                'pageIndex' => $page,
                'serialNo' => $this->serialNo(),
            ];
            date_default_timezone_set($default_timezone);
            $res = $this->requestParam('getBetHistory', $param, false, true);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            //接口内容报错
            if ($res['code']) {
                break;
            }
            if ($res['resultCount'] == 0) { // 未有任何订单数据
                break;
            }
            $this->updateOrder($res['list']);
            if ($res['pageCount'] <= $page) {
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
        $this->orderByTime($stime, $etime);
    }


    /**
     * 发送请求
     * @param string $action
     * @param array $param 请求参数
     * @param bool $is_post 是否为post请求
     * @param bool $is_order 是否请求订单接口
     * @return array|string
     */
    public function requestParam($action, array $param, bool $is_post = true, $is_order = false)
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
        $apiUrl = $is_order ? $config['orderUrl'] : $config['apiUrl'];
        $header = [
            'API:' . $action,
            'DataType:JSON',
            'Accept-Encoding:th_TH',
        ];
        $param['merchantCode'] = $config['cagent'];
        $re = Curl::post($apiUrl, null, $param, null, true, $header);
        $param['API'] = $action;
        if ($re['status'] == 200) {
            $re['content'] = json_decode($re['content'], true);
        }
        GameApi::addRequestLog($apiUrl, $this->game_type, $param, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
     * 生成随机号
     * @return mixed
     */
    public function serialNo()
    {
        return str_replace('.', '', sprintf('%.6f', microtime(TRUE)));
    }
}
