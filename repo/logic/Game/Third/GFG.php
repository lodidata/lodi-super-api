<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class GFG
 */
class GFG extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_gfg';
    protected $game_type = 'GFG';

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
            $startTime = strtotime(date('Y-m-d H:i:00', $r_time));
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('gameDate');
            $startTime = $last_datetime ? strtotime($last_datetime) : strtotime(date('Y-m-d H:i:00', $now)) - 720;
        }
        $endTime = $now;
        if($startTime > $now){
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
        $config = $this->initConfigMsg($this->game_type);

        $gameList = $this->redis->get('super_game_gfg_3th');
        if(is_null($gameList) || $gameList =="null" || empty($gameList)){
            $game3th = \DB::table('game_3th')->whereIn('game_id', [136,145,146])->get(['kind_id', 'game_id'])->toArray();
            foreach($game3th as $val){
                $val = (array) $val;
                $gameList[$val['kind_id']] = $val['game_id'];
            }
            $this->redis->setex('super_game_gfg_3th', 86400, json_encode($gameList));
        }else{
            $gameList = json_decode($gameList, true);
        }

        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('OCode', $val['roundId'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT-8");
            $gameDate = strtotime($val['roundBeginTime']);//注單建立時間
            $gameEndTime =  strtotime($val['roundEndTime']);
            date_default_timezone_set($default_timezone);

            $user_name = ltrim($val['account'], $config['cagent'].'_');

            $insertData[] = [
                'tid' => intval(ltrim($user_name, 'game')),
                'OCode' => $val['roundId'],
                'Username' => $user_name,
                'gameDate' => date('Y-m-d H:i:s', $gameDate),
                'gameCode' => $val['gameId'],
                'betAmount' => bcmul($val['bet'], 100, 0),
                'winAmount' => bcmul($val['bet']+$val['lose'], 100, 0),
                'income' => bcmul($val['lose'], 100, 0),
                'gameEndTime' => date('Y-m-d H:i:s', $gameEndTime),
                'validAmount' => bcmul($val['validBet'], 100, 0),
                'fee' => bcmul($val['fee'], 100, 0),
                'filedId' => $val['fieldId'],
                'filedName' => $val['filedName'],
                'game_menu_id' => isset($gameList[$val['gameId']]) ? $gameList[$val['gameId']] : 136,
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
        return [];

    }

    /**
     * 按分钟检索事务 15天
     * @param $stime
     * @param $etime
     * @param bool $is_redis
     * @return bool
     */
    public function orderByTime($stime, $etime, $is_redis = false)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //每次最大拉取区间15天 小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }


        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $fields = [
            'startTime' => date('Y-m-d H:i:s', $startTime),
            'endTime' => date('Y-m-d H:i:s', $endTime),
            'page' => 0,
            'size' => 100,
        ];
        date_default_timezone_set($default_timezone);
        while (1) {
            $res = $this->requestParam('takeBetLogs', $fields);
            if (!$res['responseStatus']) {
                break;
            }
            if ($res['data']['total'] == 0) {
                if ($is_redis) {
                    $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
                }
                break;
            }

            $this->updateOrder($res['data']['bets']);
            if ($is_redis) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            }
            if (($fields['page']+1) * $fields['size'] >= $res['data']['total']) {
                break;
            }
            //下一页
            $fields['page']++;
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
     * @param array $params 请求参数
     * @return array|string
     */
    public function requestParam($action, array $params)
    {
        $config = $this->initConfigMsg($this->game_type);
        if (!$config) {
            $ret = [
                'responseStatus' => false,
                'message' => 'api not config'
            ];
            GameApi::addElkLog($ret, $this->game_type);
            return $ret;
        }
        $params['agent'] = $config['cagent'];
        $params['companyKey'] = $config['des_key'];
        $params['timestamp'] = sprintf('%.3f', microtime(TRUE));
        $requestBodyString = json_encode($params, JSON_UNESCAPED_UNICODE);
        $authorization = md5($requestBodyString . $config['key']);

        //Send the Http request
        $url = $config['orderUrl'] . $action;
        //echo $url.PHP_EOL;
        $header = [
            "Content-Type: application/json",
            "Authorization:" . $authorization,
        ];
        //var_dump($header);die;
        $re = curl::commonPost($url, null,  $requestBodyString, $header, true );
        //var_dump($result);die;
        if ($re['status'] != 200) {
            $ret['responseStatus'] = false;
            $ret['netWorkStatus'] = $re['status'];
            $ret['message'] = $re['content'];
        } else {
            $ret = json_decode($re['content'], true);
            $ret['networkStatus'] = $re['status'];
            if (isset($ret['code']) && $ret['code'] === 0) {
                $ret['responseStatus'] = true;
            } else {
                $ret['responseStatus'] = false;
            }
        }

        GameApi::addRequestLog($url, $this->game_type, $params, json_encode($ret, JSON_UNESCAPED_UNICODE));
        return $ret;
    }


}
