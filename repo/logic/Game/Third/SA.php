<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Logic;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * SA真人
 */
class SA extends GameLogic
{
    protected $game_type = 'SA';
    protected $orderTable = 'game_order_sa';


    /**
     * 第三方拉单
     * @return bool
     */
    public function synchronousData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('gameDate');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 2 * 60; //取2分钟前的数据
        }
        $endTime = $now;
        //接数据时间间隔不能超过24小时
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $stime = date('Y-m-d H:i:00', $startTime);
        $etime = date('Y-m-d H:i:00', $endTime);
        $param = [
            'method' => 'GetAllBetDetailsForTimeIntervalDV',
            'FromTime' => $stime,
            'ToTime' => $etime,
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam($param, true);
        if (!$res['status']) {
            return false;  //请求超时，不做任何处理
        }

        if (!isset($res['BetDetailList']) || empty($res['BetDetailList'])) { // 未有任何订单数据
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            return true;
        }

        $this->updateOrder($res['BetDetailList']);

        $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
    }

    /**
     * 订单校验
     * 此服务会从大厅取得所有在线游戏投注总额.
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
        //取1天前的数据 当前过2时,正常拉单时间小于汇总时间
        if ($startDay > $day || ($startDay == $day && date('H') < 2) || (!is_null($lastTime) && $lastTime < strtotime($endDay))) {
            return true;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $params = [
            'method' => 'GetUserBetAmountDV',
            'Time' => date('YmdHis'),
            'StartDate' => $startDay,
            'TimeRange' => 0, //24h
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam($params, true);
        //接口报错
        if (!$res['status']) {
            return false;
        }
        //没有数据
        if (!isset($res['BetAmountDetailList']) || empty($res['BetAmountDetailList'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        $betAmount = 0;
        foreach ($res['result'] as $val) {
            $betAmount = bcadd($betAmount, $val['turnover']['StakeAmount'], 2);
        }
        $result = \DB::table($this->orderTable)
            ->where('gameDate', '>=', $startDay)
            ->where('gameDate', '<', $endDay)
            ->select(\DB::raw("sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        //金额正确
        if (bccomp($betAmount, $result->betAmount, 2) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        //金额不对,重新拉单
        $this->orderByTime($startDay . ' 00:00:00', $endDay . ' 00:00:00');

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('gameDate', '>=', $startDay)
            ->where('gameDate', '<', $endDay)
            ->select(\DB::raw("sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        //金额不正确
        if (bccomp($betAmount, $result2->betAmount, 2) != 0) {
            $this->addGameOrderCheckError($this->game_type,time(), $params, $startDay, $endDay, $betAmount, 0, $result2->betAmount, $result2->winAmount);
            return false;
        }

        //金额正确
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
     * 按分钟检索事务
     * @param $stime
     * @param $etime
     * @return bool
     */
    public function orderByTime($stime, $etime)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //接数据时间间隔不能超过24小时
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $stime = date('Y-m-d H:i:00', $startTime);
        $etime = date('Y-m-d H:i:00', $endTime);
        $param = [
            'method' => 'GetAllBetDetailsForTimeIntervalDV',
            'FromTime' => $stime,
            'ToTime' => $etime,
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam($param, true);
        if (!$res['status']) {
            return false;  //请求超时，不做任何处理
        }

        if (!isset($res['BetDetailList']) || empty($res['BetDetailList'])) { // 未有任何订单数据
            return true;
        }

        return $this->updateOrder($res['BetDetailList']);
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
     * 更新订单
     * @param $data
     * @param int $updateStatus
     * @return bool
     */
    public function updateOrder($data, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $key1 => $val2) {
            //多条注单
            if(!isset($val2[0])){
                $val2[0] = $val2;
            }
            foreach ($val2 as $key => $val) {
                if ($updateStatus) {
                    if (\DB::table($this->orderTable)->where('OCode', $val['BetID'])->count()) {
                        continue;
                    }
                }
                date_default_timezone_set("Etc/GMT-8");
                $gameDate = strtotime($val['PayoutTime']);
                date_default_timezone_set($default_timezone);
                $insertData[] = [
                    'tid' => intval(ltrim($val['Username'], 'game')),
                    'OCode' => $val['BetID'],
                    'Username' => $val['Username'],
                    'gameDate' => date('Y-m-d H:i:s', $gameDate),
                    'gameCode' => $val['GameID'],
                    'betAmount' => bcmul($val['BetAmount'], 100, 0),
                    'winAmount' => bcmul($val['ResultAmount'] + $val['BetAmount'], 100, 0),
                    'income' => bcmul($val['ResultAmount'], 100, 0),
                ];
            }
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 发送请求
     * @param array $param 请求参数
     * @param bool $is_generic 是否为post请求
     * @return array|string
     */
    public function requestParam(array $param, bool $is_generic = true)
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
        $md5Key = $config['cagent'];
        $secretKey = $config['pub_key'];
        $encryptKey = $config['key'];
        $Time = date('YmdHis', time());
        $option = [
            'method' => $param['method'],
            'Key' => $secretKey,
            'Time' => $Time
        ];
        unset($param['method']);
        $option = array_merge($option, $param);
        $queryString = http_build_query($option, '', '&');
        $crypt = new DES($encryptKey);
        $q = $crypt->encrypt($queryString);
        $s = md5($queryString . $md5Key . $Time . $secretKey);

        $url = $is_generic ? $config['apiUrl'] : $config['orderUrl'];
        $params = array(
            's' => $s,
            'q' => $q
        );
        $re = Curl::commonPost($url, null, http_build_query($params), array('Content-Type: application/x-www-form-urlencoded'));
        $re = $this->parseXML($re);
        is_array($re) && $re = json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        GameApi::addRequestLog($url, 'SA', array_merge($option, $params), $re);
        $res = json_decode($re, true);
        if (isset($res) && isset($res['ErrorMsgId']) && ($res['ErrorMsgId'] == 0 || $res['ErrorMsgId'] == 113)) {
            $res['status'] = true;
        } else {
            $res['status'] = false;
        }
        return $res;
    }
}

class DES
{
    var $key;
    var $iv;

    function __construct($key, $iv = 0)
    {
        $this->key = $key;
        if ($iv == 0) {
            $this->iv = $key;
        } else {
            $this->iv = $iv;
        }
    }

    function encrypt($str)
    {
        return base64_encode(openssl_encrypt($str, 'DES-CBC', $this->key, OPENSSL_RAW_DATA, $this->iv));
    }

    function decrypt($str)
    {
        $str = openssl_decrypt(base64_decode($str), 'DES-CBC', $this->key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $this->iv);
        return rtrim($str, "\x1\x2\x3\x4\x5\x6\x7\x8");
    }

    function pkcs5Pad($text, $blocksize)
    {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }
}