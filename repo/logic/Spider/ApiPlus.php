<?php

namespace Logic\Spider;

use Requests;
use Logic\Spider\Utils;
use Slim\Container;

/**
 *
 * 开彩网API
 *
 * http://d.apiplus.net/newly.do?token=ta0d1c92300d2805ak&code=cakeno&format=json
 * http://d.apiplus.net/daily.do?token=ta0d1c92300d2805ak&code=cakeno&format=json&date=2017-09-28
 *
 * http://d.apiplus.net/newly.do?token=ta0d1c92300d2805ak&code=twbingo&format=json
 *
 * http://d.apiplus.net/daily.do?token=ta0d1c92300d2805ak&code=gxk3&format=json&date=2017-09-28
 * http://d.apiplus.net/daily.do?token=ta0d1c92300d2805ak&code=jsk3&format=json&date=2017-09-28
 * http://d.apiplus.net/daily.do?token=ta0d1c92300d2805ak&code=ahk3&format=json&date=2017-09-28
 *
 * http://d.apiplus.net/daily.do?token=ta0d1c92300d2805ak&code=jlk3&format=json&date=2017-09-28
 *
 * http://d.apiplus.net/daily.do?token=ta0d1c92300d2805ak&code=twbingo&format=json&date=2017-09-28
 *
 * http://d.apiplus.net/daily.do?token=ta0d1c92300d2805ak&code=bjpk10&format=json&date=2017-09-28
 * http://d.apiplus.net/daily.do?token=ta0d1c92300d2805ak&code=ffc1&format=json&date=2017-09-28
 */
class Apiplus extends \Logic\Logic implements Api {

    protected $url;

    protected $token;

    protected $codes = [

        // 北京快乐8
        42 => 'bjkl8',

        // 加拿大快乐8
        44 => 'cakeno',

        // 五分彩
        17 => 'twbingo',

        // 重庆时时彩
        11 => 'cqssc',

        // 天津时时彩
        13 => 'tjssc',

        // 新疆时时彩
        14 => 'xjssc',

        // 分分彩
        // 16 => 'ffc1',
        // 弃用
        16 => '',

        // 三分彩
        // 19 => 'ffc3',
        // 弃用
        19 => '',

        // 江西11选5
        25 => 'jx11x5',

        // 江苏11选5
        26 => 'js11x5',

        // 广东11选5
        27 => 'gd11x5',

        // 山东11选5
        28 => 'sd11x5',

        // 安徽11选5
        29 => 'ah11x5',

        // 上海11选5
        30 => 'sh11x5',

        // 北京赛车
        40 => 'bjpk10',

        // 幸运飞艇
        45 => 'mlaft',

        // 广西快三
        6  => 'gxk3',

        // 江苏快三
        7  => 'jsk3',

        // 安徽快三
        8  => 'ahk3',

        // 吉林快三
        9  => 'jlk3',

        // 六合彩
        52 => 'hk6',
    ];


    public function __construct(Container $ci) {
        parent::__construct($ci);
        $this->url = $ci->get('settings')['apiplus']['url'];
        $this->token = $ci->get('settings')['apiplus']['token'];
    }

