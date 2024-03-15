<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Logic;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * AWC游戏聚合平台
 * Class AWC
 */
class AWC extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = '';
    /**
     * @var string 游戏平台
     */
    protected $platfrom = '';
    /**
     * @var string 游戏订单类型（平台类型与游戏类型不一致）
     */
    protected $orderType = '';
    /**
     * @var string 游戏类型 如：GAME LIVE
     */
    protected $gameType = 'LIVE';

    /**
     * 检查接口状态
     * @return bool
     */
    public function checkStatus()
    {
        $fields = [
            'platform' => $this->platfrom,
        ];
        $res = $this->requestParam('wallet/checkStatus', $fields, false);
        if (isset($res['status']) && $res['status'] == "0000") {
            return true;
        }
        return false;
    }

    /**
     * 同步第三方游戏订单
     * @return bool
     * @throws \Exception
     */
    public function synchronousData()
    {
        if (!$this->checkStatus()) {
            return false;
        }
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->orderType);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('betTime');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 60; //取1分钟内的数据
        }
        $endTime = $now;
        //每次最大拉取区间24 小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");

        $fields = [
            'timeFrom' => date('c', $startTime),
            'status' => 1, //已结账
            'platform' => $this->platfrom
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestOrder('/fetch/gzip/getTransactionByUpdateDate', $fields);

        if (!isset($res['status']) || $res['status'] != '0000') {
            return false;
        }
        if (!isset($res['transactions']) || empty($res['transactions'])) {
            $endTime = $endTime - 60;
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->orderType, $endTime);
            return true;
        }
        $this->updateOrder($res['transactions']);
        $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->orderType, $endTime);
        return true;
    }

    /**
     * 更新订单表
     * @param array $data
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
                if (\DB::table($this->orderTable)->where('platformTxId', $val['platformTxId'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT-8");
            $betTime = strtotime(str_replace(' ', '+', $val['betTime']));//下注时间
            $txTime = strtotime(str_replace(' ', '+', $val['txTime'])); //交易时间
            $updateTime = strtotime(str_replace(' ', '+', $val['updateTime'])); //更新时间
            $roundStartTime = isset($val['gameInfo']['roundStartTime']) ? strtotime($val['gameInfo']['roundStartTime']) : '';
            date_default_timezone_set($default_timezone);
            if($roundStartTime){
                $val['gameInfo']['roundStartTime'] = date('Y-m-d H:i:s', $roundStartTime);
            }
            $gameInfo = json_encode($val['gameInfo'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $insertData[] = [
                "tid" => intval(ltrim($val['userId'], 'game')),
                "gameType" => $val['gameType'],
                "winAmount" => $val['winAmount'],
                "txTime" => date('Y-m-d H:i:s', $txTime),
                "settleStatus" => $val['settleStatus'],
                "gameInfo" => $gameInfo ,
                "realWinAmount" => $val['realWinAmount'],
                "updateTime" => date('Y-m-d H:i:s', $updateTime),
                "realBetAmount" => $val['realBetAmount'],
                "userId" => $val['userId'],
                "platform" => $val['platform'],
                "txStatus" => $val['txStatus'],
                "betAmount" => $val['betAmount'],
                "gameName" => $val['gameName'],
                "platformTxId" => $val['platformTxId'],
                "betTime" => date('Y-m-d H:i:s', $betTime),
                "gameCode" => $val['gameCode'],
                "currency" => $val['currency'],
                "jackpotWinAmount" => $val['jackpotWinAmount'],
                "jackpotBetAmount" => $val['jackpotBetAmount'],
                "turnover" => $val['turnover'],
                "roundId" => $val['roundId'],
                "betType" => $val['betType'] ?? '',
            ];
        }

        return $this->addGameOrders($this->orderType, $this->orderTable, $insertData);

    }

    /**
     * 订单校验
     * 此API仅可捞取一个小时前的资料，会因每次资料量不同影响系统"计算总和(summary)"的时间
     * 因此建议您整点后15~20分钟，再拉取一小时前的总和资料
     * 例如:若您想拉取13:00-14:00的总和资料，建议您可于15:20再进行捞取
     * 搜寻区间以小时为单位
     * 注意事项：捞取结果按货币分类
     * @return bool
     * @throws \Exception
     */
    public function synchronousCheckData()
    {
        if (!$this->checkStatus()) {
            return false;
        }
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->orderType);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = strtotime(date('Y-m-d H:00:00', $now - 86400)); //取1天前的数据
        }

        //校验3次不通过则跳过
        $check_count = $this->redis->incr(CacheKey::$perfix['gameOrderCheckCount'] . $this->orderType);
        if($check_count > 3){
            $startTime = $startTime + 3600;
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->orderType, $startTime);
            $this->redis->set(CacheKey::$perfix['gameOrderCheckCount'] .  $this->orderType, 1);
        }

        $endTime = strtotime(date('Y-m-d H:00:00', $now - 3600)); //取1小时前的数据
        //每次最大拉取区间1 小时内
        if ($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }
        //当前小时不拉数据,延期20分钟
        if (date('H', $now) == date('H', $endTime) || (date('Y-m-d H', $endTime) == date('Y-m-d H', $startTime)) || $endTime > $now || (date('Y-m-d H:20', $endTime) > date('Y-m-d H:i', $startTime))) {
            return true;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");

        $params = [
            'startTime' => date('Y-m-d\THP', $startTime),
            'endTime' => date('Y-m-d\THP', $endTime),
            'platform' => $this->platfrom,
            'gameType' => $this->gameType,
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestOrder('/fetch/gzip/getSummaryByTxTimeHour', $params);
        if (!isset($res['status']) || $res['status'] != '0000') {
            return false;
        }
        if (!isset($res['transactions']) || empty($res['transactions'])) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->orderType, $endTime);
            return true;
        }
        //总订单数
        $betCount = 0;
        //下注金额
        $betAmount = 0;
        //返还金额 (包含下注金额)
        $winAmount = 0;

        foreach ($res['transactions'] as $val) {
            $betCount += $val['betCount'];
            $betAmount += $val['betAmount'];
            $winAmount += $val['winAmount'];
        }

        $result = \DB::table($this->orderTable)
            ->where('betTime', '>=', date("Y-m-d H:00:00", $startTime))
            ->where('betTime', '<=', date("Y-m-d H:00:00", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        //金额正确
        if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->orderType, $endTime);
            return true;
        }

        //订单数不对补单
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime));

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('betTime', '>=', date("Y-m-d H:00:00", $startTime))
            ->where('betTime', '<=', date("Y-m-d H:00:00", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        if (!(bccomp($result2->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result2->betAmount, 2) == 0 && bccomp($winAmount, $result2->winAmount, 2) == 0)) {
            $this->addGameOrderCheckError($this->orderType, $now, $params, date("Y-m-d H:00:00", $startTime), date("Y-m-d H:00:00", $endTime), $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
            return false;
        }

        //金额匹配完全正确
        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->orderType, $endTime);
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('betTime', '>=', $start_time)
            ->where('betTime', '<=', $end_time)
            ->selectRaw("sum(betAmount) as bet,sum(betAmount) as valid_bet,sum(winAmount) as win_loss")
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
            'username' => 'userId',
            'bet' => 'betAmount',
            'win' => 'winAmount',
            'profit' => 'winAmount-betAmount',
            'gameDate' => 'betTime'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->orderType, $data, false);
    }

    public function queryHotOrder($user_prefix, $startTime, $endTime, $args = [])
    {
        return [];
    }

    public function queryLocalOrder($user_prefix, $start_time, $end_time, $page = 1, $page_size = 500)
    {
        $query = \DB::table($this->orderTable)
            ->where('betTime', '>=', $start_time)
            ->where('betTime', '<=', $end_time)
            ->where('userId', 'like', "%$user_prefix%")
            ->selectRaw("id,betTime,round as order_number,betAmount as bet,betAmount as valid_bet,winAmount as win_loss");
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
        //每次最大拉取区间24 小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");

        $fields = [
            'timeFrom' => date('c', $startTime),
            'status' => 1, //已结账
            'platform' => $this->platfrom
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestOrder('/fetch/gzip/getTransactionByUpdateDate', $fields);

        if (!isset($res['status']) || $res['status'] != '0000') {
            return false;
        }
        if (!isset($res['transactions']) || empty($res['transactions'])) {
            return true;
        }
        $this->updateOrder($res['transactions']);
        return true;
    }

    /**
     * 按小时拉取
     * @param $stime
     * @param $etime
     */
    public function orderByHour($stime, $etime)
    {
        return $this->orderByTime($stime, $etime);
    }

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @param bool $is_order
     * @param bool $is_post 是否为post请求
     * @return array|string
     */
    public function requestParam(string $action, array $param, $is_order = true, bool $is_post = true)
    {
        $config = $this->initConfigMsg($this->orderType);
        if(!$config){
            $ret = [
                'responseStatus' => false,
                'message' => 'api not config'
            ];
            GameApi::addElkLog($ret, $this->orderType);
            return $ret;
        }
        $param['cert'] = $config['key'];
        $param['agentId'] = $config['cagent'];

        $querystring = http_build_query($param, '', '&');
        //echo $querystring.PHP_EOL;
        $url = ($is_order ? $config['orderUrl'] : $config['apiUrl']) . '/' . $action;
        //echo $url.PHP_EOL;
        $headers = array(
            "content-type: application/x-www-form-urlencoded"
        );
        $re = Curl::commonPost($url, null, $querystring, $headers);
        is_array($re) && $re = json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        GameApi::addRequestLog($url, $this->orderType, $param, $re);
        return json_decode($re, true);
    }

    public function requestOrder(string $action, $param)
    {
        $config = $this->initConfigMsg($this->orderType);
        $param['cert'] = $config['key'];
        $param['agentId'] = $config['cagent'];

        $querystring = http_build_query($param, '', '&');
        $url = $config['orderUrl'] . '/' . $action;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $querystring,
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($err) {
            $re = "{err:{$err}}";
        } else {
            $re = $response;
        }
        GameApi::addRequestLog($url, $this->orderType, $param, json_encode(['status' => $httpCode ?? 200, 'content' => json_decode($re, true)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return json_decode($re, true);
    }
}
