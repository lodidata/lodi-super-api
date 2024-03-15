<?php

namespace Logic\Spider;

class Utils
{

    /**
     * 00:11:00 转成 时间截
     * @param  [type] $str [description]
     * @return [type]      [description]
     */
    static function timeStrToInt($str)
    {
        $arrs = explode(":", $str);
        return intval($arrs[0]) * 3600 + intval($arrs[1]) * 60 + intval($arrs[2]);
    }

    /**
     * 计算彩期号
     * @param  [type] $curDate    [description]
     * @param  [type] $saleTime   [description]
     * @param  [type] $interval   [description]
     * @param  [type] $oldDate    [description]
     * @param  [type] $oldStartNo [description]
     * @return [type]             [description]
     */
    static function getNo($curDate, $saleTime, $interval, $oldDate, $oldStartNo)
    {

        $days = intval((strtotime($curDate) - strtotime($oldDate)) / 86400);
        // echo 'days:', $days, PHP_EOL;
        if ($days == 0) {
            return $oldStartNo;
        }

        if ($days <= 0) {
            throw new \Exception("不支持小于当前天数的期数生成, 请查看配置");
        }

        if (is_array($saleTime) && isset($saleTime[2])) {
            return $saleTime[2] * $days + $oldStartNo;
        }
        $now = strtotime($curDate);
        for ($day = 0; $day < $days; $day++) {
            if (is_array($saleTime)) {
                $nowSaleTime = $saleTime;
            } else {
                $date = date('Y-m-d', strtotime($oldDate) + $day * 86400);
                $nowSaleTime = $saleTime($date);
            }
            $oldStartNo += $nowSaleTime[2];
        }

        return $oldStartNo;
    }

    static function getNo2($timestamp, $no)
    {
        return date("Ymd", $timestamp) . str_pad($no, 4, "0", STR_PAD_LEFT);
    }

    static function getNo3($timestamp, $no)
    {
        return date("Ymd", $timestamp) . str_pad($no, 3, "0", STR_PAD_LEFT);
    }

    /**
     * 跨天时使用
     * @param  [type] $timestamp [description]
     * @param  [type] $no        [description]
     * @return [type]            [description]
     */
    static function getNo3y($timestamp, $no, $saleTime = '')
    {
        if (!empty($saleTime)) {
            $start = Utils::timeStrToInt($saleTime[0]);
            $end = Utils::timeStrToInt($saleTime[1]);
            $time = Utils::timeStrToInt(date("H:i:s", $timestamp));

            if ($start > $end && $time <= $end) {
                $timestamp = $timestamp - 86400;
            }
            return date("Ymd", $timestamp) . str_pad($no, 3, "0", STR_PAD_LEFT);
        } else {

            return date("Ymd", $timestamp) . str_pad($no, 3, "0", STR_PAD_LEFT);
        }
    }

    static function getNo4($timestamp, $no)
    {
        $timestamp = $timestamp + 20;
        $no = $no % 120;
        $no = $no == 0 ? 120 : $no;
        return date("Ymd", $timestamp) . str_pad($no, 3, "0", STR_PAD_LEFT);
    }

    /**
     * 跨天时使用
     * @param  [type] $timestamp [description]
     * @param  [type] $no        [description]
     * @return [type]            [description]
     */
    static function getNo4y($timestamp, $no, $saleTime = '')
    {
        $no = $no % 120;
        $no = $no == 0 ? 120 : $no;

        if ($no == 120) {
            $timestamp = $timestamp - 86400;
        }
        return date("Ymd", $timestamp) . str_pad($no, 3, "0", STR_PAD_LEFT);

    }

    static function getNo5($timestamp, $no)
    {
        return date("Ymd", $timestamp) . str_pad($no, 2, "0", STR_PAD_LEFT);
    }

    static function getNo0($timestamp, $no)
    {
        return $no;
    }

    static function resultFormat($opencode)
    {
        $res = explode('+', $opencode);
        return $res[0];
    }

    static function resultLhcFormat($opencode)
    {
        $res = explode('+', $opencode);
        return $res;
    }


    /**
     * mq
     * @param [type] $msg      [description]
     * @param [type] $exchange [description]
     */
    static function MQPublish($config, $msg, $exchange)
    {
        if (!isset($config['mq'])) {
            return;
        }
        $connection = new AMQPStreamConnection($config['mq']['host'],
            $config['mq']['port'],
            $config['mq']['user'],
            $config['mq']['password'],
            $config['mq']['vhost'],
            $insist = false,
            $login_method = 'AMQPLAIN',
            $login_response = null,
            $locale = 'en_US',
            $connection_timeout = 10.0,
            $read_write_timeout = 10.0);
        $channel = $connection->channel();
        $msg = new AMQPMessage($msg);
        $channel->exchange_declare($exchange, 'fanout', false, true, false);
        $channel->basic_publish($msg, $exchange);
        $channel->close();
        $connection->close();
    }

