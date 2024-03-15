<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

class YESBINGO extends GameLogic
{
    public $game_type = 'YESBINGO';
    protected $orderTable = 'game_order_yesbingo';

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

        //每次查询时间范围最多为 5 分钟。
        if ($endTime - $startTime > 300) {
            $endTime = $startTime + 300;
        }

        if (strtotime(date('Y-m-d H:i:00', $startTime)) >= strtotime(date('Y-m-d H:i:00', $endTime))) {
            return false;
        }
        $config = $this->initConfigMsg($this->game_type);
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT");
        $stime = date('Y-m-d\TH:i:00\Z', $startTime);
        $etime = date('Y-m-d\TH:i:00\Z', $endTime);
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

        if ($res['status'] != '0000' && $res['status'] != '9999') {
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
                if (\DB::table($this->orderTable)->where('OCode', $val['seqNo'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT");
            $gameDate = strtotime($val['gameDate']);
            $lastModifyTime = strtotime($val['lastModifyTime']);
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid' => intval(ltrim($val['uid'], 'game')),
                'Username' => $val['uid'],
                'OCode' => $val['seqNo'],
                'gType' => $val['gType'],
                'gameCode' => $val['mType'],
                'gameDate' => date('Y-m-d H:i:s', $gameDate),
                'betAmount' => bcmul(abs($val['bet']), 100, 0),
                'winAmount' => bcmul($val['win'], 100, 0),
                'income' => bcmul($val['win']-abs($val['bet']), 100, 0),
                'lastModifyTime' => date('Y-m-d H:i:s', $lastModifyTime),
                'game_menu_id' => $val['gType'] ==1 ? 149 : ($val['gType'] == 3 ? 147 : 148),
            ];
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 订单校验
     * 功能说明
     * ◼ 查询游戏类型每日结算信息。
     * ◼ 日期范围：一天前至三个月内
     * ◼ 例 1：查詢日期為 2020-01-01，結帳時間為 0，則取得資料範圍為 2020-01-01 00:00:00.000 至 2020-01-01 23:59:59.999（UTC+0）。
     * 為確保資料完整性，延後 1 小時取值
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

        //取1天前的数据 延期1小时
        if (($startDay == $day && date('H') < '01')) {
            return true;
        }
        $config = $this->initConfigMsg($this->game_type);
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT");

        $params = [
            'action' => 41,
            'ts' => (int)(microtime(true) * 1000),
            'parent' => $config['cagent'],
            'date' => date('Y-m-d', strtotime($startDay)),
            'hour' => 0, //結帳時間 未填入則結帳時間為預設值 0,目前提供時間為 0、4、6、8、12
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam($params);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }

        if ($res['status'] != '0000' && $res['status'] != '9999') {
            return false;
        }

        //没有数据
        if (!isset($res['data']['bet']) || empty($res['data']['bet'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        $betAmount = bcmul($res['data']['bet'], 100, 0);
        $winAmount = bcmul($res['data']['win'], 100, 0);
        $betCount = $res['data']['count'];

        $result = \DB::table($this->orderTable)
            ->where('lastModifyTime', '>=', $startDay)
            ->where('lastModifyTime', '<', $endDay)
            ->select(\DB::raw("count(0) as betCount,sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();

        //金额正确
        if (bccomp($betAmount, $result->betAmount, 0) == 0 && bccomp($betCount, $result->betCount, 0) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
        }

        //金额不对,重新拉单 64最多拉5分钟内的注单
        $this->orderByTime($startDay, $endDay);

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('lastModifyTime', '>=', $startDay)
            ->where('lastModifyTime', '<', $endDay)
            ->select(\DB::raw("count(0) as betCount,sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        if (!(bccomp($betAmount, $result2->betAmount, 0) == 0
            && bccomp($betCount, $result2->betCount, 0) == 0)
        ) {
            $this->addGameOrderCheckError($this->game_type, time(), $params, $startDay, $endDay, $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
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
            if (strtotime(date('Y-m-d H:i:00', $startTime)) >= strtotime(date('Y-m-d H:i:00', $endTime))) {
                return false;
            }

            date_default_timezone_set("Etc/GMT");
            $stime = date('Y-m-d\TH:i:00\Z', $startTime);
            $etime = date('Y-m-d\TH:i:00\Z', $endTime);
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

            if ($res['status'] != '0000' && $res['status'] != '9999') {
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