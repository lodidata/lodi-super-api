<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class FC
 */
class FC extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_fc';
    protected $game_type = 'FC';

    /**
     * 检查接口状态
     * @return bool
     */
    public function checkStatus()
    {
        return true;
    }

    /**
     * 同步第三方游戏订单
     * 拉单延迟30分钟，最大拉单区间30天
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
        $endTime = $now-600;
        if($startTime>=$endTime){
            return false;
        }
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), true);
    }

    /**
     * 更新订单表
     * @param array $data
     * @param int $updateStatus
     * @return bool
     */

    public function updateOrder($data, $updateStatus = 0, $endTime)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('recordID', $val['recordID'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT+4");
            $bdate = strtotime($val['bdate']);//注單建立時間
            date_default_timezone_set($default_timezone);
            //去重，上次拉单最一秒数据重复出现在下次拉单中
            if($bdate == $endTime){
                continue;
            }

            $insertData[] = [
                'tid' => intval(ltrim($val['account'], 'game')),
                'recordID' => $val['recordID'],
                'account' => $val['account'],
                'gameID' => $val['gameID'] ?? 0,
                'gametype' => $val['gametype'] == 7 ? 2 : $val['gametype'],
                'bet' => $val['bet'],
                'winlose' => $val['winlose'],
                'prize' => $val['prize'],
                'jpmode' => $val['jpmode'] ?? 0,
                'jppoints' => $val['jppoints'] ?? 0,
                'jptax' => $val['jptax'] ?? 0,
                'before' => $val['before'] ?? 0,
                'after' => $val['after'] ?? 0,
                'bdate' => date('Y-m-d H:i:s', $bdate),
                'isBuyFeature' => $val['isBuyFeature'] ?? ''
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

        //每次取1天的数据
        $endDay = date("Y-m-d", strtotime('+1 day', strtotime($startDay)));
        $startTime = strtotime($startDay . ' 12:00:00');
        $endTime = strtotime($endDay . ' 12:00:00');
        //正常拉单时间
        $lastTime = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);

        //取1天前的数据 当前过12时,正常拉单时间小于汇总时间
        if (($startDay == $day && date('H') < 12) || (!is_null($lastTime) && $lastTime < $endTime)) {
            return true;
        }
        $params = [
            'Date' => $startDay,
        ];

        $res = $this->requestParam('GetCurrencyReport', $params, true);
        if (!$res['responseStatus']) {
            return false;
        }
        //游戏次数为0
        if (!isset($res['Round']) || $res['Round'] == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }
        //总订单数
        $betCount = $res['Round'];
        //下注金额
        $betAmount = $res['Bet'];
        //输赢金额
        $winAmount = $res['Winlose'];
        if ($betCount == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        $result = \DB::table($this->orderTable)
            ->where('bdate', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('bdate', '<', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(bet) as betAmount, sum(winlose) as winAmount"))->first();
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
            if(!$status){
                return false;
            }
            //时间交换
            $formStartTime = $formEndTime;
        }

        //第二次校验
        $result = \DB::table($this->orderTable)
            ->where('bdate', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('bdate', '<', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(bet) as betAmount, sum(winlose) as winAmount"))->first();
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
        return [];
    }

    /**
     * 游戏统计
     * @param null $date 日期
     * @return bool
     */
    public function queryOperatesOrder($date = null)
    {
        $data = [
            'username' => 'account',
            'bet' => 'bet',
            'win' => 'prize+jppoints',
            'profit' => 'winlose+jppoints',
            'gameDate' => 'bdate'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->game_type, $data, false);
    }

    public function queryHotOrder($user_prefix, $startTime, $endTime, $args = [])
    {
        return [];
    }

    public function queryLocalOrder($user_prefix, $start_time, $end_time, $page = 1, $page_size = 500)
    {
        return [];

    }

    /**
     * 按分钟检索事务
     * @param $stime
     * @param $etime
     * @param bool $is_redis
     * @return bool
     */
    public function orderByTime($stime, $etime, $is_redis = false)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //每次最大拉取区间15 分钟内
        if ($endTime - $startTime > 300) {
            $endTime = $startTime + 300;
        }

        if($startTime > $endTime || $endTime > time()){
            return true;
        }

        //仅捞取两个小时前的注单
        if(time() - $endTime >= 7200) {
            $type = 'GetHistoryRecordList';
        }else{
            $type='GetRecordList';
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $fields = [
            'StartDate' => date('Y-m-d H:i:s', $startTime),
            'EndDate' => date('Y-m-d H:i:s', $endTime),
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam($type, $fields, true);
        
        //夸接口时间区域
        if(false === $res['responseStatus'] && $res['Result'] == 220){
            $res = $this->requestParam('GetHistoryRecordList', $fields, true);
        }elseif (false === $res['responseStatus'] && $res['Result'] == 227){
            $res = $this->requestParam('GetRecordList', $fields, true);
        }

        if (!$res['responseStatus']) {
            return false;
        }
        $this->updateOrder($res['Records'], 0, $endTime);

        if ($is_redis) {
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
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
     * @param bool $is_order 是否为获取注单
     * @return array|string
     */
    public function requestParam(string $action, array $param, $is_order = false)
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
        $url = rtrim($is_order ? $config['orderUrl'] : $config['apiUrl'], '/') . '/' . $action;
        $headers = array(
            "Content-Type: application/x-www-form-urlencoded"
        );

        $json_param = json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $params = [
            'AgentCode' => $config['cagent'],
            'Currency' => $config['currency'],
            'Params' => $this->AESencode($json_param, $config),
            'Sign' => md5($json_param)
        ];
        $queryString = http_build_query($params, '', '&');

        $re = Curl::commonPost($url, null, $queryString, $headers, true);
        if ($re['status'] == 200) {
            $re['content'] = json_decode($re['content'], true);
        }
        GameApi::addRequestLog($url, $this->game_type, ['param' => $param, 'params' => $params], json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ret = [];
        if ($re['status'] == 200) {
            $ret = $re['content'];
            if (isset($ret['Result']) && ($ret['Result'] == 0 || $ret['Result'] == 502)) {
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

    //AES 加密 ECB 模式
    public function AESencode($_values, $config)
    {
        Try {
            $data = openssl_encrypt($_values, 'AES-128-ECB', $config['key'], OPENSSL_RAW_DATA);
            $data = base64_encode($data);
        } Catch (\Exception $e) {
        }
        return $data;
    }

    //AES 解密 ECB 模式
    public function AESdecode($_values, $config)
    {
        $data = null;
        Try {
            $data = openssl_decrypt(base64_decode($_values), 'AES-128-ECB', $config['key'], OPENSSL_RAW_DATA);
        } Catch (\Exception $e) {
        }
        return $data;
    }

}