    /**
     * 取得销售时间
     * @param  [type] $fetchOne  [description]
     * @param  string $startDate [description]
     * @return [type]            [description]
     */
    static function getSaleTime($fetchOne, $startDate = '')
    {
        $startDate = empty($startDate) ? date('Y-m-d') : $startDate;
        // $endDate = empty($endDate) ? date('Y-m-d') : $startDate;

        $saleTime = $fetchOne['createLottery']['saleTime'];

        $nowSaleTime = [];
        if (is_array($saleTime)) {
            $nowSaleTime = $saleTime;
        } else {
            $nowSaleTime = $saleTime($startDate);
        }

        $t1 = strtotime($startDate) + Utils::timeStrToInt($nowSaleTime[0]);
        $t2 = strtotime($startDate) + Utils::timeStrToInt($nowSaleTime[1]);
        $t2 = $t2 < $t1 ? $t2 + 86400 : $t1;
        $yt1 = strtotime($startDate) - 86400 + Utils::timeStrToInt($nowSaleTime[0]);
        $yt2 = strtotime($startDate) - 86400 + Utils::timeStrToInt($nowSaleTime[1]);
        $isSaleTime = false;
        $after = 3600;
        $now = time();
        if ($now > $t1 && $now < $t2) {
            $isSaleTime = true;
            $after = $fetchOne['createLottery']['interval'];
        }

        if ($now > $yt1 && $now < $yt2) {
            $isSaleTime = true;
            $after = $fetchOne['createLottery']['interval'];
        }

        if ($t1 > $now) {
            $after = $t1 - $now;
        }

        return [$t1, $t2, $yt1, $yt2, $isSaleTime, $after];
    }

    /**
     * 生成区间列表
     * @param  [type] $intervalConfig [description]
     * @return [type]                 [description]
     */
    public static function createIntervalList($fetch, $fetchOne, $intervalConfig, $lotteryNumber, $startDate)
    {
        $db = $fetch->db->getConnection('common');
        $mqData = [];
        // $intervalConfig = [
        //     ['10:00:00', '22:00:00', 10 * 60, 72],
        //     ['22:00:00', '02:00:00', 5 * 60, 48],
        // ];
        $no = $lotteryNumber;
        $now = strtotime($startDate);
        foreach ($intervalConfig as $conf) {
            $start = $now + Utils::timeStrToInt($conf[0]);
            for ($i = 0; $i < $conf[3]; $i++) {
                // 获取默认处理期号方法
                $fetchNo = isset($fetchOne['createLottery']['fetchNo']) ? $fetchOne['createLottery']['fetchNo'] : '';
                // 当前期号
                $useNo = empty($fetchNo) ? $no : $fetchNo($start + $i * $conf[2], $no);
                $mqData[] =
                    [
                        'lottery_number' => $useNo,
                        'pid' => $fetchOne['pid'],
                        'lottery_type' => $fetchOne['id'],
                        'lottery_name' => $fetchOne['name'],
                        'start_time' => $start + ($i - 1) * $conf[2] - $fetchOne['createLottery']['delay'],
                        'end_time' => $start + $conf[2] * ($i - 1 + 1) - $fetchOne['createLottery']['delay'],
//                        'start_time' => date('Y-m-d H:i:s',$start + ($i - 1) * $conf[2] - $fetchOne['createLottery']['delay']),
//                        'end_time' => date('Y-m-d H:i:s',$start + $conf[2] * ($i - 1 + 1) - $fetchOne['createLottery']['delay']),
                    ];

                // $fetch->data->addLotteryInfo(end($mqData));
                $no++;
            }
        }
//        print_r(count($mqData));
//        print_r($mqData);exit;
        $db->table('lottery_info')->insert($mqData);
    }

    /**
     * 扩展取数组方法, 支持步进
     * @param  [type]  $arr   [description]
     * @param  [type]  $start [description]
     * @param  [type]  $len   [description]
     * @param  integer $step [description]
     * @return [type]         [description]
     */
    public static function getArray($arr, $start, $len, $step = 1)
    {
        $arr = array_slice($arr, $start, $len);
        $arr2 = [];
        foreach ($arr as $key => $value) {
            if ($key % $step == 0) {
                $arr2[] = $value;
            }
        }
        return $arr2;
    }

