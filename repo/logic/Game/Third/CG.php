<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class CG
 */
class CG extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_cg';
    protected $game_type = 'CG';


    /**
     * 检查接口状态
     * @return bool
     */
    public function checkStatus()
    {
        return true;
    }

    /**
     * 同步第三方游戏订单，延期3分钟
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
        $endTime = $now - 180;
        if($endTime < $startTime){
            return true;
        }
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
        $gameList = $this->redis->get('super_game_cg_3th');
        if(is_null($gameList) || $gameList =="null" || empty($gameList)){
            $game3th = \DB::table('game_3th')->whereIn('game_id', [91,92])->get(['kind_id', 'game_id'])->toArray();
            foreach($game3th as $val){
                $val = (array) $val;
                $gameList[$val['kind_id']] = $val['game_id'];
            }
            $this->redis->setex('super_game_cg_3th', 86400, json_encode($gameList));
        }else{
            $gameList = json_decode($gameList, true);
        }

        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('SerialNumber', $val['SerialNumber'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT-8");
            $LogTime = strtotime($val['LogTime']);//注單建立時間
            date_default_timezone_set($default_timezone);
            $insertData[] = [
                'tid' => intval(ltrim($val['ThirdPartyAccount'], 'game')),
                'SerialNumber' => $val['SerialNumber'],
                'GameType' => $val['GameType'],
                'LogTime' => date('Y-m-d H:i:s', $LogTime),
                'BetMoney' => $val['BetMoney'],
                'MoneyWin' => $val['MoneyWin'],
                'NormalWin' => $val['NormalWin'],
                'BonusWin' => $val['BonusWin'],
                'JackpotMoney' => $val['JackpotMoney'],
                'ThirdPartyAccount' => $val['ThirdPartyAccount'],
                'ValidBet' => $val['ValidBet'],
                'Device' => $val['Device'] ?? '',
                'IPAddress' => $val['IPAddress'] ?? '',
                'gameCategoryType' => isset($gameList[$val['GameType']]) && $gameList[$val['GameType']] == '92' ? 'pvp' : 'slot'
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
            $startTime = $startTime + 86400;
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startTime);
            $this->redis->set(CacheKey::$perfix['gameOrderCheckCount'] .  $this->game_type, 1);
        }

        $endTime = strtotime(date('Y-m-d H:00:00', $now - 7200)); //取2小时前的数据
        //每次最大拉取区间24小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        //当前小时不拉数据
        if (date('Y-m-d H', $now) == date('Y-m-d H', $endTime) || (date('Y-m-d H', $endTime) == date('Y-m-d H', $startTime)) || $endTime > $now) {
            return true;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $params = [
            'startTime' => date(DATE_RFC3339, $startTime), //注單更新起始時間，時區 +0, 需補到毫秒 Ex. 1566230400000
            'endTime' => date(DATE_RFC3339, $endTime),
            'method' => 'overview',//抓取方法 data: 抓取數據,rows: 抓取數據的總筆數,overview: 抓取数据并提供统计资讯
            'offset' => 0,
            'limit' => 10000,
            'removeComma' => 'True',
        ];
        date_default_timezone_set($default_timezone);

        $res = $this->requestParam('', $params, true, true);

        if (!$res['responseStatus']) {
            return false;
        }
        if (!isset($res['overview']) || empty($res['overview'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }
        //总订单数
        $betCount = $res['overview']['Rows'];
        //下注金额
        $betAmount = $res['overview']['TotalBet'];
        //输赢金额
        $winAmount = $res['overview']['TotalWin'];

        if ($betCount == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        $result = \DB::table($this->orderTable)
            ->where('LogTime', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('LogTime', '<=', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(BetMoney) as betAmount, sum(MoneyWin+JackpotMoney) as winAmount"))->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
                return true;
            }
        }
        //订单数不对补单
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime));


        //第二次校验
        $result = \DB::table($this->orderTable)
            ->where('LogTime', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('LogTime', '<=', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(BetMoney) as betAmount, sum(MoneyWin+JackpotMoney) as winAmount"))->first();
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
            ->where('LogTime', '>=', $start_time)
            ->where('LogTime', '<=', $end_time)
            ->selectRaw("sum(BetMoney) as bet,sum(ValidBet) as valid_bet,sum(MoneyWin) as win_loss")
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
            'username' => 'ThirdPartyAccount',
            'bet' => 'BetMoney',
            'win' => 'MoneyWin+JackpotMoney',
            'profit' => 'MoneyWin-BetMoney+JackpotMoney',
            'gameDate' => 'LogTime'
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
            ->where('LogTime', '>=', $start_time)
            ->where('LogTime', '<=', $end_time)
            ->where('userId', 'like', "%$user_prefix%")
            ->selectRaw("id,LogTime as gameDate,SerialNumber as order_number,BetMoney as bet,ValidBet as valid_bet,MoneyWin as win_loss");
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
    public function orderByTime($stime, $etime, $is_redis = false)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //每次最大拉取区间24 小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");

        $fields = [
            'startTime' => date(DATE_RFC3339, $startTime), //注單更新起始時間，時區 +0, 需補到毫秒 Ex. 1566230400000
            'endTime' => date(DATE_RFC3339, $endTime),
            'method' => 'rows',//抓取方法 data: 抓取數據,rows: 抓取數據的總筆數,overview: 抓取数据并提供统计资讯
            'offset' => 0,
            'limit' => 10000,
            'removeComma' => 'True',
        ];
        date_default_timezone_set($default_timezone);
        //第一步获取记录行数
        $res_rows = $this->requestParam('', $fields, true, true);
        if (!$res_rows['responseStatus']) {
            return false;
        }
        //总记录数
        if ($res_rows['rows'] == 0) {
            if ($is_redis) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            }
            return true;
        }
        $page = 0;
        while (1) {
            $fields['method'] = 'data';
            $fields['offset'] = $page * 10000;
            if ($res_rows['rows'] < $fields['offset']) {
                break;
            }
            $res = $this->requestParam('', $fields, true, true);
            if (!$res['responseStatus']) {
                break;
            }
            if (empty($res['data'])) {
                if ($is_redis) {
                    $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
                }
                break;
            }

            $this->updateOrder($res['data']);
            if ($is_redis) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            }
            //下一页
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

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @param bool $is_post 是否为post请求
     * @param bool $is_order
     * @return array|string
     */
    public function requestParam(string $action, array $param, bool $is_post = true, $is_order = false)
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

        if ($action) {
            $action = '/' . $action;
        }
        $url = rtrim($is_order ? $config['orderUrl'] : $config['apiUrl'], '/') . $action;
        $headers = [
            'Content-Type : '
        ];
        if ($is_order) {
            $headers = [
                'Content-Type: application/x-www-form-urlencoded'
            ];
        }

        $postParams = [
            'version' => $config['lobby'],
            'channelId' => $config['cagent'],
            'data' => $this->aes256CbcEncrypt($param, $config),
        ];
        //echo $url.PHP_EOL;
