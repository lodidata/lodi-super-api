<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameLogic;
use Logic\Game\GameApi;

/**
 * Explain: TCG 游戏接口
 *
 * OK
 */
class TCG extends GameLogic
{
    protected $game_type = 'TCG';
    protected $orderTable = 'game_order_tcg';

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
     * 拉单延迟10分钟
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
            $last_datetime = \DB::table($this->orderTable)->max('betTime');
            $startTime = $last_datetime ? strtotime($last_datetime) : $now;       
        }
        $endTime = $now - 10 * 60;   //延迟10分钟取数据
        if($startTime >= $endTime || $endTime-$startTime < 300){
            return false;
        }
        $this->orderByTime(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), true);
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
        $config = $this->initConfigMsg($this->game_type);
        $lobby = json_decode($config['lobby'], true);
        $startTime = strtotime($stime);
        $endTime = strtotime($etime);

        //每次最大拉取区间24小时内
        if ($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }

        //处理开始时间分钟从0开始算202301161523
        $start = substr(date('YmdHi', $startTime), 0, -1);
        $start_str2 = substr(date('i',$startTime),-1);
        $startTime = $start.($start_str2>=5 ? '5' : '0');

        //API预设北京时区(GMT+8)
        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT-8");
        $startTime = strtotime($startTime);
        date_default_timezone_set($default_timezone);

        $i = 1;
        $url = 'ftp://'.$lobby['ftp_username'].':'.$lobby['ftp_pw'].'@'.$lobby['ftp'].'/'.$lobby['ftp_dir'];
        while(1){
            if($startTime > $endTime) {
                break;
            }

            $startDate = date('Ymd', $startTime);
            $start = date('YmdHi', $startTime);

            $page = str_pad($i,4,"0",STR_PAD_LEFT);
            $jsonUrl = $url.'/'.$startDate.'/'.$start.'_'.$page.'.json';
            $content = file_get_contents($jsonUrl);

            $filename = $start.'_'.$page.'.log';

            $file = ROOT_PATH . '/game/TCG/' . $filename;
            // 日志存在先删除再写入
            if (file_exists($file)) {
                unlink($file);
            }
            GameApi::addJsonFile($content, 'TCG', $filename);

            $res = json_decode($content, true);
            if(!empty($res['list'])) {
                $this->updateOrder($res['list']);
            }

            if($res['page']['total'] > 0 && intval($res['page']['total']/$res['page']['pageSize']) > $res['page']['currentPage']) {
                $i++;
            } else {
                $i = 1;
                $startTime = $startTime + 300;   //后台每5分钟生成一个数据文件
            }
        }

        if ($is_redis) {
            $this->redis->set(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type, $endTime);
        }
        return true;
    }

    /**
     * 订单校验
     * @return bool
     */
    public function synchronousCheckData()
    {
        return true;
    }

    public function querySumOrder($start_time, $end_time)
    {
        $result = \DB::table($this->orderTable)
            ->where('betTime', '>=', $start_time)
            ->where('betTime', '<=', $end_time)
            ->selectRaw("sum(betAmount) as bet,sum(actualBetAmount) as valid_bet,sum(netPNL) as win_loss")
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
            'username' => 'username',
            'bet' => 'betAmount',
            'win' => 'winAmount',
            'profit' => 'netPNL',
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
        $query = \DB::table($this->orderTable)
            ->where('betTime', '>=', $start_time)
            ->where('betTime', '<=', $end_time)
            ->where('username', 'like', "%$user_prefix%")
            ->selectRaw("id,settlementTime,betOrderNo as order_number,betAmount as bet,actualBetAmount as valid_bet,netPNL as win_loss");
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
     * @param array $data
     * @param int $gameId
     * @return bool
     */
    public function updateOrder($data)
    {
        $insertData = [];

        foreach ($data as $val) {
            if($val['betStatus'] == "3" || in_array($val['detailStatusId'], [6,8,12,14,17])) {   //订单状态为取消/撤单的不更新
                continue;
            }
            $insertData[] = [
                'orderNum' => $val['orderNum'],
                'tid' => intval(ltrim($val['username'], 'game')),
                'betAmount' => $val['betAmount'],
                'actualBetAmount' => $val['actualBetAmount'],
                'gameCode' => $val['gameCode'],
                'device' => $val['device'],
                'betNum' => $val['betNum'],
                'winAmount' => $val['winAmount'],
                'netPNL' => $val['netPNL'],
                'betStatus' => $val['betStatus'],
                'username' => $val['username'],
                'numero' => $val['numero'],
                'remark' => $val['remark'],
                'bettingContent' => $val['bettingContent'],
                'playBonus' => $val['playBonus'],
                'winningNumber' => $val['winningNumber'],
                'playName' => $val['playName'],
                'gameGroupName' => $val['gameGroupName'] ?? "",
                'multiple' => $val['multiple'],
                'playId' => $val['playId'],
                'betTime' => $val['betTime'],
                'settlementTime' => $val['settlementTime']
            ];
        }

        return $this->addGameOrders($this->game_type, $this->orderTable, $insertData);
    }
}