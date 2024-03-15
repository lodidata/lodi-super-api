<?php

namespace Logic\Spider;

use Requests;
use Logic\Spider\Utils;
use Slim\Container;

/**
 *
 * 彩票控API
 *
 * 广东11选5
 * http://api.kaijiangtong.com/lottery/?name=gdsyxw&format=json&uid=813226&token=05f5e3579fd8a1abc2f3a9b08ee04ad5a8eebff9
 *
 * http://api.kaijiangtong.com/lottery/?name=twxbg&format=json&uid=813226&token=05f5e3579fd8a1abc2f3a9b08ee04ad5a8eebff9
 *
 */
class ApiCaipaokong extends \Logic\Logic implements Api {

    protected $url;

    protected $token;

    protected $fetch;

    protected $uid;

    protected $codes = [

        // 北京快乐8
        42 => 'bjklb', // 校正通过

        // 加拿大快乐8
        44 => 'jndklb', // 校正通过

        // 五分彩
        // 17 => 'twbg',
        17 => 'twbg', // 校正通过

        // 重庆时时彩
        11 => 'cqssc', // 校正通过

        // 天津时时彩
        13 => 'tjssc', // 校正通过

        // 新疆时时彩
        14 => 'xjssc', // 校正通过

        // 分分彩
        16 => '',

        // 三分彩
        19 => '',

        // 江西11选5
        25 => 'jxsyxw', // 校正通过

        // 江苏11选5
        26 => 'jssyxw', // 校正通过

        // 广东11选5
        27 => 'gdsyxw', // 校正通过

        // 山东11选5
        28 => 'sdsyydj', // 校正通过

        // 安徽11选5
        29 => 'ahsyxw', // 校正通过

        // 上海11选5
        30 => 'shsyxw', // 校正通过

        // 北京赛车
        40 => 'bjpks', // 校正通过

        // 幸运飞艇
        45 => 'xyft', // 校正通过

        // 广西快三
        6  => 'gxks', // 校正通过

        // 江苏快三
        7  => 'jsks', // 校正通过

        // 安徽快三
        8  => 'ahks', // 校正通过

        // 吉林快三
        9  => 'jlks', // 校正通过

        // 香港六合彩
        52 => 'xglhc',
    ];

    public function __construct(Container $ci) {
        parent::__construct($ci);
        $this->uid = $ci->get('settings')['caipaokong']['uid'];
        $this->token = $ci->get('settings')['caipaokong']['token'];
        $this->url = $ci->get('settings')['caipaokong']['url'];
    }

    public function getFast($fetchOne) {
        try {
            // 转换为彩票控对应的彩票代号
            $code = isset($this->codes[$fetchOne['id']]) ? $this->codes[$fetchOne['id']] : 0;
            if (empty($code)) {
                // $this->logger->info("【彩票控】{$fetchOne['name']} code is empty");
                return false;
            }

            $this->logger->info("【彩票控】http://api.kaijiangtong.com/lottery/?token={$this->token}&name={$code}&uid={$this->uid}&format=json&num=3");
            $response = Requests::get("http://api.kaijiangtong.com/lottery/?token={$this->token}&name={$code}&uid={$this->uid}&format=json&num=10", [], ['timeout' => 10]);
            $content = json_decode($response->body, true);

            if (empty($content)) {
                $this->logger->info("【彩票控】 【{$fetchOne['name']}】 {getFast} 数据查询失败:" . $response->body);
                return false;
            }

            $this->_fetch($fetchOne, $content);

            return true;
        } catch (\Exception $e) {
            $this->logger->info("【彩票控】 【{$fetchOne['name']}】 {getFast} 数据查询失败:" . $e->getMessage());
            return false;
        }
    }

    /**
     * [getHistory description]
     * @param  [type] $params ['date' => '2011-01-01']
     * @return [type] [description]
     */
    public function getHistory($fetchOne, $date = ''){
        // 转换为彩票控对应的彩票代号
        $code = isset($this->codes[$fetchOne['id']]) ? $this->codes[$fetchOne['id']] : 0;
        if (empty($code)) {
            $this->logger->info("【彩票控】{$fetchOne['name']} code is empty");
            return false;
        }

        $date = str_replace('-', '', $date);

        $response = Requests::get("http://api.kaijiangtong.com/lottery/?token={$this->token}&name={$code}&uid={$this->uid}&format=json&date={$date}", [], ['timeout' => 15]);

        $this->logger->info("【彩票控】{$fetchOne['id']}-{$fetchOne['name']}:{$date}获取结果", ['url'=>$response->url, 'status'=>$response->status_code]);

        $content = json_decode($response->body, true);
        if (empty($content)) {
            $this->logger->info("【彩票控】 【{$fetchOne['name']}】 数据查询失败:" . $response->body);
            return false;
        }

        $this->_fetch($fetchOne, $content, false);
        return true;
    }

