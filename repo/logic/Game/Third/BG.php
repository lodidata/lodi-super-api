<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class BG
 */
class BG extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_bg';
    protected $game_type = 'BG';
    protected $gameCategory = 'LIVE';

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
     * 拉单延迟5分钟，最大拉单区间30天
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
            //TODO BY补11月单
            if($this->game_type == 'BGBY'){
                $orderTime = \DB::table($this->orderTable)->where('orderTime','>', '2022-11-01')->where('gameCategory', $this->gameCategory)->max('orderTime');
                $startTime = $orderTime ? strtotime($orderTime) : strtotime('2022-11-01');
            }
        }
        $lastTime = strtotime(date('Y-m-d H:i:00', $now - 5*60));
        $endTime = $now;
        if($endTime > $lastTime){
            $endTime = $lastTime;
        }
        if($endTime <= $startTime){
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
    public function updateOrder($data, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('orderId',(string) $val['orderId'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT+4");
            $orderTime = strtotime($val['orderTime']);//注單建立時間
            $lastUpdateTime = strtotime($val['lastUpdateTime']); //注單結算時間
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid' => intval(ltrim(strtolower($val['loginId']), 'game')),
                'orderId' => !empty((string)$val['orderId']) ? (string)$val['orderId'] : $val['betId'],
                'loginId' => $val['loginId'],
                'moduleId' => $val['moduleId'] ?? 0,
                'moduleName' => $val['moduleName'] ?? "",
                'gameId' => !empty($val['gameId']) ? $val['gameId'] : $val['gameType'],
                'gameName' => $val['gameName'] ?? "",
                'orderStatus' => $val['orderStatus'] ?? 0,
                'bAmount' => isset($val['bAmount']) ? (abs($val['bAmount']) ?? 0) : (abs($val['betAmount']) ?? 0),
                'aAmount' => isset($val['aAmount']) ? ($val['aAmount'] ?? 0.00) : ($val['calcAmount'] ?? 0.00),
                'orderFrom' => $val['orderFrom'],
                'orderTime' => date('Y-m-d H:i:s', $orderTime),
                'lastUpdateTime' => !empty($val['aAmount']) ? date('Y-m-d H:i:s', $lastUpdateTime) : date('Y-m-d H:i:s', $orderTime),
                'fromIp' => !empty($val['fromIp']) ? $val['fromIp'] : "0.0.0.0",
                'issueId' => $val['issueId'],
                'playId' => $val['playId'] ?? 0,
                'playName' => $val['playName'] ?? "",
                'playNameEn' => $val['playNameEn'] ?? "",
                'validBet' => isset($val['validBet']) ? (abs($val['validBet']) ?? 0) : ($val['validAmount'] ?? 0.00),
                'payment' => isset($val['payment']) ? ($val['payment'] ?? 0.00) : ($val['payout'] ?? 0.00),
                'gameCategory' => $this->gameCategory
            ];
            unset($val);
        }

        return $this->addGameOrders('BG', $this->orderTable, $insertData);

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
            $startTime = $startTime + 3600;
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $startTime);
            $this->redis->set(CacheKey::$perfix['gameOrderCheckCount'] .  $this->game_type, 1);
        }

        $endTime = strtotime(date('Y-m-d H:00:00', $now - 7200)); //取2小时前的数据，延迟30分钟
        //每次最大拉取区间1 小时内
        if ($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }
        //当前小时不拉数据
        if (date('Y-m-d H', $now) == date('Y-m-d H', $endTime) || (date('Y-m-d H', $endTime) == date('Y-m-d H', $startTime)) || $endTime > $now) {
            return true;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        if($this->gameCategory == 'LIVE') {
            $params = [
                'moduleId' => 2,
                'startTime' => date('Y-m-d H:i:s', $startTime),
                'endTime' => date('Y-m-d H:i:s', $endTime),
                'bySettle' => 1
            ];
            $action = 'open.sn.order.sum';
        } elseif($this->gameCategory == 'BY') {
            $config = $this->initConfigMsg('BG');
            $lobby = json_decode($config['lobby'], true);
            $params = [
                'startTime' => date('Y-m-d H:i:s', $startTime),
                'endTime' => date('Y-m-d H:i:s', $endTime),
                'gameType' => 1,
                'agentLoginId' => $lobby['agent'],
            ];
            $action = 'open.bg.user.order.sum';
        }

        date_default_timezone_set($default_timezone);

        $res = $this->requestParam($action, $params, false, true);

        if (!$res['responseStatus']) {
            return false;
        }
        if (isset($res['result']) && isset($res['result']['orderCount']) && $res['result']['orderCount']==0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }
        //总订单数
        $betCount = !empty($res['result']['orderCount']) ? $res['result']['orderCount'] : $res['result']['stats']['ordercount'];
        //下注金额
        $betAmount = !empty(abs($res['result']['orderAmount'])) ? abs($res['result']['orderAmount']) : $res['result']['stats']['orderamount'];
        //输赢金额
        $winAmount = !empty($res['result']['paymentAmount']) ? $res['result']['paymentAmount'] : $res['result']['stats']['calcamount'];

        if ($betAmount == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
            return true;
        }

        $result = \DB::table($this->orderTable)
            //->where('moduleId', 2)
            ->where('orderTime', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('orderTime', '<=', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(bAmount) as betAmount, sum(payment) as winAmount"))
            ->where('gameCategory', '=', $this->gameCategory)->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
                return true;
            }
        }
        //订单数不对补单
        $this->orderByTime(date("Y-m-d H:i:s", $startTime), date("Y-m-d H:i:s", $endTime));

        //第二次校验
        $result = \DB::table($this->orderTable)
            //->where('moduleId', 2)
            ->where('orderTime', '>=', date("Y-m-d H:i:s", $startTime))
            ->where('orderTime', '<=', date("Y-m-d H:i:s", $endTime))
            ->select(\DB::raw("count(0) as betCount,sum(bAmount) as betAmount, sum(payment) as winAmount"))
            ->where('gameCategory', '=', $this->gameCategory)->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
                return true;
            }
        }
        //订单数不对
        $this->addGameOrderCheckError('BG', $now, $params, date("Y-m-d H:i:s", $startTime), date("Y-m-d H:i:s", $endTime), $betAmount, $winAmount, $result->betAmount, $result->winAmount);

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
            'username' => 'loginId',
            'bet' => 'bAmount',
            'win' => 'aAmount',
            'profit' => 'payment',
            'gameDate' => 'orderTime'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, 'BG', $data, false);
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
        $config = $this->initConfigMsg('BG');
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //拉单去重，开始时间+1秒
        //$startTime = $startTime + 1;
        //每次最大拉取区间24 小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $lobby = json_decode($config['lobby'], true);
        $fields = [
            'agentLoginId' => $lobby['agent'],
            'pageSize' => 500, //最大500
            'startTime' => date('Y-m-d H:i:s', $startTime), //注單更新起始時間
            'endTime' => date('Y-m-d H:i:s', $endTime),
            'pageIndex' => 1,
        ];
        if($this->game_type == 'BGBY') {
            $fields['gameType'] = 1;
        }
        date_default_timezone_set($default_timezone);

        switch ($this->game_type) {
            case 'BG':
                $method = 'open.order.agent.query';
                break;
            case 'BGBY':
                $method = 'open.order.bg.agent.query';
                break;
            default:
                $method = 'open.order.agent.query';
        }

        while (1) {
            $res = $this->requestParam($method, $fields, true);
            if (!$res['responseStatus']) {
                return false;
            }
            if ($res['result']['pageSize'] == 0) {
                break;
            }

            $this->updateOrder($res['result']['items']);
            if ($fields['pageIndex'] * $fields['pageSize'] >= $res['result']['total']) {
                break;
            }
            //下一页
            $fields['pageIndex']++;
        }
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
     * @param string $method
     * @param array $params 请求参数
     * @param bool $secret_code 代理商密钥
     * @param bool $secret_key secretKey
     * @param array $md5Params 加密字段
     * @return array|string
     */
    public function requestParam(string $method, array $params, $secret_code = true, $secret_key = false, $md5Params = [])
    {
        $config = $this->initConfigMsg('BG');
        if (!$config) {
            $ret = [
                'responseStatus' => false,
                'message' => 'api not config'
            ];
            GameApi::addElkLog($ret, 'BG');
            return $ret;
        }
        //厅代码
        $sn = $config['cagent'];
        //自建代理
        $lobby = json_decode($config['lobby'],true);
        $uuid = uniqid();
        //生成随机字符串
        $random = str_replace('.','', sprintf('%.6f', microtime(TRUE)));
        //代理商密钥 password为代理账号的密码
        $secretCode = base64_encode(sha1($lobby['agentpwd'], true));

        $md5str = $random . $sn;

        //登录ID加密
        if(!empty($md5Params)){
            foreach($md5Params as $field){
                $md5str .= $params[$field];
            }
        }
        //代理商密钥
        if($secret_code){
            $md5str .= $secretCode;
        }
        //密钥（secretKey）
        if($secret_key){
            $md5str .= $config['key'];
        }

        $digest = md5($md5str);

        $params['random'] = $random;
        $params['sn'] = $sn;
        if($method=='open.sn.order.sum'){
            $params['sign'] = $digest;
        }else{
            $params['digest'] = $digest;
        }


        $url = rtrim($config['apiUrl'], '/') . '/' . $method;

        $postData = [
            'id' => $uuid,
            'method' => $method,
            'params' => $params,
            'jsonrpc' => '2.0',
        ];

        $re = Curl::post($url, null, $postData, null, true);
        if ($re['status'] != 200) {
            $ret['responseStatus'] = false;
            $ret['networkStatus'] = $re['status'];
            $ret['error']['message'] = $re['content'];
            GameApi::addRequestLog($url, 'BG', $postData, json_encode($re, JSON_UNESCAPED_UNICODE));
        } else {
            $ret = json_decode($re['content'], true);
            $ret['networkStatus'] = $re['status'];
            if (isset($ret['error']) && is_array($ret['error'])) {
                $ret['responseStatus'] = false;
            } else {
                $ret['responseStatus'] = true;
            }
            GameApi::addRequestLog($url, 'BG', $postData, json_encode($ret, JSON_UNESCAPED_UNICODE));
        }
        return $ret;
    }

}
