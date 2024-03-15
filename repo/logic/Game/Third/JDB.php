<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Logic;
use Logic\Game\GameLogic;
use Utils\Curl;

class JDB extends GameLogic
{
    public $game_type = 'JDB';
    protected $orderTable = 'game_order_jdb';

    /**
     * 开始时间与结束时间中，ss(秒数)的值必须为 00
     * 提供超过 2 小时至 60 天内交易信息。
     *
     * @throws \Exception
     */
    public function synchronousData()
    {
        $now = time() - 3 * 60;
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('gameDate');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 2 * 60; //延期3分钟，取2分钟前的数据
        }
        $endTime = $now;
        if ($startTime > $endTime) {
            return false;
        }
        //超过2小时接口
        if ($endTime - $startTime > 7200) {
            $action = 64;
            //每次查询时间范围最多为 5 分钟。
            if ($endTime - $startTime > 300) {
                $endTime = $startTime + 300;
            }
        } else {
            $action = 29;
            //每次查询时间范围最多为 15 分钟。
            if ($endTime - $startTime > 900) {
                $endTime = $startTime + 900;
            }
        }

        $config = $this->initConfigMsg($this->game_type);
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $stime = date('d-m-Y H:i:00', $startTime);
        $etime = date('d-m-Y H:i:00', $endTime);
        $param = [
            'action' => $action,
            'ts' => (int)(microtime(true) * 1000),
            'parent' => $config['cagent'],
            'starttime' => $stime,
            'endtime' => $etime,
        ];
        date_default_timezone_set($default_timezone);

        $res = $this->requestParam($param);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        if ($res['status'] != '0000') {
            return false;
        }

        if (!isset($res['data']) || count($res['data']) <= 0) { // 未有任何订单数据
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            return true;
        }

        //更新注单
        $this->updateOrder($res['data']);

