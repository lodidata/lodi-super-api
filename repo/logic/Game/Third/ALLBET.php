<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class ALLBET
 */
class ALLBET extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_allbet';
    protected $game_type = 'ALLBET';

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
     * 延迟通常不会超过5分钟，最大拉单区间1天
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
            $last_datetime = \DB::table($this->orderTable)->max('gameEndTime');
            $startTime = $last_datetime ? strtotime($last_datetime) : strtotime(date('Y-m-d H:i:00', $now)) - 720;
        }
        $endTime = $startTime + 60;
        //每次只能查一分钟
        if($startTime > $now || $endTime > $now){
            return false;
        }
        $config = $this->initConfigMsg($this->game_type);
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $params = [
            'agent' => $config['cagent'],
            'startDateTime' => date('Y-m-d H:i:s', $startTime),
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('QuickQueryBetRecords', $params);
        if(!$res['responseStatus']){
            return false;
        }

        if(isset($res['data']) && isset($res['data']['list']) && !empty($res['data']['list'])){
            $this->updateOrder($res['data']['list']);
        }
        $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
        return true;
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

        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            //111 已派彩
            if($val['status'] != 111){
                continue;
            }
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('OCode', $val['betNum'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT-8");
            $gameDate = strtotime($val['betTime']);//注單建立時間
            $gameEndTime =  strtotime($val['gameRoundEndTime']);
            date_default_timezone_set($default_timezone);

            $insertData[] = [
                'tid' => intval(ltrim($val['player'], 'game')),
                'OCode' => $val['betNum'],
                'Username' => rtrim($val['player'], $config['lobby']),
                'gameDate' => date('Y-m-d H:i:s', $gameDate),
                'gameCode' => $val['gameType'],
                'betAmount' => bcmul($val['betAmount'], 100, 0),
                'winAmount' => bcmul($val['betAmount']+$val['winOrLossAmount'], 100, 0),
                'income' => bcmul($val['winOrLossAmount'], 100, 0),
                'gameEndTime' => date('Y-m-d H:i:s', $gameEndTime),
                'validAmount' => bcmul($val['validAmount'], 100, 0),
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
        //每次最大拉取区间1 小时内
        if ($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }

        $config = $this->initConfigMsg($this->game_type);

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");

        $fields = [
            'agent' => $config['cagent'],
            'startDateTime' => date('Y-m-d H:i:s', $startTime),
            'endDateTime' => date('Y-m-d H:i:s', $endTime),
            'pageNum' => 1,
            'pageSize' => 1000,
        ];
        date_default_timezone_set($default_timezone);
        while (1) {
            $res = $this->requestParam('PagingQueryBetRecords', $fields);
            if (!$res['responseStatus']) {
                break;
            }
            if ($res['data']['total'] == 0) {
                if ($is_redis) {
                    $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
                }
                break;
            }

            $this->updateOrder($res['data']['list']);
            if ($is_redis) {
                $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
            }
            if ($fields['data']['pageNum'] * $fields['data']['pageSize'] <= $res['data']['total']) {
                break;
            }
            //下一页
            $fields['pageNum']++;
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

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $requestTime = date('d M Y H:m:s T'); // "Wed, 28 Apr 2021 06:13:54 UTC";
        date_default_timezone_set($default_timezone);

        //Build the request parameters according to the API documentation
        $requestBodyString = json_encode($params, JSON_UNESCAPED_UNICODE);
        $contentMD5 =  base64_encode(pack('H*', md5($requestBodyString)));

        //The steps to generate HTTP authorization headers
        $stringToSign = "POST" . "\n"
            . $contentMD5 . "\n"
            . "application/json" . "\n"
            . $requestTime . "\n"
            . "/".$action;
        //Use HMAC-SHA1 to sign and generate the authorization
        $deKey = base64_decode($config['des_key']);
        $hash_hmac = hash_hmac("sha1", $stringToSign, $deKey, true);
        $encrypted = base64_encode($hash_hmac);
        $authorization = "AB" . " " . $config['key'] . ":" . $encrypted;

        //Send the Http request
        $url = $config['orderUrl'] . $action;
        //echo $url.PHP_EOL;
        $header = [
            "Content-Type: application/json",
            "Accept: application/json",
            "Authorization:" . $authorization,
            "Date:" . $requestTime,
            "Content-MD5:" . $contentMD5,
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
            if (isset($ret['resultCode']) && $ret['resultCode'] != 'OK') {
                $ret['responseStatus'] = false;
            } else {
                $ret['responseStatus'] = true;
            }
        }

        GameApi::addRequestLog($url, $this->game_type, $params, json_encode($ret, JSON_UNESCAPED_UNICODE));
        return $ret;
    }


}
