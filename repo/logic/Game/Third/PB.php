<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;
use function GuzzleHttp\Psr7\str;

/**
 * Class PB体育
 */
class PB extends GameLogic
{
    /**
     * @var string 订单表
     */
    protected $orderTable = 'game_order_pb';
    protected $game_type = 'PB';

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
        $now    = time();
        $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $this->game_type);//上次的结束时间
        if($r_time) {
            $startTime = $r_time;
        } else {
            $startTime = $now - 24*60*60; //取60分钟内的数据
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
            $val['wagerId'] = (string) $val['wagerId'];
            //校验更新，存在不处理
            if ($updateStatus) {
                if (\DB::table($this->orderTable)->where('wagerId', $val['wagerId'])->count()) {
                    continue;
                }
            }
            $val['loginId'] = strtolower($val['loginId']);

            date_default_timezone_set("Etc/GMT+4");
            $WagersTime = strtotime($val['wagerDateFm']);//下注时间
            $settleDateFm = strtotime($val['settleDateFm']);//结算时间
            $eventDateFm = strtotime($val['eventDateFm']);//赛事时间
            date_default_timezone_set($default_timezone);

            if(empty($val['selection']) && !empty($val['parlaySelections'])){
                $val['selection'] = join(',', array_column($val['parlaySelections'], 'selection'));
                $val['sport'] = join(',', array_column($val['parlaySelections'], 'sport'));
            }
            if(empty($val['eventName']) && !empty($val['parlaySelections'])){
                $val['eventName'] = $val['parlaySelections'][0]['eventName'];
            }

            $insertData[] = [
                'tid' => intval(ltrim($val['loginId'], 'game')),
                'wagerId' => $val['wagerId'],
                'loginId' => $val['loginId'],
                'eventName' => $val['eventName'] ?? '',
                'wagerDateFm' => date('Y-m-d H:i:s', $WagersTime),
                'settleDateFm' => date('Y-m-d H:i:s', $settleDateFm),
                'eventDateFm' => date('Y-m-d H:i:s', $eventDateFm),
                'odds' => $val['odds'] ?? '',
                'oddsFormat' => $val['oddsFormat'] ?? '',
                'betType' => $val['betType'] ?? '',
                'leagueId' => $val['leagueId']?? '',
                'league' => $val['league'] ?? '',
                'stake' => $val['stake'],
                'sportId' => $val['sportId']??'',
                'sport' => $val['sport'] ?? '',
                'product' => $val['product'] ?? '',
                'isResettle' => $val['isResettle'] ? 1: 0,
                'winLoss' => $val['winLoss'],
                'turnover' => $val['turnover'],
                'result' => $val['result'] ?? '',
                'selection' => $val['selection'] ?? '',
                'profit' => $val['winLoss'],
                'currencyCode' => $val['currencyCode'],
                'status' => $val['status']
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
            'username' => 'loginId',
            'bet' => 'stake',
            'win' => 'stake+winLoss',
            'profit' => 'winLoss',
            'gameDate' => 'settleDateFm'
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
        $endTime   = strtotime($etime);
        //每次最大拉取区间24小时内
        if($endTime - $startTime > 86400) {
            $endTime = $startTime + 86400;
        }

        $default_timezone = date_default_timezone_get();
        date_default_timezone_set("Etc/GMT+4");
        $fields = [
            'dateFrom' => date('Y-m-d H:i:s', $startTime),
            'dateTo'   => date('Y-m-d H:i:s', $endTime),
            'settle'    => 1,//1: settle 0: unsettle -1: all (both settle and unsettle) (Default: -1)
            'filterBy'  => 'settle_date',
        ];
        date_default_timezone_set($default_timezone);
        $res = $this->requestParam('/report/all-wagers', $fields, false);
        //接口报错
        if(!$res['responseStatus']) {
            return false;
        }
        unset($res['responseStatus'], $res['networkStatus']);
        if(!empty($res)) {
            $this->updateOrder($res);
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
    public function orderByHour($stime, $etime)
    {
        return $this->orderByTime($stime, $etime);
    }

    /**
     * 发送请求
     * @param string $action 请求方法
     * @param array $param 请求参数
     * @param bool $is_post 是否为POST
     * @return array|string
     */
    public function requestParam(string $action, array $param, $is_post = true)
    {
        $proxy = $this->ci->get('settings')['PBProxy'];
        if(is_null($proxy)){
            $ret = [
                'responseStatus' => false,
                'message'        => 'no config PBPROXY'
            ];
            GameApi::addElkLog($ret,'PB');
            return $ret;
        }

        $config = $this->initConfigMsg($this->game_type);
        if(!$config) {
            $ret = [
                'responseStatus' => false,
                'message'        => 'api not config'
            ];
            GameApi::addElkLog($ret, $this->game_type);
            return $ret;
        }
        
        $url = rtrim($config['orderUrl'], '/') . $action;

        //2.4. Generate Token 产生令牌
        $header=[
            'userCode: ' . $config['cagent'],
            'token: ' . $this->generateToken($config['cagent'], $config['key'], $config['pub_key'])
        ];
        if($is_post){
            $re = Curl::post($url, null, $param, null, true, $header, $proxy);
        }else{
            $url = $url.'?'.urldecode(http_build_query($param));
            $re = Curl::get($url, null, true, $header, $proxy);
        }

        if ($re['status'] != 200) {
            $ret['responseStatus'] = false;
            $ret['networkStatus'] = $re['status'];
            $ret['message'] = $re['content'];
            GameApi::addRequestLog($url, $config['type'], $param, json_encode($re, JSON_UNESCAPED_UNICODE));
        } else {
            $ret = json_decode($re['content'], true);
            $ret['networkStatus'] = 200;
            if((isset($ret['trace']) && !empty($ret['trace'])) || (isset($ret['code']) && $ret['code'] > 0)){
                $ret['responseStatus'] = false;
            }else{
                $ret['responseStatus'] = true;
            }
            GameApi::addRequestLog($url, $config['type'], $param, json_encode($ret, JSON_UNESCAPED_UNICODE));
        }
        return $ret;
    }

    /**
     * 2.4. Generate Token 产生令牌
     * @param $agentCode
     * @param $agentKey
     * @param $secretKey
     * @return string
     */
    public function generateToken($agentCode, $agentKey, $secretKey)
    {
        $timestamp = time()*1000;
        $hashToken = md5($agentCode. $timestamp . $agentKey);
        $tokenPayLoad = $agentCode . '|' . $timestamp . '|' . $hashToken;
        $token = $this->encryptAES($secretKey, $tokenPayLoad);

        return $token;
    }
    private function encryptAES($secretKey, $tokenPayLoad)
    {
        $iv = "RandomInitVector";
        $encrypt = openssl_encrypt($tokenPayLoad, "AES-128-CBC", $secretKey, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encrypt);
    }
}