        $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
    }

    /**
     * 更新注单
     * @param $data
     * @param int $updateStatus
     * @return bool
     */
    public function updateOrder($data, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $key => $val) {
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('seqNo', $val['seqNo'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT+4");
            $gameDate = strtotime($val['gameDate']);
            $lastModifyTime = strtotime($val['lastModifyTime']);
            date_default_timezone_set($default_timezone);
           /* $val['gameDate'] = date('Y-m-d H:i:s', $gameDate);
            $val['lastModifyTime'] = date('Y-m-d H:i:s', $lastModifyTime);
            $val['bet'] = abs(bcmul($val['bet'], 100, 0));
            $val['win'] = bcmul($val['win'], 100, 0);
            $val['total'] = bcmul($val['total'], 100, 0);
            $val['denom'] = isset($val['denom']) ? bcmul($val['denom'], 100, 0) : 0;
            $val['jackpot'] = isset($val['jackpot']) ? bcmul($val['jackpot'], 100, 0) : 0;
            $val['jackpotContribute'] = isset($val['jackpotContribute']) ? bcmul($val['jackpotContribute'], 100, 0) : 0;
            $val['gambleBet'] = isset($val['gambleBet']) ? bcmul($val['gambleBet'], 100, 0) : 0;
            $val['beforeBalance'] = isset($val['beforeBalance']) ? bcmul($val['beforeBalance'], 100, 0) : 0;
            $val['afterBalance'] = isset($val['afterBalance']) ? bcmul($val['afterBalance'], 100, 0) : 0;
            $val['tid'] = intval($val['playerId']);*/

            $insertData[] = [
                'tid' => intval($val['playerId']),
                'seqNo' => $val['seqNo'],
                'playerId' => $val['playerId'],
                'bet' => abs(bcmul($val['bet'], 100, 0)),
                'win' => bcmul($val['win']+$val['jackpot'], 100, 0),
                'total' => bcmul($val['total']+$val['jackpot'], 100, 0),
                'gameDate' => date('Y-m-d H:i:s', $gameDate),
                'lastModifyTime' => date('Y-m-d H:i:s', $lastModifyTime),
                'roundSeqNo' => $val['roundSeqNo'],
                'gType' => $val['gType']==9 ? 12 : $val['gType'],
                'mtype' => $val['mtype'],
                'currency' => $val['currency'] ?? 'PP',
                'gameName' => $val['gameName'] ?? ''
            ];
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 订单校验
     * 功能说明
     * ◼ 查询游戏类型每日结算信息。
     * ◼ 日期范围：一天前至三个月内
     * ◼ 查询日期为 01-01-2021，则取得数据范围为 01-01-2021 12:00:00 至 01-02-2021 12:00:00。
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
        date_default_timezone_set("Etc/GMT+4");

        $params = [
            'action' => 66,
            'ts' => (int)(microtime(true) * 1000),
            'parent' => $config['cagent'],
            'date' => date('d-m-Y', strtotime($startDay)),
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam($params);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        if ($res['status'] != '0000') {
            return false;
        }

        //没有数据
        if (!isset($res['data']['totalSummary']) || empty($res['data']['totalSummary'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        $betAmount = bcmul($res['data']['totalSummary']['validBet'], 100, 0);
        $winAmount = bcmul($res['data']['totalSummary']['win'], 100, 0);
        $betCount = $res['data']['totalSummary']['count'];

        $result = \DB::table($this->orderTable)
            ->where('gameDate', '>=', $startDay . ' 12:00:00')
            ->where('gameDate', '<', $endDay . ' 12:00:00')
            ->select(\DB::raw("count(0) as betCount,sum(bet) as betAmount, sum(win) as winAmount"))->first();

        //金额正确
        if (bccomp($betAmount, $result->betAmount, 0) == 0 && bccomp($betCount, $result->betCount, 0) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
        }

        //金额不对,重新拉单 64最多拉5分钟内的注单
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
        $result = \DB::table($this->orderTable)
            ->where('gameDate', '>=', $start_time)
            ->where('gameDate', '<=', $end_time)
            ->selectRaw("sum(bet) as bet,sum(bet) as valid_bet,sum(total) as win_loss")
            ->first();
        if ($result) {
            $result->bet = abs($result->bet);
            $result->valid_bet = abs($result->valid_bet);
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
            'username' => 'playerId',
            'bet' => 'bet',
            'win' => 'win',
            'profit' => 'total',
            'gameDate' => 'gameDate'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->game_type, $data);
    }

    public function queryHotOrder($user_prefix, $stime, $end, $args = [])
    {
        return [];
    }

    public function queryLocalOrder($user_prefix, $start_time, $end_time, $page = 1, $page_size = 500)
    {
        $query = \DB::table($this->orderTable)
            ->where('gameDate', '>=', $start_time)
            ->where('gameDate', '<=', $end_time)
            ->where('playerId', 'like', "%$user_prefix%")
            ->selectRaw("id,gameDate,seqNo as order_number,bet,bet as valid_bet,total as win_loss");
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
     * @param $stime
     * @param $etime
     * @return bool
     */
    public function orderByTime($stime, $etime)
    {
        $default_timezone = date_default_timezone_get();
        $config = $this->initConfigMsg($this->game_type);
        $startTime = strtotime($stime);
        $formEndTime = strtotime($etime);
        while (1) {
            $endTime = $startTime + 5 * 60;
            if ($endTime - $formEndTime > 0) {
                $endTime = $formEndTime;
            }
            if ($startTime >= $endTime) {
                break;
            }

            date_default_timezone_set("Etc/GMT+4");
            $stime = date('d-m-Y H:i:00', $startTime);
            $etime = date('d-m-Y H:i:00', $endTime);
            $param = [
                'action' => 64,
                'ts' => (int)(microtime(true) * 1000),
                'parent' => $config['cagent'],
                'starttime' => $stime,
                'endtime' => $etime,
            ];
            date_default_timezone_set($default_timezone);

            $res = $this->requestParam($param);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            if ($res['status'] != '0000') {
                return false;
            }
            //更新数据
            if (isset($res['data']) || count($res['data']) > 0) {
                $this->updateOrder($res['data']);
            }

            $startTime = $endTime;

            sleep(5);
        }

        return true;
    }

    public function requestParam(array $data)
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
        $encryptData = $this->encrypt(json_encode($data), $config);
        $param = [
            'dc' => $config['lobby'],
            'x' => $encryptData
        ];
        $url = $config['apiUrl'] . '?dc=' . $param['dc'] . '&x=' . $param['x'];
        $re = Curl::commonPost($config['apiUrl'], null, http_build_query($param), null, true);
        if ($re['status'] == 200) {
            $re['content'] = json_decode($re['content'], true);
        }
        GameApi::addRequestLog($url, $this->game_type, $data, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

    public function encrypt($str, $config)
    {
        $key = $config['key'];
        $iv = $config['des_key'];
        $str = $this->padString($str);
        $encrypted = openssl_encrypt($str, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $data = base64_encode($encrypted);
        $data = str_replace(array('+', '/', '='), array('-', '_', ''), $data);
        return $data;
    }

    public function decrypt($code, $config)
    {
        $code = str_replace(array('-', '_'), array('+', '/'), $code);
        $code = base64_decode($code);
        $key = $config['key'];
        $iv = $config['des_key'];
        $decrypted = openssl_decrypt($code, 'AES-128-CBC', $key, OPENSSL_NO_PADDING, $iv);
        return utf8_encode(trim($decrypted));
    }

    private function padString($source)
    {
        $paddingChar = ' ';
        $size = 16;
        $x = strlen($source) % $size;
        $padLength = $size - $x;
        for ($i = 0; $i < $padLength; $i++) {
            $source .= $paddingChar;
        }
        return $source;
    }

}