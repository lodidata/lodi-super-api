<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * PP 游戏接口  电子，捕鱼游戏
 * Class PP
 * @package Logic\Game\Third
 */
class PP extends GameLogic
{
    protected $game_alias = 'PP';
    protected $game_type = 'PP';
    protected $orderTable = 'game_order_pp';
    protected $dataTypeKey = 'Slot';
    protected $dataTypeValue = 'RNG';
    //游戏类型，分开请求接口
    protected $dataTypes = [
        'Slot' => 'RNG',
        'ECasino' => 'LC',
        'BY' => 'R2'
    ];

    public function synchronousData()
    {
        //接口维护
        if (!$this->healthCheck()) {
            return false;
        }
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if ($r_time) {
            $startTime = $r_time;
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('gameDate');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now - 600; //取10分钟内的数据
        }

        //毫秒
        $str_stime = str_pad($startTime, 13, "0", STR_PAD_RIGHT);
        $endTime = '';
        $fields = [
            'timepoint' => $str_stime
        ];

        //foreach ($this->dataTypes as $type => $datatype) {
        $fields['dataType'] = $this->dataTypeValue;
        $res = $this->requestOrderParam('/IntegrationService/v3/DataFeeds/gamerounds/', $fields);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }

        $contents = explode("\n", trim($res['content']));
        $endTime = substr(explode('=', array_shift($contents))[1], 0, 10);
        array_shift($contents);//排队字段名
        if (count($contents) > 0) {
            $this->updateOrder($contents, $this->dataTypeKey);
        }
        //if ($datatype == 'RNG') {
        $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
        //}

