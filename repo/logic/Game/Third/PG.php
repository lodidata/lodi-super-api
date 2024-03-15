<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * PG电子
 */
class PG extends GameLogic
{
    protected $game_type = 'PG';
    protected $orderTable = 'game_order_pg';
    protected $trace_id;
    protected $thrid_page_size = 1500;


    /**
     * 运营商可获得最近 60 天的投注历史记录
     * 数据行版本并不是唯一值。运营商必须为每个请求提取至少 1500 条记录。
     * 步骤
     * • 步骤一：在第一个 GetHistory API 调用中，设置 row_version = 1
     * • 步骤二：保存每个获取数据的请求调用中的 rowVersion 最大值
     * • 步骤三：在接下来的调用中，将 row_version 值设置为步骤二里所保存的
     * rowVersion
     * • 重复第二及第三步骤，直到返回的记录少于所需数量（例如：每个请求 1500 条记
     * 录）
     * • 如果返回的记录数少于所需数量，请停止并等待一个时间间隔（建议 5 分钟）再进行
     * 下一个 API 调用
     * • 可通过检查每个调用中重复的 betId 来识别重复的记录
     * @throws \Exception
     */
    public function synchronousData()
    {
        $row_version = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if (is_null($row_version)) {
            $row_version = \DB::table($this->orderTable)->max('rowVersion');
            if (is_null($row_version)) {
                $row_version = 1;
            }
        }
        while (1) {
            $param = [
                'count' => $this->thrid_page_size,
                'bet_type' => 1,
                'row_version' => $row_version,
                'hands_status' => 0 //投注状态：0:全部（默认）1: 非最后一手投注2：最后一手投注3：已调整
            ];
            $res = $this->requestParam('/Bet/v4/GetHistory', $param, true, true);
            //接口错误
            if (!$res['responseStatus']) {
                break;
            }

            if (!isset($res['data']) || empty($res['data'])) { // 未有任何订单数据
                break;
            }
            $row_version = $this->updateOrder($res['data'], $row_version);

            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $row_version);
            //最后一页
            if (count($res['data']) < $this->thrid_page_size) {
                break;
            }
        }
    }

    /**
     * 订单校验
     * 运营商可以获取最近 60 天的投注记录。
     * 按小时拉数据，拉取2小时前到1小时之前的数据
     */
    public function synchronousCheckData()
    {
        $now = time();

        $r_time = $this->redis->get(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = $now - 86400; //1天前
        }

        //校验3次不通过则跳过
        $check_count = $this->redis->incr(CacheKey::$perfix['gameOrderCheckCount'] . $this->game_type);
        if($check_count > 3){
            $startTime = $startTime + 3600;
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startTime);
            $this->redis->set(CacheKey::$perfix['gameOrderCheckCount'] .  $this->game_type, 1);
        }

        $endTime = $startTime + 3600;
        //当前小时不拉数据
        if (date('H', $now) == date('H', $endTime) || (date('Y-m-d H', $endTime) == date('Y-m-d H', $startTime)) || $endTime > $now) {
            return true;
        }
        $config = $this->initConfigMsg($this->game_type);
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $stime = date('Y-m-d H:00:00', $startTime);
        $etime = date('Y-m-d H:00:00', $endTime);
        $param = [
            'from_time' => strtotime($stime) . '000',
            'to_time' => strtotime($stime) . '000',
            'currency' => $config['currency'],
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/Bet/v4/GetHandsSummaryHourly', $param, true, true);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        if (!isset($res['data']) || empty($res['data'])) { // 未有任何订单数据
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return false;
        }
        $betAmount = 0;
        $winAmount = 0;
        foreach ($res['data'] as $val) {
            $betAmount += $val['totalBetAmount'];
            $winAmount += $val['totalWinAmount'];
        }
        $betAmount = bcmul($betAmount, 100, 0);
        $winAmount = bcmul($winAmount, 100, 0);
        $result = \DB::table($this->orderTable)
            ->where('gameDate', '>=', date('Y-m-d H:00:00', $startTime))
            ->where('gameDate', '<=', date('Y-m-d H:00:00', $endTime))
            ->select(\DB::raw("sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        //订单金额不对补单
        if (!(bccomp($betAmount, $result->betAmount, 0) == 0
            && bccomp($winAmount, $result->winAmount, 0) == 0)
        ) {
            $this->addGameOrderCheckError($this->game_type, $now, $param, date('Y-m-d H:00:00', $startTime), date('Y-m-d H:00:00', $endTime), $betAmount, $winAmount, $result->betAmount, $result->winAmount);
            //$this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime));
            return false;
        }


        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
        return true;
    }

    /**
     * 更新订单
     * @param $data
     * @param $row_version
     * @param int $updateStatus
     * @return mixed
     */
    public function updateOrder($data, $row_version, $updateStatus = 0)
    {
        $max_row_version = $row_version;
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $key => $val) {
            if($val['rowVersion'] == $max_row_version){
                continue;
            }
            $val['betId'] = (string)$val['betId'];
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('OCode', (string)$val['betId'])->count()) {
                    continue;
                }
            }

            //最后一条记录时间
            $row_version = $val['rowVersion'] > $row_version ? $val['rowVersion'] : $row_version;
            $val['betTime'] = date('Y-m-d H:i:s', substr($val['betTime'], 0, 10));
            $val['betEndTime'] = date('Y-m-d H:i:s', substr($val['betEndTime'], 0, 10));

            $insertData2 = [
                'tid' => intval(ltrim($val['playerName'], 'game')),
                'OCode' => (string)$val['betId'],
                'Username' => $val['playerName'],
                'gameDate' => $val['betEndTime'],
                'gameCode' => $val['gameId'],
                'betAmount' => bcmul($val['betAmount'], 100, 0),
                'winAmount' => bcmul($val['winAmount'], 100, 0),
                'income' => bcmul($val['winAmount'] - $val['betAmount'], 100, 0),
            ];
            unset($val['playerName'], $val['gameId'], $val['betId'], $val['betAmount'], $val['winAmount']);
            $insertData[] = array_merge($insertData2, $val);

        }

        $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
        return $row_version;
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
            ->selectRaw("id,gameDate,ugsbetid as order_number,betAmount as bet,betAmount as valid_bet,winAmount as win_loss");
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
        $row_version = $stime;
        $endTime = strtotime($etime);
        while (1) {
            $param = [
                'count' => $this->thrid_page_size,
                'bet_type' => 1,
                'row_version' => $row_version,
                'hands_status' => 0 //投注状态：0:全部（默认）1: 非最后一手投注2：最后一手投注3：已调整
            ];
            $res = $this->requestParam('/Bet/v4/GetHistory', $param, true, true);
            //接口错误
            if (!$res['responseStatus']) {
                break;
            }

            if (!isset($res['data']) || empty($res['data'])) { // 未有任何订单数据
                break;
            }
            $row_version = $this->updateOrder($res['data'], $row_version);

            //最后一页
            if (count($res['data']) < $this->thrid_page_size) {
                break;
            }
        }

        return true;
    }

    /**
     * 按小时拉取
     * @param $stime
     * @param $etime
     * @return array
     */
    public function orderByHour($stime, $etime)
    {
        return $this->orderByTime($stime, $etime);
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
        $xdate = date('Ymd', time());
        $option = [
            'operator_token' => $config['cagent'],
            'secret_key' => $config['des_key']
        ];
        $option = array_merge($option, $param);

        $queryString = http_build_query($option, '', '&');
        $apiUrl = $is_order ? $config['orderUrl'] : $config['apiUrl'];
        $hosts = parse_url($apiUrl);
        $host = $hosts['host'];
        $contentsha256 = $this->hash_sha256($queryString, $config);
        $Credential = $xdate . '/' . $config['cagent'] . '/pws/v1';
        $SignedHeaders = 'host;x-content-sha256;x-date';
        $Signature = $this->hash_sha256($host . $contentsha256 . $xdate, $config);
        $authorization = "PWS-HMAC-SHA256Credential=" . $Credential . ',SignedHeaders=' . $SignedHeaders . ',Signature=' . $Signature;
        $header = [
            'Content-Type: application/x-www-form-urlencoded',
            'Host:' . $host,
            'x-date:' . $xdate,
            'x-content-sha256:' . $contentsha256,
            'Authorization:' . $authorization
        ];

        $url = rtrim($apiUrl, '/') . $action;
        $trace_id = $this->guid();
        $url .= '?trace_id=' . $trace_id;
        $re = Curl::commonPost($url, null, $queryString, $header, true);
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

    public function hash_sha256($string, $config)
    {
        return strtoupper(hash_hmac('sha256', utf8_encode($string), utf8_encode($config['key']), false));
    }

    /**
     * 请求的唯一标识符（GUID）
     * @return string
     */
    public function guid()
    {
        if (!$this->trace_id) {
            $charid = strtoupper(md5(str_random()));
            $hyphen = chr(45);// "-"
            //chr(123)// "{"
            $this->trace_id = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);
            // .chr(125);// "}"
        }
        return $this->trace_id;
    }
}