    public function getFast($fetchOne) {
        try {
            $code = isset($this->codes[$fetchOne['id']]) ? $this->codes[$fetchOne['id']] : 0;
            if (!empty($code)) {
                // $this->logger->info("【开彩网】http://d.apiplus.net/newly.do?token={$this->token}&code={$code}&format=json");
                $response = Requests::get("http://d.apiplus.net/newly.do?token={$this->token}&code={$code}&format=json", [], ['timeout' => 10]);
                $content = json_decode($response->body, true);

                $this->logger->info("【开彩网】{$fetchOne['id']}-{$fetchOne['name']} 数据查询", ['url'=>$response->url, 'status'=>$response->status_code]);
                if (empty($content)) {
                    $this->logger->info("【开彩网】【{$fetchOne['name']}】 {getFast} 数据查询失败:" . $response->body);
                    return false;
                }

                $this->_fetch($fetchOne, $content);
                return true;
            } else {
                $this->logger->info("【开彩网】{$fetchOne['name']} code is empty");
            }
        } catch (\Exception $e) {
            $this->logger->info("【开彩网】数据查询失败 " . $e->getMessage());
        } catch (\Error $e) {
            $this->logger->info('【开彩网】数据查询失败：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 从远端API获取彩期
     * 加拿大和六合彩
     *
     * @param $date
     * @param $fetchOne
     *
     * @return bool
     */
    public function getSpec($date, $fetchOne) {
        try {

            $code = isset($this->codes[$fetchOne['id']]) ? $this->codes[$fetchOne['id']] : 0;
            if (empty($code)) {
                $this->logger->info("【开彩网】{$fetchOne['name']} code is empty");
                return false;
            }

            if ($date == '') {
                $this->logger->info("【开彩网】http://d.apiplus.net/newly.do?token={$this->token}&code={$code}&format=json");
                $response = Requests::get("http://d.apiplus.net/newly.do?token={$this->token}&code={$code}&format=json", [], ['timeout' => 10]);
                $date = date('Y-m-d');
            } else {
                $response = Requests::get("http://d.apiplus.net/daily.do?token={$this->token}&code={$code}&format=json&date={$date}", [], ['timeout' => 10]);
                $this->logger->info("【开彩网】http://d.apiplus.net/daily.do?token={$this->token}&code={$code}&format=json&date={$date}");
            }

            $content = json_decode($response->body, true);

            if (empty($content)) {
                $this->logger->info("【开彩网】 【{$fetchOne['name']}】 {getFast} 数据查询失败:" . $response->body);
                return false;
            }

            // 六合彩
            if ($fetchOne['id'] == 52) {
                Utils::createLhc($fetchOne, $date, $content);
            } else {
                Utils::createCakeno($fetchOne, $date, $content);
            }

            $this->_fetch($fetchOne, $content, false);

            return true;
        } catch (\Exception $e) {
            $this->logger->info('【开彩网】获取彩期失败，错误信息：' . $e->getMessage());
            return false;
        } catch (\Error $e) {
            $this->logger->info('【开彩网】获取彩期失败，错误信息：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 从远端API获取彩果
     *
     * @param $fetchOne
     * @param string $date
     *
     * @return bool
     */
    public function getHistory($fetchOne, $date = '') {
        try {
            $code = isset($this->codes[$fetchOne['id']]) ? $this->codes[$fetchOne['id']] : 0;

            if (empty($code)) {
                $this->logger->info("【开彩网】{$fetchOne['name']} code is empty");
                return false;
            }

            $response = Requests::get("http://d.apiplus.net/daily.do?token={$this->token}&code={$code}&format=json&date={$date}", [], ['timeout' => 15]);
            $this->logger->info("【开彩网】{$fetchOne['id']}-{$fetchOne['name']}:{$date}获取结果", ['url'=>$response->url, 'status'=>$response->status_code]);

            $content = json_decode($response->body, true);

            if (empty($content)) {
                $this->logger->info("【开彩网】 【{$fetchOne['name']}】 数据查询失败:" . $response->body);
                return false;
            }

            $this->_fetch($fetchOne, $content, true);
            return true;
        } catch (\Exception $e) {
            $this->logger->info('【开彩网】拉取彩果失败，错误信息：' . $e->getMessage());
            return false;
        } catch (\Error $e) {
            $this->logger->info('【开彩网】拉取彩果失败，错误信息：' . $e->getMessage());
            return false;
        }
    }

    protected function _fetch($fetchOne, $content, $cacheFilter = true) {
        foreach ($content['data'] as $val) {
            if ($fetchOne['id'] == 52) {
                $opencodeBases = Utils::resultLhcFormat($val['opencode']);
                $periodCodes = explode(',', $opencodeBases[0]);
                array_push($periodCodes, $opencodeBases[1]);
                $opencodeBase = implode(',', $periodCodes);
            } else {
                $opencodeBase = Utils::resultFormat($val['opencode']);
            }

            if (isset($fetchOne['resultFormat'])) {
                $opencode = $fetchOne['resultFormat']($opencodeBase);
            } else {
                $opencode = $opencodeBase;
            }
            if (Utils::savePeriod($this->ci, $fetchOne['id'], $val['expect'], $opencode, $val['opentimestamp'], $cacheFilter)) {
                $this->logger->info("【开彩网】 【{$fetchOne['name']}】 结果保存成功 {$val['expect']}  {$opencode}");
                // 消息通知 和 after执行
                if (!empty($fetchOne['after'])) {
                    foreach ($fetchOne['after'] as $v) {
                        $opencode2 = $v['resultFormat']($opencodeBase);
                        if (Utils::savePeriod($this->ci, $v['id'], $val['expect'], $opencode2, $val['opentimestamp'], $cacheFilter)) {
                            // 消息通知
                            $this->logger->info("【开彩网】 【{$v['name']}】 结果保存成功 {$val['expect']}  {$opencode2}");
                        }
                    }
                }
            }
        }
    }

}