        //}
    }

    /**
     * 订单校验
     * 每日总数 API 按货币分组的提供每天特定时段的合计数据。运营商可使用此方法来对数据进行交叉检查。
     * 响应中只会包含已完成的游戏回合。
     * 如果游戏回合开始于某一天而在结束于另一天，则其结果将包含在其完成当天的每日总数中。
     * @return bool
     */
    public function synchronousCheckData()
    {
        //接口维护
        if (!$this->healthCheck()) {
            return false;
        }
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

        $startTime = $startDay . ' 00:00:00';
        $endDay = date("Y-m-d", strtotime('+1 day', strtotime($startDay)));
        $endTime = $startDay . ' 23:59:59';
        //正常拉单时间
        $lastTime = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);
        //取1天前的数据 当前过2时,正常拉单时间小于汇总时间
        if (($startDay == $day && date('H') < 2) || (!is_null($lastTime) && $lastTime < strtotime($endTime))) {
            return true;
        }
        $params = [
            'startDate' => $startTime,
            'endDate' => $endTime,
        ];

        //foreach ($this->dataTypes as $type => $datatype) {
        $params['dataType'] = $this->dataTypeValue;
        $res = $this->requestOrderParam('/IntegrationService/v3/DataFeeds/totals/daily/', $params);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        $contents = json_decode($res['content'], true);
        //参数错误
        if ($contents['error'] != 0) {
            return false;
        }
        //无数据
        if(empty($contents['data']) && $contents['error'] == 0){
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        $betAmount = $winAmount = 0;
        foreach ($contents['data'] as $val) {
            $betAmount += $val['totalBet'];
            $winAmount += $val['totalWin'];
        }
        $betAmount = bcmul($betAmount, 100, 0);
        $winAmount = bcmul($winAmount, 100, 0);
        //echo $betAmount . '---' . $winAmount.PHP_EOL;
        $result = \DB::table($this->orderTable)
            ->where('dataType', $this->dataTypeKey)
            ->where('gameDate', '>=', $startTime)
            ->where('gameDate', '<=', $endTime)
            ->select(\DB::raw("sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        //金额正确
        if (bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        //金额不对,重新拉单
        $formStartTime = strtotime($startTime);
        $formEndTime = strtotime($endTime);
        while (1) {
            if ($formStartTime > $formEndTime) {
                $formStartTime = $formEndTime;
            }

            sleep(5);
            $this->orderByTime(date('Y-m-d H:i:s', $formStartTime), date('Y-m-d H:i:s', $formEndTime));

            if ($formEndTime == $formStartTime) {
                break;
            }
            //拉单不能超过10分钟
            $formStartTime += 600;
        }

        //第二次校验
        $result2 = \DB::table($this->orderTable)
            ->where('dataType', $this->dataTypeKey)
            ->where('gameDate', '>=', $startTime)
            ->where('gameDate', '<=', $endTime)
            ->select(\DB::raw("sum(betAmount) as betAmount, sum(winAmount) as winAmount"))->first();
        //金额不正确
        if (!(bccomp($betAmount, $result2->betAmount, 2) == 0 && bccomp($winAmount, $result2->winAmount, 2) == 0)) {
            $this->addGameOrderCheckError($this->game_type, time(), $params, $startTime, $endTime, $betAmount, $winAmount, $result2->betAmount, $result2->winAmount);
            return false;
        }

        //}
        //金额匹配完全正确
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
            ->selectRaw("id,gameDate,OCode as order_number,betAmount as bet,betAmount as valid_bet,income as win_loss");
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
        //毫秒
        $str_stime = str_pad($startTime, 13, "0", STR_PAD_RIGHT);
        $endTime = '';
        $fields = [
            'timepoint' => $str_stime
        ];
        //游戏类型，分开请求接口

        foreach ($this->dataTypes as $type => $datatype) {
            $fields['dataType'] = $datatype;
            $res = $this->requestOrderParam('/IntegrationService/v3/DataFeeds/gamerounds/', $fields);
            //接口错误
            if (!$res['responseStatus']) {
                break;
            }

            $contents = explode("\n", trim($res['content']));
            array_shift($contents);//排队字段名
            if (count($contents) > 0) {
                $this->updateOrder($contents, $type);
            }

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
     * 更新订单
     * @param array $contents
     * @param string $type 游戏类型 RNG LC
     * @param int $updateStatus
     * @return bool
     */
    public function updateOrder($contents, $type, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($contents as $key => $val) {
            if (empty($val)) {
                continue;
            }
            $val = explode(',', $val);
            //游戏进行中不处理
            if ($val[7] == 'I') {
                continue;
            }
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('OCode', $val[3])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT");
            $startDate = strtotime($val[5]);
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid' => intval(ltrim($val[1], 'game')),
                'OCode' => $val[3],
                'Username' => $val[1],
                'gameDate' => date('Y-m-d H:i:s', $startDate),
                'gameCode' => $val[2],
                'betAmount' => bcmul($val[9], 100, 0),
                'winAmount' => bcmul($val[10], 100, 0),
                'income' => bcmul($val[10] - $val[9], 100, 0),
                'Type' => $val[8],//游戏类型：R - 游戏回合 F – 免费旋转在游戏回合中触发
                'Status' => $val[7],//游戏状态：I - 正在进行中（尚未完成）C - 已完成
                'currency' => $val[11],
                'jackpot' => $val[12],
                'dataType' => $type,
            ];

        }
        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 验证服务接口状态
     * @return array|string
     */
    public function healthCheck()
    {
        $res = $this->requestParam('health/heartbeatCheck', [], false, null);
        if ($res['responseStatus'] && $res['error'] == 0) {
            return true;
        }
        return false;
    }

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @param bool $is_post 是否为post请求
     * @param array $options 请求参数 不加密码
     * @return array|string
     */
    public function requestParam(string $action, array $param, bool $is_post = true, $options = null)
    {
        $config = $this->initConfigMsg($this->game_alias);
        if(!$config){
            $ret = [
                'responseStatus' => false,
                'message' => 'api not config'
            ];
            GameApi::addElkLog($ret, $this->game_alias);
            return $ret;
        }
        $param['secureLogin'] = $config['cagent'];
        $querystring = urldecode(http_build_query($param, '', '&'));
        //echo $querystring.PHP_EOL;
        $hash = $this->GetSignature($param, $config);
        $querystring .= '&hash=' . $hash;
        if ($options) {
            $querystring .= urldecode(http_build_query($options, '', '&'));
        }
        $url = $config['apiUrl'] . $action . '?' . $querystring;
        //echo $url.PHP_EOL;die;
        if ($is_post) {
            $re = Curl::post($url, null, null, null, true);
        } else {
            $re = Curl::get($url, null, true);
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

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @return array|string
     */
    public function requestOrderParam($action, array $param)
    {
        $config = $this->initConfigMsg($this->game_alias);
        $param['login'] = $config['cagent'];
        $param['password'] = $config['key'];
        $querystring = http_build_query($param, '', '&');
        //echo $querystring.PHP_EOL;

        $url = $config['orderUrl'] . $action . '?' . $querystring;
        //echo $url . PHP_EOL;
        $re = Curl::get($url, null, true);
        GameApi::addRequestLog($url, 'PP', $param, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ret = [];
        if ($re['status'] == 200) {
            $ret['content'] = $re['content'];
            $ret['responseStatus'] = true;
        } else {
            $ret['responseStatus'] = false;
            $ret['message'] = $re['content'];
        }
        return $ret;
    }

    public function GetSignature($fields, $config)
    {
        ksort($fields);
        $signature = md5(urldecode(http_build_query($fields, '', '&')) . $config['key']);
        return $signature;
    }

}
