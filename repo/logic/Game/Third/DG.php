<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class DG视讯
 */
class DG extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_dg';
    protected $game_type = 'DG';

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

        $this->orderByTime('', '', true);
    }

    /**
     * 更新订单表
     * @param array $data
     * @param int $updateStatus
     * @return bool
     */

    public function updateOrder($data, $updateStatus = 0)
    {
        $orderIds = [];
        $insertData = [];
        foreach ($data as $val) {
            //是否结算：1：已结算 2:撤销
            if($val['isRevocation'] == 2){
                $orderIds[] = $val['id'];
                continue;
            }elseif($val['isRevocation'] !== 1){
                continue;
            }
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('order_number', $val['id'])->count()) {
                    continue;
                }
            }
            //回调ID
            $orderIds[] = $val['id'];

            $insertData[] = [
                'tid' => intval(ltrim(strtolower($val['userName']), 'game')),
                'order_number' => $val['id'],
                'userName' => strtolower($val['userName']),
                'betPoints' => $val['betPoints'],
                'winOrLoss' => $val['winOrLoss'],
                'profit' => bcsub($val['winOrLoss'], $val['betPoints'], 2),
                'betTime' => $val['betTime'],
                'calTime' => $val['calTime'],
                'playId' => $val['playId'],
                'GameId' => $val['GameId'] ?? 0,
                'tableId' => $val['tableId'],
                'availableBet' => $val['availableBet'],
                'currencyId' => $val['currencyId'] ?? 0,
                'result' => '{}',
                'betDetail'=>'{}'
            ];
        }

        if(!empty($orderIds)){
            $this->markReport($orderIds);
        }
        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }

    /**
     * 2.10 标记已抓取注单
     * @param $orderIds
     * @return mixed
     */
    public function markReport($orderIds)
    {
        $params = [
            'list' => $orderIds
        ];

        $res = $this->requestParam('/game/markReport', $params);
        return $res['responseStatus'];
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
            'username' => 'userName',
            'bet' => 'betPoints',
            'win' => 'winOrLoss',
            'profit' => 'profit',
            'gameDate' => 'betTime'
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
        $fields = [];
        $res = $this->requestParam('/game/getReport', $fields);
        if (!$res['responseStatus']) {
            return false;
        }
        if(empty($res['list'])){
            return true;
        }
        $this->updateOrder($res['list']);

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
     * @param array $params
     * @return array|string
     */
    public function requestParam(string $action, array $params)
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
        $agentName = $config['cagent'];
        //生成token的随机字符串
        $random = str_replace('.','', sprintf('%.6f', microtime(TRUE)));
        $token = md5($agentName . $config['key'] . $random);
        $params['token'] = $token;
        $params['random'] = $random;

        $url = rtrim($config['orderUrl'], '/') . $action . '/' . $agentName;
        $re = Curl::post($url, null, $params, null, true);
        if ($re['status'] != 200) {
            $ret['responseStatus'] = false;
            $ret['networkStatus'] = $re['status'];
            $ret['msg'] = $re['content'];
            GameApi::addRequestLog($url, 'DG', $params, json_encode($re, JSON_UNESCAPED_UNICODE));
        } else {
            $ret = json_decode($re['content'], true);
            $ret['networkStatus'] = $re['status'];
            if (isset($ret['codeId']) && $ret['codeId'] === 0) {
                $ret['responseStatus'] = true;
            } else {
                $ret['responseStatus'] = false;
            }
            GameApi::addRequestLog($url, 'DG', $params, json_encode($ret, JSON_UNESCAPED_UNICODE));
        }
        return $ret;
    }
}
