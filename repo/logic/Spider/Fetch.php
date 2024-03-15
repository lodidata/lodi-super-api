<?php
namespace Logic\Spider;


class Fetch extends \Logic\Logic {

    public static $lottery;

    public static function getTaskConfig() {
        return require __DIR__.'/TaskConfig.php';
    }

    //开彩网
    public function openprize($fetchOne, $date) {
        $req = new ApiPlus($this->ci);
        $req->getHistory($fetchOne, $date);
    }

    //彩票控
    public function openprize2($fetchOne, $date) {
        $req = new ApiCaipaokong($this->ci);
        $req->getHistory($fetchOne, $date);
    }

    //开彩网
    public function specCreateLottery2($fetchOne, $date) {
        $req = new ApiPlus($this->ci);
        $req->getSpec($date, $fetchOne);
    }

    //彩票控接口创建彩期
    public function specCreateLottery($fetchOne, $date) {
        $req = new ApiCaipaokong($this->ci);
        $req->getSpec($date, $fetchOne);
    }

    /**
     * 创建彩期
     * @param  [type] $fetchOne  [description]
     * @param  string $startDate [description]
     * @param  string $timerId   [description]
     * @return [type]            [description]
     */
    public function createLottery($fetchOne, $startDate = '', $manual = false) {
        $this->logger->info("{$fetchOne['id']}-{$fetchOne['name']}:{$startDate}生成彩期");
        $stopshelling = \Logic\Set\SetConfig::DATA['system.config.global']['stopshelling'];
        if(in_array($fetchOne['id'], $stopshelling['lottery'])){
            if($startDate >= $stopshelling['date_start'] && $startDate <= $stopshelling['date_end']){
                $this->logger->info("【彩票控】{$fetchOne['name']}:{$startDate}彩种封盘中！！");
                return ;
            }
        }

        $db = $this->db->getConnection('common');
        $this->logger->info("【{$fetchOne['name']}】 {$startDate} 开始生成...");
        $startDate = empty($startDate) ? date('Y-m-d') : $startDate;
        $saleTime = $fetchOne['createLottery']['saleTime'];
        $nowSaleTime = [];
        if (is_array($saleTime)) {
            $nowSaleTime = $saleTime;
        } else {
            $nowSaleTime = $saleTime($startDate);
        }

        $startTime = \Logic\Spider\Utils::timeStrToInt($nowSaleTime[0]) - $fetchOne['createLottery']['delay'];
        $endTime = \Logic\Spider\Utils::timeStrToInt($nowSaleTime[1]) - $fetchOne['createLottery']['delay'];

        if (!$manual) {
            // 判断是否在开奖区
            $is = $db->table('lottery_info')
            ->where('lottery_type', $fetchOne['id'])
            ->where('end_time', '>', strtotime($startDate) + $startTime)
            ->count();
        } else {
            // 判断是否在开奖区
            $is = $db->table('lottery_info')->where('lottery_type', $fetchOne['id'])
            ->where('end_time', '>=', strtotime($startDate) + $startTime)
            ->where('end_time', '<=', strtotime($startDate) + $endTime + ($startTime > $endTime ? 86400 : 0))
            ->count();
        }

        if (!$is) {
            $fetchInterval = isset($fetchOne['createLottery']['fetchInterval']) ? $fetchOne['createLottery']['fetchInterval'] : '';
            // 判断是否使用特别方法执行彩期生成
            $endTime = $endTime < $startTime ? $endTime + 86400 : $endTime;

            // 使用数据库计算期号
            if (!empty($fetchOne['createLottery']['start'][0] && $fetchOne['createLottery']['start'][0] == 'db')) {
                $configStartTime = strtotime($fetchOne['createLottery']['start'][1].' '.$fetchOne['createLottery']['saleTime'][0]);
                //var_dump($configStartTime);
                $configEndTime = $configStartTime + $fetchOne['createLottery']['interval'];
                //var_dump($configEndTime);
                // 判断是否大于配置里的生成时间
                if (strtotime($startDate.' '.$fetchOne['createLottery']['saleTime'][0]) > $configEndTime) {
                    $configStartTime = strtotime($startDate.' '.$fetchOne['createLottery']['saleTime'][0]);
                } 

                $data = \Model\CommonLotteryInfo::where('lottery_type', $fetchOne['id'])->where('start_time', '>=', $configStartTime)->first();
                
                if (empty($data)) {
                    $lastData = \Model\CommonLotteryInfo::where('lottery_type', $fetchOne['id'])->where('start_time', '<=', $configStartTime)->first();


                } else {
                    $this->logger->info("【{$fetchOne['name']}】 {$startDate} 彩期己生成");
                    return false;
                }

            } else if (!empty($fetchOne['createLottery']['start'][0])) { // 自动计算期号
                //print_r(12343242423423);
                $no = \Logic\Spider\Utils::getNo($startDate,
                    $saleTime,
                    $fetchOne['createLottery']['interval'],
                    $fetchOne['createLottery']['start'][0],
                    $fetchOne['createLottery']['start'][1]);
            } else {
                // 不需要计算期号
                //$this->logger->info("【{$fetchOne['name']}1111111】 {$fetchOne['createLottery']['start'][0]}");
                $no = $fetchOne['createLottery']['start'][1];
                $this->logger->info("【期号】 {$fetchOne['createLottery']['start'][1]}");
            }
            //print_r($no);die;
            $this->logger->info("【{$fetchOne['name']}】 {$startDate} 开始期号: {$no}");
            $defaultFetchNum = 9999;
            $fetchNum = isset($nowSaleTime[2]) ? $nowSaleTime[2] : $defaultFetchNum;
            $calNum = $fetchNum;
            if (empty($fetchInterval)) {
                $nowDay = strtotime($startDate);
                $insertData = [];
                while ($calNum--) {
                    $startTimeTemp = $startTime + $nowDay;
                    $endTimeTemp = $startTime + $fetchOne['createLottery']['interval'] + $nowDay;

                    if ($fetchNum == $defaultFetchNum && $startTime >= $endTime) {
                        break;
                    }

                    // 获取默认处理期号方法
                    $fetchNo = isset($fetchOne['createLottery']['fetchNo']) ? $fetchOne['createLottery']['fetchNo'] : '';
                    // 当前期号
                    $useNo = empty($fetchNo) ? $no : $fetchNo($startTimeTemp + $fetchOne['createLottery']['delay'], $no, $fetchOne['createLottery']['saleTime']);

                    $insertData[] = [
                            'lottery_number' => $useNo,
                            'lottery_name' => $fetchOne['name'],
                            'pid' => $fetchOne['pid'],
                            'start_time' => $startTimeTemp,
                            'end_time' => $endTimeTemp,
//                            'start_time' => date('Y-m-d H:i:s',$startTimeTemp),
//                            'end_time' => date('Y-m-d H:i:s',$endTimeTemp),
                            'lottery_type' => $fetchOne['id'],
                    ];

                    if (!empty($fetchOne['after'])) {
                        foreach ($fetchOne['after'] as $v) {
                            $insertData[] = [
                                'lottery_number' => $useNo,
                                'lottery_name' => $v['name'],
                                'pid' => $v['pid'],
                                'start_time' => $startTimeTemp,
                                'end_time' => $endTimeTemp,
                                'lottery_type' => $v['id'],
                            ];
                        }
                    }
                    $startTime = $startTime + $fetchOne['createLottery']['interval'];
                    $no++;
                }
//                print_r(count($insertData));
//                print_r($insertData);
                $db->table('lottery_info')->insert($insertData);
            } else {
                // 执行特定方法生成彩期
                $fetchInterval($this, $fetchOne, $no, $startDate);
                $this->logger->info("【执行特定方法生成彩期{$fetchOne['name']}】 {$startDate} 彩期已生成");
            }
        } else {
//            echo '彩种：'.$fetchOne['id'] .'彩期已经生成过' ;
            $this->logger->info("【{$fetchOne['name']}】 {$startDate} 彩期已生成");
        }
    }