    /**
     * 保存结果并刷新缓存
     * @param  [type] $ci            [description]
     * @param  [type] $id            [description]
     * @param  [type] $lotteryNumber [description]
     * @param  [type] $periodCode    [description]
     * @param  string $openTime [description]
     * @return [type]                [description]
     */
    public static function savePeriod($ci, $id, $lotteryNumber, $periodCode, $openTime, $cacheFilter = true)
    {
        //echo 'savePeriod before:', $id, ' ', $lotteryNumber, PHP_EOL;
        if ($cacheFilter && false===$ci->redisCommon->sadd(\Logic\Define\CacheKey::$perfix['commonLotteryPeriodSet'] . $id, $lotteryNumber)) {
            return false;
        }
         //echo 'savePeriod:', $id, ' ', $lotteryNumber, PHP_EOL;
        $time = time();
        $periodCodes = explode(',', $periodCode);
        $periodResult = array_sum($periodCodes);
        $n = [0 => '', 1 => '', 2 => '', 3 => '', 4 => '', 5 => '', 6 => '', 7 => '', 8 => '', 9 => ''];
        foreach ($periodCodes as $ks => $vs) {
            $n[$ks] = $vs;
        }
      /*  var_dump('id=' .$id);
        echo PHP_EOL;
        var_dump('lotteryNumber='.$lotteryNumber);   echo PHP_EOL;
        var_dump('openTime'.$openTime);   echo PHP_EOL;
        var_dump('lotteryNumber',$lotteryNumber);   echo PHP_EOL;
        var_dump('periodResult',$periodResult);   echo PHP_EOL;
        var_dump('periodCode'.$periodCode);   echo PHP_EOL;
        var_dump($n);die;*/
        \Model\CommonLotteryInfo::where('lottery_type', $id)
            ->where('period_code','')
            ->whereRaw("now() > (end_time + 5)")
            ->where('lottery_number', $lotteryNumber)
            ->where('end_time', '<', $openTime)
            ->update([
                'period_result' => $periodResult,
                'period_code' => $periodCode,
                'n1' => $n[0],
                'n2' => $n[1],
                'n3' => $n[2],
                'n4' => $n[3],
                'n5' => $n[4],
                'n6' => $n[5],
                'n7' => $n[6],
                'n8' => $n[7],
                'n9' => $n[8],
                'n10' => $n[9],
                'catch_time' => $time,
                'official_time' => $openTime,
                'state' => 'open'
            ]);
        //var_dump(\DB::getQueryLog());die;

        $common = $ci->db->getConnection('common');
        $lotteryInfo = $common->table('lottery_info')->where('end_time', '<', time())->where('period_code', '!=',  '')->where('lottery_type', $id)->orderBy('end_time', 'desc')->take(10)->get([
            'period_code',
            'lottery_number',
            'lottery_name',
            'period_result',
            'start_time',
            'end_time',
            'catch_time',
            'official_time',
            'lottery_type','n1', 'n2', 'n3', 'n4', 'n5', 'n6', 'n7', 'n8', 'n9', 'n10'
        ]);

        $ci->redisCommon->set(\Logic\Define\CacheKey::$perfix['commonLotteryPeriod'] . $id, json_encode($lotteryInfo));
        return true;
    }

    /**
     * 判断夏令时
     * @return boolean [description]
     */
    public static function isDst($date = '')
    {
        $timezone = date('e'); //获取当前使用的时区
        date_default_timezone_set('US/Pacific-New'); //强制设置时区
        $dst = empty($date) ? date('I') : date('I', strtotime($date)); //判断是否夏令时
        date_default_timezone_set($timezone); //还原时区
        return $dst; //返回结果
    }

