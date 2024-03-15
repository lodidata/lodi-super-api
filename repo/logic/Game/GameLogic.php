<?php

namespace Logic\Game;

use Interop\Container\Exception\ContainerException;
use Logic\Logic;
use Logic\Define\CacheKey;
use Slim\Container;

class GameLogic extends Logic
{

    /**
     * 获取注单列表
     * @param int $tid tid
     * @param int $max_id 注单最大ID号
     * @return array
     * @throws ContainerException
     */
    public function getOrder(int $tid, int $max_id)
    {
        $lastId = 0;
        $subsite_page_size = $this->ci->get('settings')['app']['subsite_page_size'] ?? 1000;

        //获取表最大ID值
        $tableMaxId = \DB::table($this->orderTable)->max('id') ?: 0;
        //无数据更新
        if($tableMaxId == $max_id){
            return ['data' => [], 'total' => 0, 'lastId' => $max_id];
        }

        //获取列表数据
        $query = \DB::table($this->orderTable);
        if($max_id > 0){
            $query->from(\DB::raw($this->orderTable . ' force index(PRIMARY)'));
        }
        $data = $query->where('tid', '=', $tid)
            ->where('id', '>', $max_id)
            ->forPage(1, $subsite_page_size)
            ->get()->toArray();

        //无数据下次从最大ID开始
        if(empty($data)){
            $lastId = $tableMaxId;
        }else{
            $lastId = end($data)->id;
            if($lastId < ($max_id+$subsite_page_size)){
                $lastId = $tableMaxId;
            }
        }

        return ['data' => $data, 'total' => count($data), 'lastId' => $lastId];
    }

    /**
     * 获取对局详情列表
     * @param int $tid tid
     * @param int $max_id 注单最大ID号
     * @return array
     * @throws ContainerException
     */
    public function getPlayDetail(int $tid, int $max_id)
    {
        $lastId = 0;
        $subsite_page_size = $this->ci->get('settings')['app']['subsite_page_size'] ?? 1000;

        //获取表最大ID值
        $tableMaxId = \DB::table($this->playTable)->max('id') ?: 0;
        //无数据更新
        if($tableMaxId == $max_id){
            return ['data' => [], 'total' => 0, 'lastId' => $max_id];
        }

        //获取列表数据
        $query = \DB::table($this->playTable);
        if($max_id > 0){
            $query->from(\DB::raw($this->playTable . ' force index(PRIMARY)'));
        }
        $data = $query->where('tid', '=', $tid)
            ->where('id', '>', $max_id)
            ->forPage(1, $subsite_page_size)
            ->get()->toArray();

        //无数据下次从最大ID开始
        if(empty($data)){
            $lastId = $tableMaxId;
        }else{
            $lastId = end($data)->id;
            if($lastId < ($max_id+$subsite_page_size)){
                $lastId = $tableMaxId;
            }
        }

        return ['data' => $data, 'total' => count($data), 'lastId' => $lastId];
    }

