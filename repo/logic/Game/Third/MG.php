<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;
use function GuzzleHttp\Psr7\str;

/**
 * Explain: MG 游戏接口
 *
 * OK
 */
class MG extends GameLogic
{

    protected $game_type = 'MG';
    protected $orderTable = 'game_order_mg';
    protected $jwtToken = '';


    public function getJWTToken($config)
    {
        $this->jwtToken = $this->redis->get('game_authorize_mg');
        if (!$this->jwtToken) {
            $fields = [
                'client_id' => $config['cagent'],
                'client_secret' => $config['key'],
                'grant_type' => 'client_credentials'
            ];
            $res = $this->requestParam('/connect/token', $fields, true, true, false, true);
            if ($res['responseStatus']) {
                $this->jwtToken = $res['data']['access_token'];
                $this->redis->setex('game_authorize_mg', 3600, $res['data']['access_token']);
            }
        }
        return $this->jwtToken;
    }

    /**
     * 同步订单
     * @return bool
     */
    public function synchronousData()
    {
        $lastBetUid = $this->redis->get(CacheKey::$perfix['gameGetOrderLastBetUid'] . $this->game_type); //上次查询的最后一笔betUID
        $this->orderByTime($lastBetUid,'', true);
    }

    /**
     * 按分钟检索事务
     * @param $stime
     * @param $etime
     * @param bool $is_redis
     * @return bool
     */
    public function orderByTime($stime, $etime='', $is_redis = false)
    {
        $fields = [
            'limit' => 10000,
        ];
        if ($stime) {
            $fields['startingAfter'] = $stime;
        }
        $config = $this->initConfigMsg($this->game_type);
        $res = $this->requestParam('/agents/' . $config['cagent'] . '/bets', $fields, false);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        if (!empty($res['data'])) {
            $this->updateOrder($res['data']);
            //记录拉取订单最后一笔的betUID到redis
            if ($is_redis) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastBetUid'] . $this->game_type, $res['data'][count($res['data']) - 1]['betUID']);
            }
        }
        return true;
    }

    public function requestParam(string $action, array $param, bool $is_post = true, $status = true, $is_header = true, $is_login = false)
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
        $url = rtrim($is_login ? $config['loginUrl'] : $config['apiUrl'] . '/api/v1', '/') . $action;
        $headers = [];
        if ($is_header) {
            $token = $this->getJWTToken($config);
            if (!$token) {
                return [
                    'responseStatus' => false,
                    'msg' => 'get jwt token error'
                ];
            }

            $headers = array(
                "Authorization: Bearer " . $token
            );
        }
        if ($is_post) {
            $re = Curl::commonPost($url, null, http_build_query($param), $headers, $status);
        } else {
            $queryString = http_build_query($param, '', '&');
            if ($queryString) {
                $url .= '?' . $queryString;
            }
            $re = Curl::get($url, null, true, $headers);
        }
        if ($re['status'] == 200) {
            $re['content'] = json_decode($re['content'], true);
        }
        GameApi::addRequestLog($url, $this->game_type, $param, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ret = [];
        if ($re['status'] == 200) {
            $ret['data'] = $re['content'];
            $ret['responseStatus'] = true;
        } else {
            $ret['responseStatus'] = false;
            $ret['message'] = $re['content'];
        }
        return $ret;
    }


    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('createdTime', '>=', $start_time)
            ->where('createdTime', '<=', $end_time)
            ->selectRaw("sum(betAmount) as bet,sum(betAmount) as valid_bet,sum(payoutAmount) as win_loss")
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
            'username' => 'playerId',
            'bet' => 'betAmount',
            'win' => 'payoutAmount',
            'profit' => 'payoutAmount-betAmount',
            'gameDate' => 'createdTime'
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
            ->where('createdTime', '>=', $start_time)
            ->where('createdTime', '<=', $end_time)
            ->where('playerId', 'like', "%$user_prefix%")
            ->selectRaw("id,createdTime,betUID,betAmount,betAmount as valid_bet,payoutAmount as win_loss");
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
     * 更新订单
     * @param $data
     * @param int $updateStatus
     * @return bool
     */
    public function updateOrder($data, $updateStatus = 0)
    {
        $gameList = $this->redis->get('super_game_mg_3th');
        if(is_null($gameList) || $gameList =="null" || empty($gameList)){
            $game3th = \DB::table('game_3th')->whereIn('game_id', [118, 119, 121, 122, 123])->get(['kind_id', 'game_id'])->toArray();
            foreach($game3th as $val){
                $val = (array) $val;
                $gameList[$val['kind_id']] = $val['game_id'];
            }
            $this->redis->setex('super_game_mg_3th', 86400, json_encode($gameList));
        }else{
            $gameList = json_decode($gameList, true);
        }

        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('betUID', (string)$val['betUID'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT");
            $createTime = strtotime($val['createdDateUTC']);
            $gameEndTime = strtotime($val['gameEndTimeUTC']);
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid' => intval(ltrim($val['playerId'], 'game')),
                'betUID' => $val['betUID'],
                'playerId' => $val['playerId'],
                'betAmount' => bcmul($val['betAmount'], 100, 0),
                'payoutAmount' => bcmul($val['payoutAmount'], 100, 0),
                'createdTime' => date('Y-m-d H:i:s', $createTime),
                'gameEndTime' => date('Y-m-d H:i:s', $gameEndTime),
                'gameCode' => $val['gameCode'],
                'game_id' => isset($gameList[$val['gameCode']]) ? $gameList[$val['gameCode']] : 118,
                'currency' => $val['currency'] ?? 'PHP',
            ];
        }
        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

}