    /**
     * 生成加拿大快乐8彩期
     */
    public static function createCakeno($fetchOne, $date, $content)
    {
        $s1 = 0;
        $s2 = 0;
        $start = Utils::isDst($date) ? '19:30:00' : '20:30:00';
        $end = '22:30:00';
        $st = strtotime($date . ' ' . $start);
        $ed = strtotime($date . ' ' . $end);
        // 保证数据时间由小到大
        $temp = array_reverse($content['data']);
        $delay = 60;
        $interval = 210;

        foreach ($temp as $key => $val) {
            if (!isset($val['opentimestamp'])) {
                continue;
            }
            // if ($val['opentimestamp'] > strtotime('2018-04-19 20:14:50')) {
            //     continue;
            // }

            if ($val['opentimestamp'] > $st && $val['opentimestamp'] < $ed) {
                $s1++;

                if (isset($temp[$key + 1]) && isset($temp[$key - 1])) {
                    $nextTime = $temp[$key + 1]['opentimestamp'];

                    // 增加兼容检测
                    if ($nextTime < $val['opentimestamp']) {
                        $nextTime = $temp[$key - 1]['opentimestamp'];
                    }

                    // 判断开奖时间是否大于间隔时间，大于允许时间才生成彩期
                    if ($nextTime - $val['opentimestamp'] >= $interval) {
                        $s2++;
                    }
                }
            }

            if ($s2 == 1) {
                $data = \Model\CommonLotteryInfo::where('lottery_number', $val['expect'])->where('lottery_type', $fetchOne['id'])->first();
                if (!empty($data)) {
                    continue;
                }

                // 尝试补前三期
                for ($i = 5; $i > 0; $i--) {

                    if (!isset($temp[$key - $i])) {
                        continue;
                    }
                    // echo  $temp[$key - $i]['expect'], PHP_EOL;
                    $useNo = $temp[$key - $i]['expect'];
                    $startTimeTemp = $temp[$key - $i]['opentimestamp'] - $interval - $delay;
                    $endTimeTemp = $temp[$key - $i]['opentimestamp'] - $delay;

                    try {
                        $opencodeBase = Utils::resultFormat($temp[$key - $i]['opencode']);
                        if (isset($fetchOne['resultFormat'])) {
                            $opencode = $fetchOne['resultFormat']($opencodeBase);
                        } else {
                            $opencode = $opencodeBase;
                        }
                        $periodCodes = explode(',', $opencode);
                        $periodResult = array_sum($periodCodes);
                        \Model\CommonLotteryInfo::create([
                            'lottery_number' => $useNo,
                            'lottery_name' => $fetchOne['name'],
                            'pid' => $fetchOne['pid'],
                            'lottery_type' => $fetchOne['id'],
                            'start_time' => $startTimeTemp,
                            'end_time' => $endTimeTemp,
                            'period_code' => $opencode,
                            'period_result' => $periodResult,
                            'catch_time' => time(),
                            'official_time' => $temp[$key - $i]['opentimestamp'],
                        ]);

                        if (!empty($fetchOne['after'])) {
                            foreach ($fetchOne['after'] as $v) {
                                try {
                                    $opencode2 = $v['resultFormat']($opencodeBase);
                                    $periodCodes2 = explode(',', $opencode2);
                                    $periodResult2 = array_sum($periodCodes2);
                                    \Model\CommonLotteryInfo::create([
                                        'lottery_number' => $useNo,
                                        'lottery_name' => $v['name'],
                                        'pid' => $v['pid'],
                                        'lottery_type' => $v['id'],
                                        'start_time' => $startTimeTemp,
                                        'end_time' => $endTimeTemp,
                                        'period_code' => $opencode2,
                                        'period_result' => $periodResult2,
                                        'catch_time' => time(),
                                        'official_time' => $temp[$key - $i]['opentimestamp'],
                                    ]);
                                } catch (\Exception $e) {
                                    // no do something
                                    // echo 'after', PHP_EOL;
                                    // echo $e->getMessage(), PHP_EOL;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // no do something
                        // echo 'before', PHP_EOL;
                        // echo $e->getMessage(), PHP_EOL;
                    }
                }

                // $lotteryNumberEndTime = strtotime(date('Y-m-d 19:56:30', strtotime($date) + 86400));
                $lotteryNumberEndTime = Utils::isDst($date) ? strtotime(date('Y-m-d 18:56:30', strtotime($date) + 86400)) : strtotime(date('Y-m-d 19:56:30', strtotime($date) + 86400));
                $i = 0;
                $num = 500;
                // 生成彩期
                while ($num > 0) {
                    $useNo = $val['expect'] + $i;
                    $startTimeTemp = $val['opentimestamp'] + $interval * ($i - 1) - $delay;
                    $endTimeTemp = $val['opentimestamp'] + $interval * $i - $delay;

                    if ($startTimeTemp > $lotteryNumberEndTime) {
                        break;
                    }

                    try {
                        \Model\CommonLotteryInfo::create([
                            'lottery_number' => $useNo,
                            'lottery_name' => $fetchOne['name'],
                            'pid' => $fetchOne['pid'],
                            'lottery_type' => $fetchOne['id'],
                            'start_time' => $startTimeTemp,
                            'end_time' => $endTimeTemp
                        ]);

                        if (!empty($fetchOne['after'])) {
                            foreach ($fetchOne['after'] as $v) {
                                try {
                                    \Model\CommonLotteryInfo::create([
                                        'lottery_number' => $useNo,
                                        'lottery_name' => $v['name'],
                                        'pid' => $v['pid'],
                                        'lottery_type' => $v['id'],
                                        'start_time' => $startTimeTemp,
                                        'end_time' => $endTimeTemp
                                    ]);
                                } catch (\Exception $e) {
                                    // no do something
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // no do something
                    }
                    $i++;
                    $num--;
                }
                break;
            }
        }
    }

    /**
     * 生成六合彩彩期
     */
    public static function createLhc($fetchOne, $date, $content)
    {
        //$s1 = 0;
        $start = ' 21:40:00';
        $end = ' 21:30:00';
        $now = time();
        // 保证数据时间由小到大
        // $temp = $content['data'];
        $temp = array_reverse($content['data']);
        $interval = 86400 * 2;
        //取temp第一个一个数组值
        $lastData = end($temp);
        $i = 1;
        
        foreach ($temp as $key => $value) {
            // echo  $temp[$key - $i]['expect'], PHP_EOL;
            $useNo = $temp[$key]['expect'];
            $startTimeTemp = $temp[$key]['opentimestamp'] - 60;
            $endTimeTemp = $temp[$key]['opentimestamp'];

            try {
                $opencodeBase = Utils::resultLhcFormat($temp[$key]['opencode']);
                if (isset($fetchOne['resultFormat'])) {
                    $opencode = $fetchOne['resultFormat']($opencodeBase[0]);
                } else {
                    $opencode = $opencodeBase[0];
                }

                $periodCodes = explode(',', $opencode);
                if (isset($opencodeBase[1])) {
                    array_push($periodCodes,$opencodeBase[1]);
                }

                $periodResult = array_sum($periodCodes);
                $period_code = implode(',',$periodCodes);
                \Model\CommonLotteryInfo::create([
                    'lottery_number' => $useNo,
                    'lottery_name' => $fetchOne['name'],
                    'pid' => $fetchOne['pid'],
                    'lottery_type' => $fetchOne['id'],
                    'start_time' => $startTimeTemp,
                    'end_time' => $endTimeTemp,
                    'period_code' => $period_code,
                    'period_result' => $periodResult,
                    'catch_time' => time(),
                    'official_time' => $temp[$key]['opentimestamp'],
                    'n1'=> $periodCodes[0],
                    'n2'=> $periodCodes[1],
                    'n3'=> $periodCodes[2],
                    'n4'=> $periodCodes[3],
                    'n5'=> $periodCodes[4],
                    'n6'=> $periodCodes[5],
                    'n7'=> $periodCodes[6],
                ]);
            } catch (\Exception $e) {
                // no do something
            }
        }
        
        $data = \Model\CommonLotteryInfo::where('lottery_number', $lastData['expect'] + $i)->where('lottery_type', $fetchOne['id'])->first();
        if (empty($data)) {
            //新增彩期
            $useNo = $lastData['expect'] + $i;
            $startTimeTemp = date('Y-m-d',$lastData['opentimestamp']);
            $startTimeTemp = strtotime($startTimeTemp.$start);
            $zhouJi = date('N', $lastData['opentimestamp']);
            if ($zhouJi == '6'){
                $endTimeTemp = date('Y-m-d', $lastData['opentimestamp'] + 86400 * 3);
            }else{
                $endTimeTemp = date('Y-m-d', $lastData['opentimestamp'] + $interval);
            }
            //若当前彩期时间比上期封盘时间之前，说明有误，不给矛生成彩期
            $c = \Model\CommonLotteryInfo::where('lottery_type','=',52)->
                where('lottery_number','<',$useNo)->where('end_time','>',$startTimeTemp)->count();
            if($c >= 1) {
                return;
            }
            $endTimeTemp = strtotime($endTimeTemp.$end);
            try {
                \Model\CommonLotteryInfo::create([
                    'lottery_number' => $useNo,
                    'lottery_name' => $fetchOne['name'],
                    'pid' => $fetchOne['pid'],
                    'lottery_type' => $fetchOne['id'],
                    'start_time' => $startTimeTemp,
                    'end_time' => $endTimeTemp
                ]);
            } catch (\Exception $e) {
                // no do something
            }
        }
    }


}