    /**
     * 游戏game_api表配置
     * @param string $type 游戏类型
     * @return array
     */
    public function initConfigMsg($type)
    {
        $gameConfig = $this->redis->hGet(CacheKey::$perfix['ApiThirdGameJumpMsg'], $type);
        if (is_null($gameConfig)) {
            $data = \DB::table('game_api')->get()->toArray();
            $tmp = [];
            foreach ($data as $val) {
                $val = (array)$val;
                $tmp[$val['type']] = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $this->redis->hMset(CacheKey::$perfix['ApiThirdGameJumpMsg'], $tmp);
            $gameConfig = $tmp[$type] ?? '';
        }
        $gameConfig = json_decode($gameConfig, true);
        return $gameConfig;
    }


    /**
     * 注单入库
     * @param $game_type
     * @param $table
     * @param $insertData
     * @return bool
     */
    public function addGameOrders($game_type, $table, $insertData)
    {
        if (empty($insertData)) {
            return true;
        }
        $page = 1;
        $pageSize = 200;
        $total_count = count($insertData);
        $page_count = ceil($total_count / $pageSize);
        while (1) {
            if ($page > $page_count) {
                break;
            }
            $page_start = ($page - 1) * $pageSize;
            $subData = array_slice($insertData, $page_start, $pageSize);
            $subDataCount = count($subData);
            try {
                \DB::table($table)->insert($subData);
            } catch (\Exception $e) {
                //总数只有一条插入失败
                if ($subDataCount == 1) {
                    if(strpos($e->getMessage(), '1062 Duplicate entry')){
                        break;
                    }
                    $this->addGameOrderError($game_type, reset($subData), $e->getCode(), $e->getMessage());
                } else {
                    foreach ($subData as $recode) {
                        try {
                            \DB::table($table)->insert($recode);
                        } catch (\Exception $e2) {
                            if(strpos($e2->getMessage(), '1062 Duplicate entry')){
                                continue;
                            }
                            $this->addGameOrderError($game_type, $recode, $e2->getCode(), $e2->getMessage());
                        }
                    }
                }
            }
            $page++;
        }

        unset($data, $val, $insertData, $subData);
        return true;
    }

    /**
     * 订单统计错误记录表
     * @param $game_type
     * @param string $now 当前时间
     * @param array $json
     * @param string $startTime
     * @param string $endTime
     * @param string $totalBet
     * @param string $totalWin
     * @param string $orderBet
     * @param string $orderWin
     */
    public function addGameOrderCheckError($game_type, $now, $json, $startTime, $endTime, $totalBet, $totalWin, $orderBet, $orderWin)
    {
        \DB::table('game_order_check_error')->insert([
            'game_type' => $game_type,
            'now' => date('Y-m-d H:i:s', $now),
            'json' => json_encode($json, JSON_UNESCAPED_UNICODE),
            'error' => json_encode([
                'startTime' => $startTime,
                'endTime' => $endTime,
                'totalBet' => $totalBet,
                'totalWin' => $totalWin,
                'orderBet' => $orderBet,
                'orderWin' => $orderWin
            ], JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * 处理game_order_error表注单
     */
    public function clearGameOrderError()
    {
        //游戏表对应注单order字段
        $orderType = [
            'AT' => 'order_number',
            'AVIA' => 'OrderID',
            'CG' => 'SerialNumber',
            'CQ9' => 'round',
            'DS88' => 'slug',
            'EVO' => 'OCode',
            'FC' => 'recordID',
            'JDB' => 'seqNo',
            'JILI' => 'OCode',
            'JOKER' => 'OCode',
            'KMQM' => 'ugsbetid',
            'PG' => 'OCode',
            'PNG' => 'OCode',
            'PP' => 'OCode',
            'RCB' => 'platformTxId',
            'SEXYBCRT' => 'platformTxId',
            'SV388' => 'platformTxId',
            'SBO' => 'refNo',
            'SGMK' => 'ticketId',
            'TF' => 'order_id',
            'UG' => 'BetID',
            'BNG' => 'round',
            'SA' => 'OCode',
            'DG' => 'order_number',
            'IG' => 'OCode',
            'AWS' => 'platformTxId',
            'WM' => 'order_number',
            'YGG' => 'id',
            'XG' => 'WagersId',
            'PB' => 'wagerId',
            'BG' => 'orderId',
            'STG' => 'OrderNumber',
            'EVOPLAY' => 'OCode',
            'BSG' => 'order_number',
            'MG' => 'betUID',
            'HB' => 'GameInstanceId',
            'VIVO' => 'OCode',
            'EZUGI' => 'order_number',
            'QT' => 'round_id',
            'ALLBET' => 'OCode',
            'BTI' => 'PurchaseID',
            'GFG' =>'OCode',
            'NG' => 'roundId',
            'TCG' => 'orderNum',
            'YESBINGO' => 'OCode',
            'EVORT' => 'OCode',
            'WMATCH' => 'roundId',
            'RSG' => 'SequenNumber',
        ];
        $list = (array)\DB::table('game_order_error')->limit(1000)->get(['id', 'game_type', 'json'])->toArray();
        foreach ($list as $value) {
            $value = (array)$value;
            $game_type = $value['game_type'];
            $game_table = 'game_order_' . strtolower($game_type);
            if($game_type == 'YGG'){
                $game_table = 'game_order_ygg_detail';
            }
            $order_number_field = $orderType[$game_type];
            if(is_null($order_number_field)){
                $this->logger->error('clearGameOrderError 未定义:' . $game_type);
            }
            $val = json_decode($value['json'], true);
            if (isset($val[0])) {
                $val = $val[0];
            }
            if (in_array($game_type, ['JILI','YGG','PG'])) {
                $val[$order_number_field] = (string)$val[$order_number_field];
            }

            if($game_type == 'CG'){
                unset($val['user_id']);
            }

            if (\DB::table($game_table)->where($order_number_field, $val[$order_number_field])->count()) {
                \DB::table('game_order_error')->delete($value['id']);
                continue;
            }
            try {
                $insert_res = \DB::table($game_table)->insert($val);
                if ($insert_res) {
                    \DB::table('game_order_error')->delete($value['id']);
                }
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    /**
     * 添加游戏订单错误
     * @param $game_type
     * @param $insertData
     * @param $code
     * @param $msg
     */
    public function addGameOrderError($game_type, $insertData, $code, $msg)
    {
        $tmp_err = [
            'game_type' => $game_type,
            'json' => json_encode($insertData, JSON_UNESCAPED_UNICODE),
            'error' => $msg,
        ];
        \DB::table('game_order_error')->insert($tmp_err);
        GameApi::addElkLog(['code' => $code, 'message' => $msg], $game_type);
    }

    /**
     * 报表统计
     * @param string $date 日期
     * @param string $table 注单表名称
     * @param string $gameType 菜单
     * @param array $data [username,bet,win,profit,gameDate]
     * @param bool $is_fen 金额单位是否为分
     * @return bool
     */
    public function rptOrdersMiddleDay($date, $table, $gameType, $data, $is_fen = true)
    {
        $reset = false;
        if ($date) {
            $start_time = $date;
            $end_time = date('Y-m-d', strtotime("$date+1 day"));
            $reset = true;
        } else {
            $now = time();
            $startTime = $this->redis->hGet(CacheKey::$perfix['queryOperatesOrder'], $gameType);//上次的结束时间
            if (is_null($startTime)) {
                $startTime = strtotime(date('Y-m-d 00:00:00', $now)); //当天的数据
            }
            //统计日期
            $date = date('Y-m-d', $startTime);
            $start_time = date('Y-m-d H:00:00', $startTime);
            //开始时间大于今天,统计一天
            if (date('Y-m-d', $now) > $date) {
                $end_time = date('Y-m-d', strtotime("$start_time +1 day"));
            } else {
                $end_time = date('Y-m-d H:00:00', strtotime("$start_time+1 hour"));
            }
            if ($start_time == $end_time || strtotime($end_time) > $now) {
                return true;
            }
        }
        $sql = "
        SELECT
	  tid,
	count( DISTINCT {$data['username']} ) AS game_user_cnt,
	count( 0 ) AS game_order_cnt,
	sum( {$data['bet']} ) AS game_bet_amount,
	sum( {$data['win']} ) AS game_prize_amount,
	sum( {$data['profit']} ) AS game_order_profit,
    '{$gameType}' as game_type,
    '{$date}' as count_date
FROM
	{$table} 
WHERE
	{$data['gameDate']} >= '{$start_time}' 
	AND {$data['gameDate']} < '{$end_time}' 
GROUP BY
	tid;";
        echo $sql.PHP_EOL;
        $result = \DB::select($sql);
        if ($result) {
            foreach ($result as $val) {
                $val = (array)$val;
                if($is_fen){
                    $val['game_bet_amount'] = abs(bcdiv($val['game_bet_amount'], 100, 2));
                    $val['game_prize_amount'] = bcdiv($val['game_prize_amount'], 100, 2);
                    $val['game_order_profit'] = bcdiv($val['game_order_profit'], 100, 2);
                }else{
                    $val['game_bet_amount'] = abs(bcmul($val['game_bet_amount'], 1, 2));
                    $val['game_prize_amount'] = bcmul($val['game_prize_amount'], 1, 2);
                    $val['game_order_profit'] = bcmul($val['game_order_profit'], 1, 2);
                }

                try {
                    $where = ['tid' => $val['tid'], 'game_type' => $val['game_type'], 'count_date' => $date];
                    //重新统计昨天数据
                    if ($reset) {
                        \DB::table('rpt_orders_middle_day')->updateOrInsert($where, $val);
                    } else {
                        if (\DB::table('rpt_orders_middle_day')->where($where)->count()) {
                            \DB::table('rpt_orders_middle_day')
                                ->where($where)
                                ->increment('game_user_cnt', $val['game_user_cnt'], [
                                    'game_order_cnt' => \DB::raw("game_order_cnt + {$val['game_order_cnt']}"),
                                    'game_bet_amount' => \DB::raw("game_bet_amount + {$val['game_bet_amount']}"),
                                    'game_prize_amount' => \DB::raw("game_prize_amount + {$val['game_prize_amount']}"),
                                    'game_order_profit' => \DB::raw("game_order_profit + {$val['game_order_profit']}"),
                                ]);
                        } else {
                            \DB::table('rpt_orders_middle_day')->insert($val);
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->error('queryOperatesOrder-' . $gameType, $val);
                }
            }
        }
        unset($val);
        if (!$reset) {
            $this->redis->hSet(CacheKey::$perfix['queryOperatesOrder'], $gameType, strtotime($end_time));
        }
        return true;
    }

    function xml_attribute($object, $attribute)
    {
        if(isset($object[$attribute]))
            return (string) $object[$attribute];
    }


    /**
     * XML解析成数组
     */
    public function parseXML2($xmlSrc)
    {
        if (empty($xmlSrc)) {
            return false;
        }
        $array = array();
        $xml = simplexml_load_string($xmlSrc);
        $encode = $this->getXmlEncode($xmlSrc);
        if ($xml && $xml->children()) {
            foreach ($xml->children() as $node) {
                //有子节点
                if ($node->children()) {
                    $k = $node->getName();
                    $nodeXml = $node->asXML();
                    $v = $this->parseXML($nodeXml);
                } else {
                    $k = $node->getName();
                    $v = (string)$node;
                }
                if ($encode != "" && strpos($encode, "UTF-8") === FALSE) {
                    $k = iconv("UTF-8", $encode, $k);
                    $v = iconv("UTF-8", $encode, $v);
                }
                $array[$k] = $v;
            }
        }
        return $array;
    }

    //获取xml编码
    public function getXmlEncode($xml)
    {
        $ret = preg_match("/<?xml[^>]* encoding=\"(.*)\"[^>]* ?>/i", $xml, $arr);
        if ($ret) {
            return strtoupper($arr[1]);
        } else {
            return "";
        }
    }

    /**
     * XML解析成数组
     */
    public function parseXML($xmlSrc)
    {
        $xml_parser = xml_parser_create();
        if(!xml_parse($xml_parser,$xmlSrc,true)){
            xml_parser_free($xml_parser);
            return $xmlSrc;
        }
        $xml = simplexml_load_string($xmlSrc);
        $data = $this->xml2array($xml);
        return $data;
    }

    public function xml2array($xmlobject)
    {
        if ($xmlobject) {
            foreach ((array)$xmlobject as $k => $v) {
                $data[$k] = !is_string($v) ? $this->xml2array($v) : $v;
            }
            return $data;
        }
    }
}