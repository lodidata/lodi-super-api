<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * Class YGG
 */
class YGG extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_ygg';
    protected $orderTableDetail = 'game_order_ygg_detail';
    protected $game_type = 'YGG';


    /**
     * 检查接口状态
     * @return bool
     */
    public function checkStatus()
    {
        return true;
    }

    /**
     * 取消一周前的数据
     */
    public function syncOrderDetailDel()
    {
        $startTime = date('Y-m-d', strtotime('-1 month'));
        //$startTime = date('Y-m-d', strtotime('-1 week')); //查1周内的数据
        $lastTime = date('Y-m-d', strtotime('-1 week'));
        $list = \DB::table($this->orderTableDetail)
            ->where('status', 0)
            ->where('createTime', '>=', $startTime)
            ->where('createTime', '<', $lastTime)
            ->get(['reference','createTime'])->forPage(1,1000)->toArray();
        if(!empty($list)){
            foreach($list as $val){
                if($val->type == 'cancelWager'){
                    \DB::table($this->orderTableDetail)->where('createTime','>=', $startTime)->where('reference', $val->reference)->update(['status' => 1]);
                    continue;
                }
                $count = \DB::table($this->orderTableDetail)->where('createTime','>=', $startTime)->where('reference', $val->reference)->count();
                if($count>1){
                    continue;
                }

                \DB::table($this->orderTableDetail)->where('createTime','>=', $startTime)->where('reference', $val->reference)->update(['status' => 1]);
            }
        }
        return true;
    }

    /**
     * YGG详情表数据更新到注单表
     * @return bool
     */
    public function syncOrderDetail()
    {
        $startTime = date('Y-m-d', strtotime('-1 month'));
        //$startTime = date('Y-m-d', strtotime('-1 week')); //查1周内的数据
        $lastTime = date('Y-m-d H:i:s', time() - 600); //只处理10分钟前的数据
        $list = \DB::table($this->orderTableDetail)
            ->where('status', 0)
            ->where('createTime', '>', $startTime)
            ->where('createTime', '<', $lastTime)
            ->get()->forPage(1,5000)->toArray();

        if (empty($list)) {
            return true;
        }
        $wager_data = [];
        $endWager_data = [];
        $appendWager_data = [];
        foreach ($list as $val) {
            $val = (array)$val;
            if ($val['type'] == 'wager') {
                $wager_data[$val['reference']] = $val;
            } elseif ($val['type'] == 'endWager') {
                $endWager_data[$val['reference']] = $val;
            } elseif ($val['type'] == 'appendWagerResult') {
                $appendWager_data[$val['reference']] = $val;
            }
        }
        unset($list, $val);

        foreach ($endWager_data as $order_number => $val) {
            $tmpData = [];
            //有投注单
            if (isset($wager_data[$order_number])) {
                $tmpData = $wager_data[$order_number];
            }else{
                continue;
            }
            $tmpData['tid'] = intval(ltrim($val['loginname'], 'game'));
            //更新最后余额
            $tmpData['afterAmount'] = $val['afterAmount'];

            //有奖励单
            if (isset($appendWager_data[$order_number])) {
                $tmpData['afterAmount'] = $val['afterAmount'];
            }
            $tmpData['createTime'] = $val['createTime'];
            //计算派奖与盈利
            $tmpData['profit'] = bcsub($tmpData['afterAmount'], $tmpData['beforeAmount'], 2);
            $tmpData['prize'] = bcadd($tmpData['amount'], $tmpData['profit'], 2);
            $tmpData['last_id'] = $tmpData['id'];
            unset($tmpData['id'], $tmpData['subreference'], $tmpData['status'],$tmpData['detail']);

            $order_number = (string)$order_number;
            try{
                //插入数据
                $insert_res = \DB::table($this->orderTable)->insert($tmpData);
                if ($insert_res) {
                    \DB::table($this->orderTableDetail)->whereRaw('reference="'. $order_number. '"')->update(['status' => 1]);
                }
            } catch (\Exception $e){
                //存在标识已更新
                if (\DB::table($this->orderTable)->whereRaw('reference="'. $order_number. '"')->count()) {
                    \DB::table($this->orderTableDetail)->whereRaw('reference="'. $order_number. '"')->update(['status'=> 1]);
                }else{
                    $this->logger->error('YGG ' . $e->getMessage());
                }
            }
        }
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
            $startTime = $r_time;
        } else {
            $startTime = $now - 24 * 60 * 60; //取30分钟内的数据
        }
        $endTime = $now;
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), true);
    }

    /**
     * 更新订单表
     * @param array $data
     * @param int $last_id 最后ID
     * @param int $updateStatus
     * @return bool
     */
    public function updateOrder($data, $last_id = 0, $updateStatus = 0)
    {
        $default_timezone = date_default_timezone_get();
        $insertData = [];
        foreach ($data as $val) {
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTableDetail)->where('id', $val['id'])->count()) {
                    continue;
                }
            }
            $last_id = $val['id'];
            date_default_timezone_set("Etc/GMT");
            $createTime = strtotime($val['createTime']);//注單建立時間
            date_default_timezone_set($default_timezone);
            unset($val['topOrg'], $val['org']);
            $insertData[] = [
                'id' => $val['id'],
                'reference' => $val['reference'],
                'subreference' => $val['subreference']??'',
                'loginname' => $val['loginname']??'',
                'currency' => $val['currency'] ?? '',
                'type' => $val['type']??'',
                'amount' => $val['amount']?? 0,
                'afterAmount' => $val['afterAmount']?? 0,
                'beforeAmount' => $val['beforeAmount'] ?? 0,
                'gameName' => $val['gameName'] ?? '',
                'DCGameID' => $val['DCGameID'] ?? '',
                'createTime' => date('Y-m-d H:i:s', $createTime),
                'status' => 0,
                'detail' => $val['detail'] ?? '',
            ];
        }
        $this->addGameOrders($this->game_type, $this->orderTableDetail, $insertData);
        return $last_id;
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
        $result = \DB::table($this->orderTable)
            ->where('createTime', '>=', $start_time)
            ->where('createTime', '<=', $end_time)
            ->selectRaw("sum(amount) as bet,sum(amount) as valid_bet,sum(prize) as win_loss")
            ->first();
        if ($result) {
            $result = (array)$result;
            $result['bet'] = bcmul($result['bet'], 100, 0);
            $result['valid_bet'] = bcmul($result['valid_bet'], 100, 0);
            $result['win_loss'] = bcmul($result['win_loss'], 100, 0);
        }
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
            'username' => 'loginname',
            'bet' => 'amount',
            'win' => 'prize',
            'profit' => 'profit',
            'gameDate' => 'createTime'
        ];
        return $this->rptOrdersMiddleDay($date, $this->orderTable, $this->game_type, $data, false);
    }

    public function queryHotOrder($user_prefix, $startTime, $endTime, $args = [])
    {
        return [];
    }

    public function queryLocalOrder($user_prefix, $start_time, $end_time, $page = 1, $page_size = 500)
    {
        $query = \DB::table($this->orderTable)
            ->where('createTime', '>=', $start_time)
            ->where('createTime', '<=', $end_time)
            ->where('loginname', 'like', "%$user_prefix%")
            ->selectRaw("id,createTime as gameDate,reference as order_number,amount as bet,amount as valid_bet,prize as win_loss");
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
     * 按分钟检索事务
     * @param $stime
     * @param $etime
     * @return bool
     */
    public function orderByTime($stime, $etime, $is_redis = false)
    {
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);
        //每次最大拉取区间24 小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }

        if($startTime >= $endTime){
            return true;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT");

        $fields = [
            'startTime' => date('Y-m-d H:i:s', $startTime), //注單更新起始時間，時區 +0, 需補到毫秒 Ex. 1566230400000
            'endTime' => date('Y-m-d H:i:s', $endTime),
            'gametype' => 'yg',
        ];
        date_default_timezone_set($default_timezone);

        $last_id = 0;//最大ID
        while (1) {
            $fields['lastId'] = $last_id;
            $res = $this->requestParam('getUsersBetDataV2', $fields, true, true);
            if (!$res['responseStatus']) {
                return false;
            }
            if (empty($res['data'])) {
                break;
            }
            $last_id = $this->updateOrder($res['data'], $last_id);
            sleep(10);
    }

        //总记录数
        if ($is_redis) {
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
     * @param bool $is_post 是否为post请求
     * @param bool $is_order
     * @return array|string
     */
    public function requestParam(string $action, array $param, bool $is_post = true, $is_order = false)
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
        $param['topOrg'] = $config['des_key'];
        $param['org'] = $config['cagent'];
        $param['currency'] = $config['currency'];
        $param['sign'] = md5($param['topOrg'] . $param['org'] . $config['key']);

        if ($action) {
            $action = '/' . $action;
        }
        $url = rtrim($is_order ? $config['orderUrl'] : $config['apiUrl'], '/') . $action;
        $headers = [
            'Content-Type : '
        ];
        if ($is_order) {
            $headers = [
                'Content-Type: application/x-www-form-urlencoded'
            ];
        }

        $postParams = $param;
        $re = Curl::commonPost($url, null, http_build_query($postParams, '', '&'), $headers);
        if ($re === false) {
            $ret['responseStatus'] = false;
        } else {
            $ret = json_decode($re, true);
            if ($ret['code'] == 0) {
                $ret['responseStatus'] = true;
            } else {
                $ret['responseStatus'] = false;
            }
        }
        $logs = $ret;
        unset($logs['responseStatus']);
        GameApi::addRequestLog($url, $this->game_type, $param, json_encode($logs, JSON_UNESCAPED_UNICODE));
        return $ret;
    }
}
