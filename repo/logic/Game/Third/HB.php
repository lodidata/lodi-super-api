<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;
use function GuzzleHttp\Psr7\str;

/**
 * Explain: HB 游戏接口
 *
 * OK
 */
class HB extends GameLogic
{

    protected $game_type = 'HB';
    protected $orderTable = 'game_order_hb';

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
            //为了防止redis里的时间错误,每次都格式化整10分钟
            $startTime = strtotime(date('Y-m-d H:i:00', $r_time));
            $startTime += 1; //去掉最后一秒拉单重复
        } else {
            $last_datetime = \DB::table($this->orderTable)->max('DtCompleted');
            $startTime = $last_datetime ? strtotime($last_datetime) : strtotime(date('Y-m-d H:i:00', $now)) - 3600; //取一小时的数据
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
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("GMT");
        $fields = [
            'DtStartUTC' => date("YmdHis", $startTime),
            'DtEndUTC' => date("YmdHis", $endTime),
            'BrandId' => $config['des_key'],
            'APIKey' => $config['key'],
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('GetBrandCompletedGameResultsV2', $fields, true, true, true);
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
            ->where('DtCompleted', '>=', $start_time)
            ->where('DtCompleted', '<=', $end_time)
            ->selectRaw("sum(Stake) as bet,sum(Stake) as valid_bet,sum(Payout) as win_loss")
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
            'bet' => 'Stake',
            'win' => 'Payout',
            'profit' => 'Payout-Stake',
            'gameDate' => 'DtCompleted'
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
            ->where('DtCompleted', '>=', $start_time)
            ->where('DtCompleted', '<=', $end_time)
            ->where('Username', 'like', "%$user_prefix%")
            ->selectRaw("id,DtCompleted,GameInstanceId as order_number,Stake as bet,Stake as valid_bet,Payout as win_loss");
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
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('GameInstanceId', (string)$val['GameInstanceId'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("GMT");
            $DtStarted = strtotime($val['DtStarted']);
            $DtCompleted = strtotime($val['DtCompleted']);
            date_default_timezone_set($default_timezone);
            $insertData[] = [
                'tid' => intval(ltrim($val['Username'], 'game')),
                'GameInstanceId' => (string)$val['GameInstanceId'],
                'Username' => $val['Username'],
                'Stake' => bcmul($val['Stake'], 100, 0),
                'Payout' => bcmul($val['Payout'], 100, 0),
                'DtStarted' => date('Y-m-d H:i:s', $DtStarted),
                'DtCompleted' => date('Y-m-d H:i:s', $DtCompleted),
                'GameKeyName' => $val['GameKeyName'],
                'GameTypeId' => $val['GameTypeId'],
                'CurrencyCode' => $val['CurrencyCode'] ?? 'PHP',
            ];
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
        $url = rtrim($is_order ? $config['orderUrl'] : $config['apiUrl']) . $action;
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
