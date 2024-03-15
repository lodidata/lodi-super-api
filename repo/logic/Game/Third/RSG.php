<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;
use function GuzzleHttp\Psr7\str;

/**
 * Explain: RSG 游戏接口  电子，捕鱼游戏
 *
 * OK
 */
class RSG extends GameLogic
{

    protected $game_type = 'RSG';
    protected $orderTable = 'game_order_rsg';

    /**
     * 同步订单
     * 延期3分钟，最大时间间隔不能超过5分钟
     * @return bool
     */
    public function synchronousData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type); //上次的结束时间
        // 开始与结束时间格式均为整分钟
        if ($r_time) {
            $startTime = strtotime(date('Y-m-d H:i:00', $r_time));
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('PlayTime');
            $startTime = $last_datetime ? strtotime(date('Y-m-d H:i:00', strtotime($last_datetime))) : strtotime(date('Y-m-d H:i:00', $now)) - 3600;
        }

        $lastTime = $now - 180;
        //接数据时间间隔不能超过5分钟，且需要对分钟进行取整，间隔时间为300-1
        $endTime = $startTime + 299;
        if ($endTime > $lastTime) {
            $endTime = $lastTime;
        }

        if ($startTime > $endTime || $endTime > $now) {
            return true;
        }

        // 时区为北京时间GMT+8
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $config = $this->initConfigMsg('RSG');
        $lobby = json_decode($config['lobby'], true);
        $fields = [
            'SystemCode' => $lobby['SystemCode'],
            'WebId' => $lobby['WebId'],
            'GameType' => $this->game_type == 'RSGBY' ? 2 : 1,
            'TimeStart' => date("Y-m-d H:i", $startTime),
            'TimeEnd' => date("Y-m-d H:i", $endTime),
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/History/GetGameDetail', $fields);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        if (!empty($res['Data']['GameDetail'])) {
            $this->updateOrder($res['Data']['GameDetail']);
        }
        $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
    }

    /**
     * 订单校验
     * 校验时间为前一天12:00到当天11:59
     * @return bool
     */
    public function synchronousCheckData()
    {
        $now = time();
        $day = date('Y-m-d', strtotime('-1 day'));
        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type); //上次的结束时间
        if ($r_time) {
            $startDay = $r_time;
        } else {
            $startDay = $day; //取1天前的数据
        }

        //校验3次不通过则跳过
        $check_count = $this->redis->incr(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type);
        if ($check_count > 3) {
            $startDay = date("Y-m-d", strtotime('+1 day', strtotime($startDay)));
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startDay);
            $this->redis->set(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type, 1);
        }

        //每次取1天的数据
        $endDay = date("Y-m-d", strtotime('+1 day', strtotime($startDay)));
        $startTime = strtotime($startDay . ' 12:00:00');
        $endTime = strtotime($endDay . ' 11:59:59');
        //正常拉单时间
        $lastTime = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);

        //取1天前的数据 当前过12时,正常拉单时间小于汇总时间
        if (($startDay == $day && date('H') < 12) || (!is_null($lastTime) && $lastTime < $endTime)) {
            return true;
        }
        $params = [
            'SystemCode' => $startDay,
            'WebId' => $startDay,
            'GameType' => $this->game_type == 'RSGBY' ? 2 : 1,
            'Date' => $startDay,
        ];

        $res = $this->requestParam('/Report/GetGameDailyReport', $params);
        if (!$res['responseStatus']) {
            return false;
        }
        //无数据
        if (!isset($res['Data']['GameReport']) || $res['Data']['GameReport'] == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }
        //总订单数
        $betCount = $res['Data']['GameReport']['RecordCount'];
        //下注金额
        $betAmount = $res['Data']['GameReport']['BetSum'];
        //输赢金额
        $winAmount = $res['Data']['GameReport']['NetWinSum'];
        if ($betCount == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        $result = \DB::table($this->orderTable)
            ->where('PlayTime', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('PlayTime', '<', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(BetAmt) as betAmount, sum(WinAmt) as winAmount"))->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 0) == 0 && bccomp($winAmount, $result->winAmount, 0) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
                return true;
            }
        }
        //订单数不对补单
        $formStartTime = $startTime;
        while (1) {
            sleep(5);
            $formEndTime = $formStartTime + 5 * 60;
            if ($formEndTime > $endTime) {
                $formEndTime = $endTime;
            }
            if ($formStartTime == $endTime) {
                break;
            }
            $status = $this->orderByTime(date('Y-m-d H:i:s', $formStartTime), date('Y-m-d H:i:s', $formEndTime));
            if (!$status) {
                return false;
            }
            //时间交换
            $formStartTime = $formEndTime;
        }

        //第二次校验
        $result = \DB::table($this->orderTable)
            ->where('bdate', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('bdate', '<', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(BetAmt) as betAmount, sum(WinAmt) as winAmount"))->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 0) == 0 && bccomp($winAmount, $result->winAmount, 0) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
                return true;
            }
        }
        //订单数不对
        $this->addGameOrderCheckError($this->game_type, time(), $params, date("Y-m-d H:i:s", $startTime), date("Y-m-d H:i:s", $endTime), $betAmount, $winAmount, $result->betAmount, $result->winAmount);

        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('PlayTime', '>=', $start_time)
            ->where('PlayTime', '<=', $end_time)
            ->selectRaw("sum(BetAmt) as bet,sum(BetAmt) as valid_bet,sum(WinAmt) as win_loss")
            ->first();
        return (array) $result;
    }

    /**
     * 游戏统计
     * @param null $date 日期
     * @return bool
     */
    public function queryOperatesOrder($date = null)
    {
        $data = [
            'username' => 'UserId',
            'bet' => 'BetAmt',
            'win' => 'WinAmt',
            'profit' => 'WinAmt-BetAmt',
            'gameDate' => 'PlayTime'
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
            ->where('PlayTime', '>=', $start_time)
            ->where('PlayTime', '<=', $end_time)
            ->where('UserId', 'like', "%$user_prefix%")
            ->selectRaw("id,PlayTime,SequenNumber as order_number,BetAmt as bet,BetAmt as valid_bet,WinAmt as win_loss");
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
        date_default_timezone_set("Etc/GMT-8");
        $fields = [
            'GameType' => $this->game_type == 'RSGBY' ? 2 : 1,
            'TimeStart' => date("Y-m-d H:i", $startTime),
            'TimeEnd' => date("Y-m-d H:i", $endTime),
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/History/GetGameDetail', $fields);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        if (!empty($res['Data']['GameDetail'])) {
            $this->updateOrder($res['Data']['GameDetail']);
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
                if (\DB::table($this->orderTable)->where('SequenNumber', (string) $val['SequenNumber'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT-8");
            $PlayTime = strtotime($val['PlayTime']);
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid' => intval(ltrim($val['UserId'], 'game')),
                'Currency' => $val['Currency'],
                'WebId' => $val['WebId'],
                'UserId' => $val['UserId'],
                'SequenNumber' => (string) $val['SequenNumber'],
                'GameId' => $val['GameId'],
                'SubGameType' => $val['SubGameType'],
                'BetAmt' => bcmul($val['BetAmt'], 100, 0),
                'WinAmt' => bcmul($val['WinAmt'], 100, 0),
                'PlayTime' => date('Y-m-d H:i:s', $PlayTime),
                'game_menu_id' => $this->game_type == 'RSGBY' ? 151 : 150,
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
        $config = $this->initConfigMsg('RSG');
        if (!$config) {
            $ret = [
                'responseStatus' => false,
                'message' => 'api not config'
            ];
            GameApi::addElkLog($ret, 'RSG');
            return $ret;
        }
        // des加密后拼接字符串md5加密
        $current_timestamp = time();
        $encryptText = $this->encryptText($config, $param);
        $sign_data = md5($config['cagent'] . $config['key'] . $current_timestamp . $encryptText);
        $requestBodyString = 'Msg=' . $encryptText;

        $headers = [
            "Content-Type: application/x-www-form-urlencoded",
            "X-API-ClientID:" . $config['cagent'],
            "X-API-Signature:" . $sign_data,
            "X-API-Timestamp:" . $current_timestamp
        ];

        $url = $config['apiUrl'] . $action;
        $re = Curl::commonPost($url, null, $requestBodyString, $headers, true);
        if ($re['status'] == 200) {
            $re['content'] = openssl_decrypt(base64_decode($re['content']), 'DES-CBC', $config['des_key'], OPENSSL_RAW_DATA, $config['pub_key']);
            $re['content'] = json_decode($re['content'], true);
        }
        GameApi::addRequestLog($url, 'RSG', $param, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $ret = [];
        if ($re['status'] == 200) {
            $ret = $re['content'];
            $ret['responseStatus'] = true;
        } elseif ($re['status'] == 0) {
            $ret['ErrorCode'] = '886';
            $ret['responseStatus'] = false;
            $ret['Message'] = 'api error';
        } else {
            $ret['ErrorCode'] = $ret['ErrorCode'] ?? '886';
            $ret['responseStatus'] = false;
            $ret['Message'] = $ret['ErrorMessage'] ?? 'api error';
        }
        return $ret;
    }

    /**
     * 请求参数加密
     */
    public function encryptText(array $config, array $param)
    {
        $encrypt_data = openssl_encrypt(json_encode($param), 'DES-CBC', $config['des_key'], OPENSSL_RAW_DATA, $config['pub_key']);
        $req_base64 = base64_encode($encrypt_data);
        return $req_base64;
    }

}