    /**
     * 执行始化(测试用)
     * @return [type] [description]
     */
    public static function initTest() {
        global $app;
        if (empty(self::$lottery)) {
            self::$lottery = \Model\Lottery::where('pid', '>', 0)->get()->toArray();
        }
        return self::$lottery;
    }

    /**
     * 加载彩期(测试用)
     * @return [type] [description]
     */
    public static function runLoadLotteryTest($lottery) {
        global $app;
        foreach ($lottery as $v) {
            $lotteryInfo = \Model\CommonLotteryInfo::whereRaw("UNIX_TIMESTAMP() <= end_time")
            ->where('lottery_type', $v['id'])
            ->orderBy('lottery_number', 'asc')
            ->take(15)
            ->get()
            ->toArray();
            // print_r($lotteryInfo);
            $app->getContainer()->redisCommon->set(\Logic\Define\CacheKey::$perfix['spiderServerLotteryTest'].$v['id'], json_encode($lotteryInfo, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 执行开奖(测试用)
     * @return [type] [description]
     */
    public static function runTest($lottery) {
        global $app, $logger;
        $db = $app->getContainer()->db->getConnection();
        foreach ($lottery as $v) {
            if (in_array($v['id'], array_keys(\Logic\Lottery\OpenPrize::$tables))) {
                continue;
            }

            $lotteryInfo = (array) json_decode($app->getContainer()->redisCommon->get(\Logic\Define\CacheKey::$perfix['spiderServerLotteryTest'].$v['id']), true);
            foreach ($lotteryInfo as $v2) {
                $lotteryNumber = $v2['lottery_number'] - 1;
                if ($v2['start_time'] + 15 < time() && $app->getContainer()->redisCommon->sadd(\Logic\Define\CacheKey::$perfix['spiderServerTest'].$v['id'], $lotteryNumber)) {

                    $openCode = \Logic\Lottery\OpenPrize::getPeriodCode($v['pid']);
                    if ($openCode == null) {
                        $logger->info('跳过开奖', $v);
                        continue;
                    }
                    $logger->info('开奖:'.$openCode, $v);
                    // lottery_info 写入开奖结果
                    $periodResult = array_sum(explode(',', $openCode));
                    \Model\CommonLotteryInfo::where('lottery_number', $lotteryNumber)
                    ->where('lottery_type', $v2['lottery_type'])
                    ->where('period_code', '=', '')
                    ->update([
                        'period_code' => $openCode, 
                        'state' => 'open',
                        'catch_time' => $db->raw('end_time + 20'),
                        'official_time' => $db->raw('end_time + 20'),
                        'period_result' => $periodResult
                    ]);
                }
            }
        }
    }

    /**
     * 检查彩种是否停止销售
     * @param  [type] $fetchOne  [description]
     * @param  [type] $timestamp [description]
     * @return [type]            [description]
     */
    public function check($fetchOne, $checkNumberCount = 3, $delayCheckTime = 60) {
        $now = time();
        $currentlotteryInfo = \Model\CommonLotteryInfo::where('lottery_type', $fetchOne['id'])->where('start_time', '>', $now - $delayCheckTime)->where('end_time', '<=', $now - $delayCheckTime)->first();

        if (empty($currentlotteryInfo)) {
            return true;
        }

        $checkLotteryNumbers = [];
        for ($i = 1;$i < $checkLotteryNumbers + 1;$i++) {
            $checkLotteryNumbers[] = $currentlotteryInfo['lottery_number'] - $i;
        }

        // 开不出奖
        $count = \Model\CommonLotteryInfo::where('lottery_type', $fetchOne['id'])->whereIn('lottery_number', $checkLotteryNumbers)->where('period_code','=', '')->count();

        if ($count > 0) {
            $ids = [];
            $ids[] = $fetchOne['id'];
            if (isset($fetchOne['after'])) {
                foreach ($fetchOne['after'] as $v) {
                    $ids[] = $v['id'];
                }
            }

            return $ids;
        }

        return true;
    }
}