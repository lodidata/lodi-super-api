<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Logic;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Explain: JOKER 游戏接口  真人，电子，捕鱼游戏
 *
 * OK
 */
class JOKER extends GameLogic
{
    protected $game_type = 'JOKER';
    protected $orderTable = 'game_order_joker';

    /**
     * 第三方拉单
     * @return bool
     */
    public function synchronousData()
    {
        $now = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if (!$r_time) {
            $last_datetime = \DB::table($this->orderTable)->max('gameDate');
            $r_time = $last_datetime ? strtotime($last_datetime) : $now - 20 * 60;
        }
        //为了防止redis里的时间错误,每次都格式化整10分钟
        $begin_time = strtotime(date('Y-m-d H:i:00', $r_time));
        //只能拉整10分钟的单 比如 14:10 到 14:20
        $left_minute = date('i', $begin_time) % 10;
        $startTime = $begin_time - $left_minute * 60;

        $endTime = $startTime + 10 * 60;
        //只能拉10分钟前的单
        if ($now - $endTime < 10 * 60) {
            return false;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $stime = date('Y-m-d H:i:s', $startTime);//北京时间转为GMT(UTC+0)日期
        $etime = date('Y-m-d H:i:s', $endTime);//北京时间转为GMT(UTC+0)日期
        date_default_timezone_set($default_timezone);
        $fields = [
            'Method' => 'TSM',
            'StartDate' => $stime,
            'EndDate' => $etime,
            'NextId' => '',
        ];
        while (1) {
            $res = $this->requestParam($fields);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            if (!isset($res['data']) || empty($res['data']) || (empty($res['data']['Game']))) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
                break;
            }
            $this->updateOrder($res['data']['Game']);
            //奖励
            if (isset($res['data']['Jackpot'])) {
                $this->updateOrder($res['data']['Jackpot']);
            }
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            if (!$res['nextId']) {
                break;
            }
            $fields['NextId'] = $res['nextId'];
        }
    }

    /**
     * 第三方注单同步
     * 延期10分钟
     * 每页限制 1500 条记录
     * @return bool
     * @throws \Exception
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

        $endTime = $startTime + 3600;
        //当前小时不拉数据
        if (date('H', $now) == date('H', $endTime) || (date('Y-m-d H', $endTime) == date('Y-m-d H', $startTime)) || $endTime > $now) {
            return true;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $fields = [
            'Method' => 'TS',
            'StartDate' => date('Y-m-d H:00', $startTime),
            'EndDate' => date('Y-m-d H:00', $endTime),
            'NextId' => '',
        ];
        date_default_timezone_set($default_timezone);
        //按页码循环
        while (1) {
            $res = $this->requestParam($fields);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            if (!isset($res['data']) || empty($res['data']) || (!isset($res['data']['Game']) || empty($res['data']['Game']))) {
                break;
            }

            $this->updateOrder($res['data']['Game']);
            //奖励
            if (isset($res['data']['Jackpot'])) {
                $this->updateOrder($res['data']['Jackpot']);
            }

            if (!$res['nextId']) {
                break;
            }
            $fields['NextId'] = $res['nextId'];
        }

        $this->redis->set(CacheKey::$perfix['gameOrderCheckTime'] . $this->game_type, $endTime);
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
     * 6.2.按分钟检索事务
     * @param $stime
     * @param $etime
     */
    public function orderByTime($stime, $etime)
    {
        $fields = [
            'Method' => 'TSM',
            'StartDate' => $stime,
            'EndDate' => $etime,
            'NextId' => '',
        ];
        while (1) {
            $res = $this->requestParam($fields);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            if (!isset($res['data']) || empty($res['data']) || (empty($res['data']['Game']))) {
                break;
            }
            $this->updateOrder($res['data']['Game']);
            //奖励
            if (isset($res['data']['Jackpot'])) {
                $this->updateOrder($res['data']['Jackpot']);
            }
            if (!$res['nextId']) {
                break;
            }
            $fields['NextId'] = $res['nextId'];
        }
    }

    /**
     * 按小时拉取
     * @param $stime
     * @param $etime
     * @return bool
     */
    public function orderByHour($stime, $etime)
    {
        $fields = [
            'Method' => 'TSM',
            'StartDate' => $stime,
            'EndDate' => $etime,
            'NextId' => '',
        ];
        while (1) {
            $res = $this->requestParam($fields);
            //接口错误
            if (!$res['responseStatus']) {
                return false;
            }
            if (!isset($res['data']) || empty($res['data']) || (empty($res['data']['Game']))) {
                break;
            }
            $this->updateOrder($res['data']['Game']);
            //奖励
            if (isset($res['data']['Jackpot'])) {
                $this->updateOrder($res['data']['Jackpot']);
            }
            if (!$res['nextId']) {
                break;
            }
            $fields['NextId'] = $res['nextId'];
        }
    }

    /**
     * 更新订单
     * @param $data
     * @param int $updateStatus
     * @return bool
     */
    public function updateOrder($data, $updateStatus = 0)
    {
        $gameList = $this->redis->get('super_game_joker_3th');
        if(is_null($gameList) || $gameList =="null" || empty($gameList)){
            $game3th = \DB::table('game_3th')->whereIn('game_id', [59,60,61])->get(['kind_id', 'game_id'])->toArray();
            foreach($game3th as $val){
                $val = (array) $val;
                $gameList[$val['kind_id']] = $val['game_id'];
            }
            $this->redis->setex('super_game_joker_3th', 86400, json_encode($gameList));
        }else{
            $gameList = json_decode($gameList, true);
        }

        $insertData = [];
        foreach ($data as $val) {
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('OCode', $val['OCode'])->count()) {
                    continue;
                }
            }
            $insertData[] = [
                'tid' => intval(ltrim($val['Username'], 'GAME')),
                'OCode' => $val['OCode'],
                'Username' => strtolower($val['Username']),
                'gameDate' => date('Y-m-d H:i:s', strtotime($val['Time'].'+1 hour')),
                'gameCode' => $val['GameCode'],
                'RoundID' => isset($val['RoundID']) ? $val['RoundID'] : '',
                'betAmount' => bcmul($val['Amount'], 100, 0),
                'winAmount' => bcmul($val['Result'], 100, 0),
                'income' => bcmul($val['Result'] - $val['Amount'], 100, 0),
                'Type' => $val['Type'],
                'Description' => $val['Description'],
                'game_id' => isset($gameList[$val['GameCode']]) ? $gameList[$val['GameCode']] : 59
            ];
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 发送请求
     * @param array $param 请求参数
     * @return array|string
     */
    public function requestParam(array $param)
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

        $param['Timestamp'] = time();
        $signature = $this->GetSignature($param, $config);
        $url = $config['orderUrl'] . '?' . 'AppID=' . $config['cagent'] . '&Signature=' . $signature;
        $re = Curl::post($url, '', $param, '', true);
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

    private function GetSignature($fields, $config)
    {
        ksort($fields);
        $signature = urlencode(base64_encode(hash_hmac("sha1", urldecode(http_build_query($fields, '', '&')), $config['key'], TRUE)));

        return $signature;
    }

}