    /**
     * 加拿大和六合彩彩期生成方法
     *
     * @param  [type] $date     [description]
     * @param  [type] $fetchOne [description]
     *
     * @return [type]           [description]
     */
    public function getSpec($date, $fetchOne) {
        $this->logger->info("【彩票控】{$fetchOne['id']}-{$fetchOne['name']}:{$date}生成彩期");
        $stopshelling = \Logic\Set\SetConfig::DATA['system.config.global']['stopshelling'];
        if(in_array($fetchOne['id'], $stopshelling['lottery'])){
            if($date >= $stopshelling['date_start'] && $date <= $stopshelling['date_end']){
                $this->logger->info("【彩票控】{$fetchOne['name']}:{$date}彩种封盘中！！");
                return;
            }
        }

        $date = str_replace('-', '', $date);
        $code = isset($this->codes[$fetchOne['id']]) ? $this->codes[$fetchOne['id']] : 0;
        if (empty($code)) {
            $this->logger->info("【彩票控】{$fetchOne['name']} code is empty");
            return false;
        }

        if ($date == '') {
            // $this->logger->info("【彩票控】http://d.apiplus.net/newly.do?token={$this->token}&code={$code}&format=json");
            $response = Requests::get("http://api.kaijiangtong.com/lottery/?token={$this->token}&name={$code}&uid={$this->uid}&format=json&num=10", [], ['timeout' => 15]);
            $date = date('Ymd');
        } else {
            $response = Requests::get("http://api.kaijiangtong.com/lottery/?token={$this->token}&name={$code}&uid={$this->uid}&format=json&date={$date}", [], ['timeout' => 15]);
            // $this->logger->info("【彩票控】http://d.apiplus.net/daily.do?token={$this->token}&code={$code}&format=json&date={$date}");
        }
        $this->logger->info("【彩票控】{$fetchOne['id']}-{$fetchOne['name']}:{$date}获取结果", ['url'=>$response->url, 'status'=>$response->status_code]);

        $content = json_decode($response->body, true);

        if (empty($content)) {
            $this->logger->info("【彩票控】【{$fetchOne['name']}】 {getFast} 数据查询失败:" . $response->body);
            return false;
        }

        // 转换成开彩网格式
        $apiPlusContent = ['data' => []];
        foreach ($content as $lotteryNumber => $v) {
            $apiPlusContent['data'][] = [
                'opencode'      => $v['number'],
                'opentimestamp' => strtotime($v['dateline']),
                'expect'        => $this->_formatLotteryNumber($fetchOne, $lotteryNumber),
            ];
        }

        // 六合彩
        if ($fetchOne['id'] == 52) {
            Utils::createLhc($fetchOne, $date, $apiPlusContent);
        } else {
            Utils::createCakeno($fetchOne, $date, $apiPlusContent);
        }

        $this->_fetch($fetchOne, $content, false);
        return true;
    }

    private function _resultFormat($fetchOne, $opencode) {
        if (in_array($fetchOne['id'], [17, 42, 44])) {
            $opencodes = explode(',', $opencode);
            array_pop($opencodes);
            $opencode = join(",", $opencodes);
        }
        return $opencode;
        // $opencodes = explode( ',' , $opencode );
        // $opencodes = array_map("intval",$opencodes);
        // return join(',',$opencodes);
    }

    protected function _formatLotteryNumber($fetchOne, $lotteryNumber) {
        if (in_array($fetchOne['id'], [6, 26, 27, 29, 7, 9])) {
            $lotteryNumber = intval('20' . strval($lotteryNumber));
        } else if (in_array($fetchOne['id'], [14])) {
            $lotteryNumber = strval($lotteryNumber);
            $len = strlen($lotteryNumber);
            $lotteryNumber = substr($lotteryNumber, 0, $len - 2) . '0' . substr($lotteryNumber, $len - 2, 2);
            $lotteryNumber = intval($lotteryNumber);
        }
        // $this->logger->info($fetchOne['name'].' '.$lotteryNumber);
        return $lotteryNumber;
    }

    protected function _fetch($fetchOne, $content, $cacheFilter = true) {
        foreach ($content as $lotteryNumber => $val) {
            $lotteryNumber = $this->_formatLotteryNumber($fetchOne, $lotteryNumber);
            $val['opentimestamp'] = strtotime($val['dateline']);
            //$opencodeBase = Utils::resultFormat($val['number']);
            $opencodeBase = $this->_resultFormat($fetchOne, $val['number']);
            if (isset($fetchOne['resultFormat'])) {
                $opencode = $fetchOne['resultFormat']($opencodeBase);
            } else {
                $opencode = $opencodeBase;
            }

            if (Utils::savePeriod($this->ci, $fetchOne['id'], $lotteryNumber, $opencode, $val['opentimestamp'], $cacheFilter)) {

                $this->logger->info("【彩票控】【{$fetchOne['name']}】 结果保存成功 {$lotteryNumber}  {$opencode}");
                // 消息通知 和 after执行

                if (!empty($fetchOne['after'])) {
                    foreach ($fetchOne['after'] as $v) {
                        $opencode2 = $v['resultFormat']($opencodeBase);
                        if (Utils::savePeriod($this->ci, $v['id'], $lotteryNumber, $opencode2, $val['opentimestamp'], $cacheFilter)) {
                            // 消息通知
                            $this->logger->info("【彩票控】【{$v['name']}】 结果保存成功 {$lotteryNumber}  {$opencode2}");
                        }
                    }
                }
            }
        }
    }
}