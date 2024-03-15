<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class DS88
 */
class DS88 extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_ds88';
    protected $game_type = 'DS88';

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
            if($val['status'] == 'cancel'){
                continue;
            }
            //订单状态 init: 注單初始,beted:注單成立,settled:派彩,cancel:取消,fail:失敗
            if (!$val['is_settled']) {
                continue;
            }
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('slug', $val['slug'])->count()) {
                    continue;
                }
            }
            date_default_timezone_set("Etc/GMT");
            $CreateAt = strtotime($val['bet_at']);//建立時間
            $ResultAt = strtotime($val['settled_at']);//结果时间
            date_default_timezone_set($default_timezone);
            $insertData[] = [
                'tid' => intval(ltrim($val['account'], 'game')),
                'slug' => $val['slug'],
                'account' => $val['account'],
                'bet_amount' => $val['bet_amount'],
                'bet_return' => $val['bet_return'],
                'valid_amount' => $val['valid_amount'],
                'net_income' => $val['net_income'],
                'settled_at' => date('Y-m-d H:i:s', $ResultAt),
                'bet_at' => date('Y-m-d H:i:s', $CreateAt),
                'side' => $val['side'] ?? '',
                'result' => $val['result'] ?? '',
                'round_id' => $val['round_id'] ?? 0,
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
            'username' => 'account',
            'bet' => 'bet_amount',
            'win' => 'bet_return',
            'profit' => 'net_income',
            'gameDate' => 'settled_at'
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
        //每次最大拉取区间1小时内
        if ($endTime - $startTime > 3600) {
            $endTime = $startTime + 3600;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT");
        $fields = [
            'time_type' => 'settled_at',//搜尋的時間型態(Default is “bet_at”, [“bet_at”, “settled_at”])
            'start_time' => date('Y-m-d H:i:s', $startTime),
            'end_time' => date('Y-m-d H:i:s', $endTime),
            'page' => 1,
            'page_size' => 1000,
        ];
        date_default_timezone_set($default_timezone);
        while (1) {
            $res = $this->requestParam('/api/merchant/bets', $fields, false, true);
            //接口报错
            if (!$res['responseStatus']) {
                return false;
            }
            //无数据
            if ($res['total_count'] == 0) {
                break;
            }
            $this->updateOrder($res['data']);
            //下一页
            if ($res['total_page'] <= $fields['page']) {
                break;
            }
            $fields['page']++;
            sleep(10);
        }
        if($is_redis){
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
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @param bool $is_post 是否POST请求
     * @param bool $is_order 是否为获取注单
     * @return array|string
     */
    public function requestParam(string $action, array $param, $is_post = true, $is_order = false)
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
            "Authorization:Bearar " . $config['key'],
            "Accept: application/json",
            "Content-Type:application/json"
        );

        $queryString = http_build_query($param, '', '&');
        if ($is_post) {
            $re = Curl::post($url, null, $param, null, true, $headers);
        } else {
            $url .= '?' . $queryString;
            $re = Curl::get($url, null, true, $headers);
        }

        if (!isset($re['status'])) {
            GameApi::addRequestLog($url, $this->game_type, $param, json_encode($re, JSON_UNESCAPED_UNICODE));
        }
        $re['content'] = json_decode($re['content'], true);
        GameApi::addRequestLog($url, $this->game_type, $param, json_encode($re, JSON_UNESCAPED_UNICODE));
        $ret = $re['content'];
        $ret['status'] = $re['status'];
        //201登录成功
        if (in_array($re['status'], [200, 201])) {
            $ret['responseStatus'] = true;
        } else {
            $ret['responseStatus'] = false;
        }
        return $ret;
    }


}
