<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;
use function GuzzleHttp\Psr7\str;

/**
 * Explain: LD集成
 *
 */
class LDAPI extends GameLogic
{

    protected $game_type = 'LD';
    protected $orderTable = 'game_order_common';

    /**
     * 同步订单
     * @return bool
     */
    public function synchronousData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            // 上次拉单结束时间超过当前时间，从当前时间往前10分钟开始拉单
            if ($r_time >= $now) {
                $startTime = $now - 900;
            } else {
                $startTime = $r_time;
            }
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('betEndTime');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 900;
        }

        $endTime = $now-600;
        if($startTime>=$endTime){
            return false;
        }
        //最大拉单时间为5分钟
        if($endTime - $startTime > 300){
            $endTime = $startTime + 300;
        }

        if ($startTime > $endTime || $endTime > $now) {
            return true;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $fields = [
            'startTime' => date("Y-m-d H:i:s", $startTime),
            'endTime'   => date("Y-m-d H:i:s", $endTime),
            'pageLimit' => 10000,
        ];
        date_default_timezone_set($default_timezone);
        $page = 1;
        while (1) {
            $fields['page'] = $page;
            $res = $this->requestParam('historyList', $fields);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            if ($res['state'] != 0) {
                break;
            }

            if (!empty($res['data']['Result'])) {
                $this->updateOrder($res['data']['Result']);
            }
            if ($res['data']['Pagination']['totalPages'] <= $page) {
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
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('betEndTime', '>=', $start_time)
            ->where('betEndTime', '<=', $end_time)
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
            'username' => 'userId',
            'bet'      => 'betAmount',
            'win'      => 'winAmount',
            'profit'   => 'profit',
            'gameDate' => 'betEndTime'
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
            ->where('betEndTime', '>=', $start_time)
            ->where('betEndTime', '<=', $end_time)
            ->where('userId', 'like', "%$user_prefix%")
            ->selectRaw("id,betEndTime,orderNumber as order_number,betAmount as bet,betAmount as valid_bet,winAmount as win_loss");
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
    public function orderByTime($stime, $etime, $is_redis =false)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $fields = [
            'startTime' => date("Y-m-d H:i:s", $startTime),
            'endTime'   => date("Y-m-d H:i:s", $endTime),
            'pageLimit' => 10000,
        ];
        date_default_timezone_set($default_timezone);
        $page = 1;
        while (1) {
            $fields['page'] = $page;
            $res = $this->requestParam('historyList', $fields);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            if ($res['state'] != 0) {
                break;
            }

            if (!empty($res['data']['Result'])) {
                $this->updateOrder($res['data']['Result']);
            }
            if ($res['data']['Pagination']['totalPages'] <= $page) {
                break;
            }
            $page++;
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
                if (\DB::table($this->orderTable)->where('orderNumber', (string)$val['orderNumber'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT-8");
            $betTime = strtotime($val['betTime']);
            $betEndTime = strtotime($val['betEndTime']);
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid'         => intval(ltrim($val['userId'], 'game')),
                'orderNumber' => (string)$val['orderNumber'],
                'userId'      => $val['userId'],
                'gameCode'    => $val['gameCode'],
                'gameType'    => $val['gameType'],
                'betTime'     => date('Y-m-d H:i:s', $betTime),
                'betEndTime'  => date('Y-m-d H:i:s', $betEndTime),
                'betAmount'   => $val['betAmount'],
                'winAmount'   => $val['winAmount'],
                'profit'      => $val['profit'],
                'currency'    => $val['currency']
            ];
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    public function sign($data)
    {
        $config = $this->initConfigMsg($this->game_type);
        if (isset($data['sign'])) {
            unset($data['sign']);
        }
        ksort($data);
        $signString = urldecode(http_build_query($data, '', '&'));
        $iv = substr(strrev($config['key']), 0, 16);
        return openssl_encrypt($signString, "AES-256-CBC", $config['key'], 0, $iv);
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
                'message'        => 'api not config'
            ];
            GameApi::addElkLog($ret, $this->game_type);
            return $ret;
        }

        $url = $config['orderUrl'] . '/api/' . $action;

        $param['agentCode'] = $config['cagent'];
        $param['timestamp'] = time();
        $param['gamePlatform'] = $config['lobby'];
        $param['sign'] = $this->sign($param);

        $queryString = http_build_query($param, '', '&');

        $re = Curl::commonPost($url, null, $queryString, [], true);
        if ($re['status'] == 200) {
            $re['content'] = json_decode($re['content'], true);
        }
        GameApi::addRequestLog($url, $this->game_type, ['param' => $param], json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ret = [];
        if ($re['status'] == 200) {
            $ret = $re['content'];
            if (isset($ret['state']) && ($ret['state'] == 0)) {
                $ret['responseStatus'] = true;
            } else {
                $ret['responseStatus'] = false;
            }
        } else {
            $ret['responseStatus'] = false;
            $ret['message'] = $re['content'];
        }
        return $ret;
    }

}
