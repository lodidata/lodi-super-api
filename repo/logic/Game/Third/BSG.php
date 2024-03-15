<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;
use function GuzzleHttp\Psr7\str;

/**
 * Class BSG电子
 */
class BSG extends GameLogic {
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_bsg';
    protected $game_type = 'BSG';

    /**
     * 检查接口状态
     * @return bool
     */
    public function checkStatus() {
        return true;
    }

    /**
     * 同步第三方游戏订单
     * 拉单延迟60分钟，最大拉单区间1小时
     * @return bool
     * @throws \Exception
     */
    public function synchronousData() {
        if(!$this->checkStatus()) {
            return false;
        }
        $now    = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = $now - 3600; //取60分钟内的数据
        }
        $endTime = $now;
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), true);
    }

    /**
     * 更新订单表
     * @param array $res
     * @param int   $updateStatus
     * @return bool
     */

    public function updateOrder($res, $updateStatus = 0) {
        $insertData = [];
        $data       = [];
        if(!isset($res[0])) {
            $resData[0] = $res;
        } else {
            $resData = $res;
        }
        unset($res);

        $default_timezone = date_default_timezone_get();

        foreach($resData as $val) {

            $data['username'] = $val['USERNAME'];
            $data['tid']      = intval(ltrim($val['USERNAME'], 'game'));
            if(!isset($val['GAMESESSION'][0])) {
                $val['sessionArray'][0] = $val['GAMESESSION'];
            } else {
                $val['sessionArray'] = $val['GAMESESSION'];
            }
            unset($val['GAMESESSION']);

            foreach($val['sessionArray'] as $item) {
                $data['gamesession'] = $item['@attributes']['id'];
                $data['game_id']     = $item['GAMEID'];
                $data['platform']    = $item['PLATFORM'];
                if(!isset($item['BET'][0])) {
                    $item['betArray'][0] = $item['BET'];
                } else {
                    $item['betArray'] = $item['BET'];
                }
                unset($item['BET']);
                foreach($item['betArray'] as $value) {
                    $bet_id      = $value['@attributes']['id'];
                    $orderNumber = $data['gamesession'] . '_' . $bet_id;
                    if($value['AMOUNT'] == 0 && $value['WIN'] == 0) {
                        continue;
                    }

                    if($updateStatus) {
                        if(\DB::table($this->orderTable)->where('order_number', $orderNumber)->count()) {
                            continue;
                        }
                    }

                    date_default_timezone_set("Etc/GMT");
                    $betTime = date('Y-m-d H:i:s', strtotime($value['BETTIME']));
                    date_default_timezone_set($default_timezone);
                    $data['bet_id']       = $bet_id;
                    $data['order_number'] = $orderNumber;
                    $data['bet_amount']   = $value['AMOUNT'];
                    $data['win_amount']   = $value['WIN'];
                    $data['contribution'] = $value['CONTRIBUTION'] ?? 0;
                    $data['income']       = $value['WIN'] - $value['AMOUNT'];
                    $data['bettime']      = $betTime;
                    $insertData[]         = $data;
                }
            }
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 订单校验
     * @return bool
     * @throws \Exception
     */
    public function synchronousCheckData() {
        return true;
    }

    public function querySumOrder($start_time, $end_time) {
        return [];
    }

    /**
     * 游戏统计
     * @param null $date 日期
     * @return bool
     */
    public function queryOperatesOrder($date = null) {
        $data = [
            'username' => 'username',
            'bet'      => 'bet_amount',
            'win'      => 'win_amount',
            'profit'   => 'income',
            'gameDate' => 'bettime'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->game_type, $data, false);
    }

    public function queryHotOrder($user_prefix, $startTime, $endTime, $args = []) {
        return [];
    }

    public function queryLocalOrder($user_prefix, $start_time, $end_time, $page = 1, $page_size = 500) {
        return [];
    }

    /**
     * 按分钟检索事务
     * @param      $stime
     * @param      $etime
     * @param bool $is_redis
     * @return bool
     */
    public function orderByTime($stime, $etime, $is_redis = false) {
        $startTime = strtotime($stime);
        $endTime   = strtotime($etime);
        //每次最大拉取区间1小时内
        if($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT");
        $fields = [
            'startDate' => date('Y/m/d_H:i:s', $startTime),
            'endDate'   => date('Y/m/d_H:i:s', $endTime),
        ];
        date_default_timezone_set($default_timezone);

        $res = $this->requestParam('playersBetHistory.do', $fields);
        if(!$res['responseStatus']) {
            return false;
        }
        if(!empty($res['RESPONSE']['ACCOUNT'])){
            $this->updateOrder($res['RESPONSE']['ACCOUNT']);
        }


        if($is_redis) {
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
    public function orderByHour($stime, $etime) {
        return $this->orderByTime($stime, $etime, true);
    }

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array  $params
     * @return array|string
     */
    public function requestParam(string $action, array $params) {
        $config = $this->initConfigMsg($this->game_type);
        if(!$config) {
            $ret = [
                'responseStatus' => false,
                'message'        => 'api not config'
            ];
            GameApi::addElkLog($ret, $this->game_type);
            return $ret;
        }
        $url              = $config['orderUrl'] . $action;
        $params['bankId'] = $config['cagent'];
        $params['hash']   = $this->sign($params, $config);

        $url = $url . '?' . urldecode(http_build_query($params));

        $re  = Curl::get($url, null, true);

        if($re['status'] != 200) {
            $ret['responseStatus'] = false;
            $ret['networkStatus']  = $re['status'];
            $ret['msg']            = $re['content'];
            GameApi::addRequestLog($url, 'BSG', $params, json_encode($re, JSON_UNESCAPED_UNICODE));
        } else {
            $ret = $this->parseXML2($re['content']);
            if(isset($ret['RESPONSE']) && $ret['RESPONSE']['RESULT'] == 'OK') {
                $ret['responseStatus'] = true;
            } else {
                $ret['responseStatus'] = false;
            }
            GameApi::addRequestLog($url, 'BSG', $params, json_encode($ret, JSON_UNESCAPED_UNICODE));
        }
        return $ret;
    }

    public function sign($data, $config) {
        unset($data['hash']);
        $str = '';
        foreach($data as $k => $v) {
            if(is_null($v) || $v === '')
                continue;
            $str .= $v;
        }
        $signStr = $str . $config['key'];

        return md5($signStr);
    }
}
