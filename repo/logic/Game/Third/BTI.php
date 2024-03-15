<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;
use function GuzzleHttp\Psr7\str;

/**
 * Explain: BTI 游戏接口
 *
 * OK
 */
class BTI extends GameLogic
{

    protected $game_type = 'BTI';
    protected $orderTable = 'game_order_bti';

    /** 获取api请求令牌
     * @param $config
     * @return false|mixed|string
     */
    public function getToken($config)
    {
        $cacheKey = 'game_authorize_bti';
        $token = $this->redis->get($cacheKey);
        if (is_null($token)) {
            $fields = [
                'agentUserName' => $config['cagent'],
                'agentPassword' => $config['key'],
            ];
            $res = $this->requestParam('', $fields, true, true, false);
            if ($res['responseStatus'] && $res['content']['errorCode'] == 0) {
                $this->redis->setex($cacheKey, 15, $res['content']['token']);
                return $res['content']['token'];
            }
            return false;
        }
        return $token;
    }

    /**
     * 同步订单
     * @return bool
     */
    public function synchronousData()
    {
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        $this->orderByTime($r_time, '', true);

    }

    public function orderByTime($r_time, $e_time, $is_redis = false)
    {
        $now = time();
        if ($r_time) {
            $startTime = strtotime(date('Y-m-d H:i:s', $r_time));
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('CreationDate');
            $startTime = $last_datetime ? strtotime($last_datetime) : strtotime(date('Y-m-d H:i:s', $now)) - 3600; //取一小时的数据
        }

        //接数据时间间隔不能超过60分钟
        if ($e_time) {
            $endTime = $e_time;
        } else {
            $endTime = $startTime + 3599;
        }

        if ($endTime > $now) {
            $endTime = $now;
        }

        if ($endTime < $startTime) {
            return true;
        }
        $config = $this->initConfigMsg($this->game_type);
        $token = $this->getToken($config);
        if (!$token) {
            return false;
        }
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("GMT");
        $fields = [
            'From' => date("Y-m-d\TH:i:s", $startTime),
            'To' => date("Y-m-d\TH:i:s", $endTime),
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('?token=' . $token, $fields, true, true, true);
        //接口错误
        if (!$res['responseStatus']) {
            return false;
        }
        if (!empty($res['content'])) {
            $this->updateOrder($res['content']);
        }
        if ($is_redis) {
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
        }
    }

    /**
     * 订单校验
     */
    public function synchronousCheckData()
    {
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('CreationDate', '>=', $start_time)
            ->where('CreationDate', '<=', $end_time)
            ->selectRaw("sum(TotalStake) as bet,sum(ValidStake) as valid_bet,sum(ReturnAmount) as win_loss")
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
            'username' => 'MerchantCustomerID',
            'bet' => 'TotalStake',
            'win' => 'ReturnAmount',
            'profit' => 'PL',
            'gameDate' => 'CreationDate'
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
            ->where('CreationDate', '>=', $start_time)
            ->where('CreationDate', '<=', $end_time)
            ->where('MerchantCustomerID', 'like', "%$user_prefix%")
            ->selectRaw("id,CreationDate,PurchaseID as order_number,TotalStake as bet,ValidStake as valid_bet,ReturnAmount as win_loss");
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
     * 按小时拉取
     * @param $stime
     * @param $etime
     */
    public function orderByHour($stime, $etime)
    {
        return [];
    }

    /**
     * 更新订单
     * @param $data
     * @param int $updateStatus
     * @return bool
     */
    public function updateOrder($data, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data['Bets'] as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('PurchaseID', (string)$val['PurchaseID'])->count()) {
                    continue;
                }
            }

            // APi时区转系统时区
            date_default_timezone_set("GMT");
            $BetSettledDate = strtotime($val['BetSettledDate']);
            $CreationDate = strtotime($val['CreationDate']);
            $SearchDateTime = strtotime($val['SearchDateTime']);
            $UpdateDate = strtotime($val['UpdateDate']);
            date_default_timezone_set($default_timezone);
            $val['BetSettledDate'] = date('Y-m-d H:i:s', $BetSettledDate);
            $val['CreationDate'] = date('Y-m-d H:i:s', $CreationDate);
            $val['SearchDateTime'] = date('Y-m-d H:i:s', $SearchDateTime);
            $val['UpdateDate'] = date('Y-m-d H:i:s', $UpdateDate);

            $val['tid'] = intval(ltrim($val['MerchantCustomerID'], 'game'));

            // 金额格式转为系统格式
            $val['ComboBonusAmount'] = bcmul($val['ComboBonusAmount'], 100, 0);
            $val['PL'] = bcmul($val['PL'], 100, 0);
            $val['RealMoneyAmount'] = bcmul($val['RealMoneyAmount'], 100, 0);
            // Return字段为数据库关键字改为ReturnAmount
            $val['ReturnAmount'] = bcmul($val['Return'], 100, 0);
            unset($val['Return']);
            $val['TotalStake'] = bcmul($val['TotalStake'], 100, 0);
            $val['ValidStake'] = bcmul($val['ValidStake'], 100, 0);

            $val['FreeBet'] = json_encode($val['FreeBet']);
            $val['Selections'] = json_encode($val['Selections']);

            $insertData[] = $val;
        }
        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @return array|string
     */
    public function requestParam(string $action, array $param, bool $is_post = true, $status = true, $is_order = false)
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
        $orderUrl = json_decode($config['orderUrl'], true);
        $url = rtrim($is_order ? $orderUrl['betHistory'] : $orderUrl['getToken']) . $action;
        $headers = array(
            'Content-Type: application/json',
        );
        if ($is_post) {
            $re = Curl::commonPost($url, null, json_encode($param), $headers, $status);
        } else {
            $queryString = http_build_query($param, '', '&');
            if ($queryString) {
                $url .= '?' . $queryString;
            }
            $re = Curl::get($url, null, $status, $headers);
        }
        GameApi::addRequestLog($url, $config['type'], $param, json_encode($re, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ret['content'] = json_decode($re['content'], true);
        if ($re['status'] == 200) {
            $ret['responseStatus'] = true;
        } else {
            $ret['responseStatus'] = false;
            $ret['msg'] = isset($ret['content']) ?? 'api error';
        }
        return $ret;
    }

}
