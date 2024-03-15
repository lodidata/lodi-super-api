<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class AVIA
 */
class AVIA extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_avia';
    protected $game_type = 'AVIA';

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
            $startTime = strtotime(date('Y-m-d', strtotime('-1 day'))); //取1天前的数据
        }
        $endTime = $now;
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
                if (\DB::table($this->orderTable)->where('OrderID', $val['OrderID'])->count()) {
                    continue;
                }
            }
            //订单状态 None 等待开奖,Cancel 比赛取消,Win 赢,Lose 输,Revoke 无效订单
            if (!in_array($val['Status'], ['Win', 'Lose'])) {
                continue;
            }
            date_default_timezone_set("Etc/GMT-8");
            $CreateAt = strtotime($val['CreateAt']);//建立時間
            $UpdateAt = strtotime($val['UpdateAt']);//更新时间
            $RewardAt = strtotime($val['RewardAt']);//派奖时间
            $ResultAt = isset($val['ResultAt']) ? strtotime($val['ResultAt']) : $RewardAt;//结果时间
            $StartAt = isset($val['StartAt']) ? strtotime($val['StartAt']) : '';
            $EndAt = isset($val['EndAt']) ? strtotime($val['EndAt']) : '';
            date_default_timezone_set($default_timezone);
            $val['CreateAt'] = date('Y-m-d H:i:s', $CreateAt);
            $val['UpdateAt'] = date('Y-m-d H:i:s', $UpdateAt);
            $val['RewardAt'] = date('Y-m-d H:i:s', $RewardAt);
            $val['ResultAt'] = date('Y-m-d H:i:s', $ResultAt);
            $val['Platform'] = isset($val['Platform']) ? join('-', $val['Platform']) : '';

            switch ($val['Type']) {
                case 'Single':
                    //电竞单关订单
                    $val['Details'] = [
                        [
                            'CateID' => $val['CateID'],
                            'Category' => $val['Category'],
                            'LeagueID' => $val['LeagueID'],
                            'League' => $val['League'],
                            'MatchID' => $val['MatchID'],
                            'Match' => $val['Match'],
                            'BetID' => $val['BetID'],
                            'Bet' => $val['Bet'],
                            'Content' => $val['Content'],
                            'Result' => $val['Result'],
                            'IsLive' => $val['IsLive'],
                            'StartAt' => date('Y-m-d H:i:s', $StartAt),
                            'EndAt' => date('Y-m-d H:i:s', $EndAt),
                        ]
                    ];
                    unset($val['CateID'], $val['Category'], $val['LeagueID'], $val['League'], $val['MatchID'], $val['Match'], $val['BetID'], $val['Bet'], $val['Content'], $val['Result'], $val['IsLive'], $val['StartAt'], $val['EndAt']);
                    break;
                case 'Combo':
                    //电竞串关订单
                    break;
                case 'Smart':
                    //趣味游戏订单
                    $val['Details'] = [
                        [
                            'Code' => $val['Code'],
                            'Index' => $val['Index'],
                            'Player' => $val['Player'],
                            'Content' => $val['Content'],
                            'Result' => $val['Result'],
                        ]
                    ];
                    unset($val['Code'], $val['Index'], $val['Player'], $val['Content'], $val['Result']);
                    break;
                case 'Anchor':
                    //主播订单
                    $val['Details'] = [
                        [
                            'CateID' => $val['CateID'],
                            'Category' => $val['Category'],
                            'AnchorID' => $val['AnchorID'],
                            'Anchor' => $val['Anchor'],
                            'Code' => $val['Code'],
                            'BetID' => $val['BetID'],
                            'Bet' => $val['Bet'],
                            'Content' => $val['Content'],
                            'Index' => $val['Index'],
                        ]
                    ];
                    unset($val['CateID'], $val['Category'], $val['AnchorID'], $val['Anchor'], $val['Code'], $val['BetID'], $val['Bet'], $val['Content'], $val['Index']);
                    break;
                case 'VisualSport':
                    //虚拟电竞订单
                    $val['Details'] = [
                        [
                            'CateID' => $val['CateID'],
                            'Category' => $val['Category'],
                            'MatchID' => $val['MatchID'],
                            'Match' => $val['Match'],
                            'BetID' => $val['BetID'],
                            'Bet' => $val['Bet'],
                            'Content' => $val['Content'],
                            'Result' => $val['Result'],
                            'Index' => $val['Index'],
                        ]
                    ];
                    unset($val['CateID'], $val['Category'], $val['MatchID'], $val['Match'], $val['BetID'], $val['Bet'], $val['Content'], $val['Result'], $val['Index']);
                    break;
            }

            $val['Details'] = json_encode($val['Details'], JSON_UNESCAPED_UNICODE);
            $val['tid'] = intval(ltrim($val['UserName'], 'game'));

            $insertData[] = $val;
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
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");

        $startAt = str_replace('-', '/', $startDay);
        $params = [
            'StartAt' => $startAt,
            'EndAt' => $startAt,
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/api/log/SiteReport', $params, true);
        if (!$res['responseStatus']) {
            return false;
        }
        if (!isset($res['info']) || $res['info']['RecordCount']) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }
        //总订单数
        $betCount = 0;
        //下注金额
        $betAmount = 0;
        //输赢金额
        $winAmount = 0;
        foreach ($res['info']['list'] as $val) {
            $betAmount = bcadd($betAmount, $val['BetMoney'], 2);
            $betCount += $val['OrderCount'];
            $winAmount = bcadd($winAmount, $val['Money'], 2);
        }

        if ($betAmount == 0) {
            $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
            return true;
        }

        $result = \DB::table($this->orderTable)
            ->where('RewardAt', '>=', $startDay)
            ->where('RewardAt', '<', $endDay)
            ->select(\DB::raw("count(0) as betCount,sum(BetAmount) as betAmount, sum(Money) as winAmount"))->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
                return true;
            }
        }
        //订单数不对补单
        $this->orderByTime($startDay, $endDay);

        //第二次校验
        $result = \DB::table($this->orderTable)
            ->where('RewardAt', '>=', $startDay)
            ->where('RewardAt', '<', $endDay)
            ->select(\DB::raw("count(0) as betCount,sum(BetAmount) as betAmount, sum(Money) as winAmount"))->first();
        if ($result) {
            if (bccomp($result->betCount, $betCount, 0) == 0 && bccomp($betAmount, $result->betAmount, 2) == 0 && bccomp($winAmount, $result->winAmount, 2) == 0) {
                $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endDay);
                return true;
            }
        }
        //订单数不对
        $this->addGameOrderCheckError($this->game_type, time(), $params, $startDay, $endDay, $betAmount, $winAmount, $result->betAmount, $result->winAmount);

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
            'username' => 'UserName',
            'bet' => 'BetAmount',
            'win' => 'Money+BetAmount',
            'profit' => 'Money',
            'gameDate' => 'RewardAt'
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
        //每次最大拉取区间24小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $fields = [
            'Type' => 'RewardAt',//UpdateAt	订单更新时间（默认值）CreateAt订单创建时间,ResultAt赛果产生时间,RewardAt派奖时间,UserName根据用户名查询
            'StartAt' => date('Y-m-d H:i:s', $startTime),
            'EndAt' => date('Y-m-d H:i:s', $endTime),
            'PageIndex' => 1,
            'PageSize' => 1000,
        ];
        date_default_timezone_set($default_timezone);
        while (1) {
            $res = $this->requestParam('/api/log/get', $fields, true);
            //接口报错
            if (!$res['responseStatus']) {
                return false;
            }
            //无数据
            if ($res['info']['RecordCount'] == 0) {
                if($is_redis){
                    $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
                }
                break;
            }
            $this->updateOrder($res['info']['list']);
            if($is_redis){
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            }
            //下一页
            if ($res['info']['RecordCount'] <= $fields['PageIndex'] * $fields['PageSize']) {
                break;
            }
            $fields['PageIndex']++;
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
        $url = rtrim($is_order ? $config['orderUrl'] : $config['apiUrl'], '/') . $action;
        $headers = array(
            "Authorization: " . $config['key'],
            "Content-Language: ENG",
            "Content-Type: application/x-www-form-urlencoded"
        );

        $queryString = http_build_query($param, '', '&');

        $re = Curl::commonPost($url, null, $queryString, $headers, true);
        if ($re['status'] != 200) {
            $ret['responseStatus'] = false;
            $ret['status'] = $re['status'];
            $ret['msg'] = $re['content'];
            GameApi::addRequestLog($url, $this->game_type, $param, json_encode($re, JSON_UNESCAPED_UNICODE));
        } else {
            $ret = json_decode($re['content'], true);
            if (isset($ret['success']) && $ret['success'] == '1') {
                $ret['responseStatus'] = true;
            } else {
                $ret['responseStatus'] = false;
            }
            GameApi::addRequestLog($url, $this->game_type, $param, json_encode($ret, JSON_UNESCAPED_UNICODE));
        }
        return $ret;
    }

}