//var_dump(http_build_query($postParams, '', '&'));
        $re = Curl::commonPost($url, null, http_build_query($postParams, '', '&'), $headers);
        // var_dump($re);
        //
        if ($re === false) {
            $ret['responseStatus'] = false;
        } else {
            $re = $this->aes256CbcDecrypt($re, $config);
            //var_dump($re);
            $ret = json_decode($re, true);
            if ($ret['errorCode'] == 0) {
                $ret['responseStatus'] = true;
            } else {
                $ret['responseStatus'] = false;
                if (isset($ret['data'])) {
                    $ret['data'] = $this->aes256CbcDecrypt($ret['data'], $config);
                }
            }
        }
        $logs = $ret;
        unset($logs['responseStatus']);
        GameApi::addRequestLog($url, $this->game_type, $param, json_encode($logs, JSON_UNESCAPED_UNICODE));
        return $ret;
    }

    public function aes256CbcDecrypt($data, $config)
    {
        $raw_key = base64_decode($config['key']);
        $raw_iv = base64_decode($config['des_key']);
        //$raw_data = base64_decode($data);
        $raw_data = $data;

        return openssl_decrypt($raw_data, "AES-256-CBC", $raw_key, 0, $raw_iv);
    }

    public function aes256CbcEncrypt($data, $config)
    {
        $raw_key = base64_decode($config['key']);
        $raw_iv = base64_decode($config['des_key']);
        //$raw_data = base64_decode($data);
        $raw_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return openssl_encrypt($raw_data, "AES-256-CBC", $raw_key, 0, $raw_iv);
    }
}
