<?php

namespace Logic\Game;


use Logic\Logic;
use GuzzleHttp\Client;
use Logic\Define\CacheKey;
use GuzzleHttp\Exception\ClientException;

/**
 *  获取对应的第三方游戏实例
 * @author lwx
 *
 */
class GameApi extends Logic
{

    static $LOG = LOG_PATH . '/game/';
    protected $nameSpace = 'Logic\Game\Third';
    protected $gameType = '';
    protected $thirdGameClass;

    public static function getGameConfig()
    {
        return require __DIR__ . '/GameConfig.php';
    }

    public function init($gameType = '', $namespace = '')
    {
        if (!$gameType)
            return;

        $this->gameType = $gameType;
        $this->nameSpace = $namespace ? $namespace : $this->nameSpace;


    }

    /**
     * 第三方同步订单
     *
     * @param $game_type
     *
     * @return mixed
     * @throws \Exception
     */
    public function synchronousData($game_type)
    {
        try {
            $class = $this->nameSpace . '\\' . $game_type;
            if (!class_exists($class)) {
                throw new \Exception('no class ' . $class);
            }
            $obj = new $class($this->ci);
            $obj->synchronousData();
        } catch (\Exception $e) {
            $this->logger->error('synchronousData error ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 第三方同步对局详情
     *
     * @param $game_type
     *
     * @return mixed
     * @throws \Exception
     */
    public function synchronousPlayDetail($game_type)
    {
        try {
            $class = $this->nameSpace . '\\' . $game_type;
            if (!class_exists($class)) {
                throw new \Exception('no class ' . $class);
            }
            $obj = new $class($this->ci);
            $obj->synchronousPlayDetail();
        } catch (\Exception $e) {
            $this->logger->error('synchronousData error ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 校验第三方订单
     *
     * @param $game_type
     *
     * @return mixed
     * @throws \Exception
     */
    public function synchronousCheckData($game_type)
    {
        try {
            $class = $this->nameSpace . '\\' . $game_type;
            if (!class_exists($class)) {
                throw new \Exception('no class ' . $class);
            }
            //无校验方法
            if (!is_callable(array($class, 'synchronousCheckData'))) {
                return true;
            }
            $obj = new $class($this->ci);
            $obj->synchronousCheckData();
        } catch (\Exception $e) {
            $this->logger->error('synchronousData error ' . $e->getMessage());
            throw $e;
        }
    }

    public function getGameObj(string $game_type)
    {
        $class = $this->nameSpace . '\\' . $game_type;
        if (!class_exists($this->nameSpace . '\\' . $game_type)) {
            return false;
        }
        return new $class($this->ci);
    }

    public static function addRequestLog(
        string $url,
        string $gameType,
        array $req,
        string $resp,
        string $remark = ''
    )
    {
        $data = [
            'url' => $url,
            'game_type' => $gameType,
            'request' => $req,
            'response' => $resp,
            'remark' => $remark,
        ];
        self::addElkLog($data, $gameType);
//        \DB::table('game_request_log')->insert($data);
    }

    public function callback($action = 'login')
    {

        return $this->$action();
    }

    public static function addElkLog($data, $path = 'game', $file = 'request')
    {
        if (!is_dir(self::$LOG . $path) && !mkdir(self::$LOG . $path, 0777, true)) {
            $path = '';
        } else {
            $path .= '/';
        }
        $file = self::$LOG . $path . '/' . $file . '-' . date('Y-m-d-H') . '.log';
        $stream = @fopen($file, "aw+");
        if (isset($data['response']) && self::is_json($data['response'])) {
            $data['response'] = json_decode($data['response'], true);
        }
        $data['logTime'] = date('Y-m-d H:i:s');
        $str = urldecode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . PHP_EOL;
        @fwrite($stream, $str);
        @fclose($stream);
    }

    public static function addJsonFile($data, $path = 'game', $filname = '')
    {
        if (!is_dir(self::$LOG . $path) && !mkdir(self::$LOG . $path, 0777, true)) {
            $path = '';
        } else {
            $path .= '/';
        }
        if(empty($filname)){
            $filname = date('Y-m-d') . '.log';
        }
        $file = self::$LOG . $path . $filname;
        $stream = @fopen($file, "aw+");
        @fwrite($stream, $data);
        @fclose($stream);
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

    /**
     * XML解析成数组
     */
    public static function parseXML2($xmlSrc)
    {
        if (empty($xmlSrc)) {
            return false;
        }
        $array = array();
        $xml = simplexml_load_string($xmlSrc);
        $encode = self::getXmlEncode($xmlSrc);
        if ($xml && $xml->children()) {
            foreach ($xml->children() as $node) {
                //有子节点
                if ($node->children()) {
                    $k = $node->getName();
                    $nodeXml = $node->asXML();
                    $v = self::parseXML($nodeXml);
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
    public static function getXmlEncode($xml)
    {
        $ret = preg_match("/<?xml[^>]* encoding=\"(.*)\"[^>]* ?>/i", $xml, $arr);
        if ($ret) {
            return strtoupper($arr[1]);
        } else {
            return "";
        }
    }

    public static function sCurDay($date, $gameType)
    {
        $customer = \DB::table('customer as c')
            ->leftJoin('customer_notify as n', 'c.id', '=', 'n.customer_id')
            ->where('n.status', 'enabled')
            ->where('c.type', 'game')
            ->groupBy('n.customer_id')->get([
                'c.id as customer_id',
                'c.customer',
                'n.admin_notify',
            ])->toArray();

        foreach ($customer as $val) {
            $queryParams = [
                'start_date' => $date,
                'end_date' => $date . ' 23:59:59',
                'game_type' => $gameType,
            ];
            $url = $val->admin_notify . '/report/OverPipe?' . http_build_query($queryParams);
            $res = json_decode(\Utils\Curl::get($url), true)['data'] ?? [];
            if (!$res || !isset($res['game_type'])) continue;
            $d = [
                'customer_id' => $val->customer_id,
                'customer' => $val->customer,
                'game_type' => $res['game_type'],
                'game_name' => $res['game_name'],
                'count' => $res['list'][0]['count'] ?? 0,
                'bet' => $res['list'][0]['bet'] ?? 0,
                'valid_bet' => $res['list'][0]['valid_bet'] ?? 0,
                'win_loss' => $res['list'][0]['win_loss'] ?? 0,
                'date' => $res['list'][0]['date'] ?? $date,
                'updated' => date('Y-m-d H:i:s'),
            ];
            \DB::table('checkdata')->updateOrInsert([
                'game_type' => $d['game_type'],
                'date' => $d['date'],
                'customer_id' => $val->customer_id,
            ], $d);
        }
    }

    public function thirdRequestInfo()
    {
        try {

            $gameConfig = self::getGameConfig();
            $games = \DB::table('game_menu')
                ->where('pid', '!=', 0)
                ->whereNotIn('id', [26, 27])
                ->where('switch', 'enabled')
                ->get([
                    "id",
                    "type",
                    "name"
                ])->toArray();
            foreach ($games as $game) {
                if (!isset($gameConfig[$game->id])) {
                    continue;
                }
                if (!$gameConfig[$game->id]['getOrderInfo']['request']) continue;
                $gameGetOrderLastTime = $this->ci->redis->get(\Logic\Define\CacheKey::$perfix['gameGetOrderLastTime'] . $game->type);
                echo $gameGetOrderLastTime . PHP_EOL;
                if ($game->type == 'KAIYUAN' || $game->type == 'LONGCHEN' || $game->type == 'NWG') {
//                echo $gameGetOrderLastTime.PHP_EOL;
                    $gameGetOrderLastTime = $gameGetOrderLastTime / 1000 + 300;
                }
                $datetime = $gameGetOrderLastTime ? date('Y-m-d H:i:s', $gameGetOrderLastTime) : 'null';
                echo $game->name . ' : ' . $datetime . PHP_EOL;
                $res = (new GameApi($this->ci))->getReferralLink($game->type . '(' . $game->name . ')', $datetime);
                print_r($res);
            }
        } catch (\Exception $e) {

        }

    }

    public function getReferralLink($pv = 'AGIN', $pullTime = 0, $pullCount = 0)
    {
//        $response = Requests::request('http://tgnb.cc/shared/generate', [], [
//            'pv' => $pv,
//            'invite_code'  => $invit_code,
//        ], Requests::POST, ['timeout' => 10]);
//        print_r($response);exit;


        $client = new Client();
        $body = [
            'platformName' => $pv,
            'pullTime' => $pullTime,
            'pullCount' => $pullCount,
        ];
        try {
            $result = $client->request('POST', 'https://tgnb.cc/monitor/create/third', [
                'form_params' => $body,
                'timeout' => 5,
            ]);
            $res = $result->getBody();

            if ($result->getStatusCode() == 200) {
                $res = json_decode($res, true);
                return $res;
            }
        } catch (\Exception $e) {
            $this->ci->logger->error('lastGetGameOrderError', ['message' => $e->getMessage()]);
        }
    }

    public static function is_json($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * 报表统计
     * @param int $num 负几天的数据 1昨天数据，2前天数据
     * @param null $date 跑具体日期数据
     * @return bool
     */
    public function rptAllOrdersMiddleDay($num = 0, $date = null)
    {
        if($num){
            $date   = date('Y-m-d', strtotime(empty($date) ? "-{$num} day" : $date));//默认前一天
            //加锁
            $lock_key = $this->redis->setnx(\Logic\Define\CacheKey::$perfix['queryOperatesOrderTime'] . $date, 1);
            $this->redis->expire(\Logic\Define\CacheKey::$perfix['queryOperatesOrderTime'] . $date, 22 * 60 * 60);
            if(!$lock_key) {
                $this->logger->error('【统计】已经重新统计昨天数据 ' . $date);
                return false;
            }
        }
        $games = \DB::table('game_menu')
            ->where('pid', '!=', 0)
            ->whereNotIn('id', [26, 27])
            ->where('switch', 'enabled')
            ->groupBy('alias')
            ->get(["alias"])->toArray();

        try {
            foreach ($games as $game) {
                $class = $this->nameSpace . '\\' . $game->alias;
                if (!class_exists($class)) {
                    continue;
                }
                //无校验方法
                if (!is_callable(array($class, 'queryOperatesOrder'))) {
                    continue;
                }
                $obj = new $class($this->ci);
                $obj->queryOperatesOrder($date);
                sleep(5);
            }
        } catch (\Exception $e) {
            $this->logger->error('queryOperatesOrder error ' . $e->getMessage());
        }

    }

    /**
     * 小飞机拉单间隔时间超过20分钟报警
     */
    public function gameOrderAWSAlarmMsg(){

        $site_type = $this->ci->get('settings')['website']['site_type'];
        $content = '';

        $now = time();

        $games = \DB::table('game_menu')
            ->where('pid', '!=', 0)
            ->whereNotIn('id', [26, 27])
            ->where('status', 'enabled')
            ->where('switch', 'enabled')
            ->groupBy('alias')
            ->get(["alias"])->toArray();

        $i = 1;
        foreach($games as $game){
            //UG非时间
            if(in_array($game->alias,['UG','AWS','SEXYBCRT'])){
                continue;
            }
            $r_time = $this->redis->get(CacheKey::$perfix['gameGetOrderLastTime'] . $game->alias);
            if(is_null($r_time)){
                continue;
            }

            //PG毫秒
            if($game->alias == 'PG'){
                $r_time =  strtotime(date('Y-m-d H:i:s', substr($r_time, 0, 10)));
            }
            $isdelay = 0; //是否延期

            //WM 30分钟延期
            if($game->alias == 'WM'){
                if( $now - $r_time > 50 * 60){
                    $isdelay = 1;
                }
            }elseif(in_array($game->alias, ['JILI','JOKER'])){
                //10分钟延期
                if( $now - $r_time > 30 * 60){
                    $isdelay = 1;
                }
            }elseif($game->alias == 'PP'){
                if ($now - $r_time > 2 * 60 * 60){
                    $isdelay = 1;
                }
            }else{
                if ($now - $r_time > 20 * 60){
                    $isdelay = 1;
                }
            }

            if($isdelay){
                if($i == 1){
                    $content = $site_type . "超管拉单延迟超过20分钟未拉单：" . PHP_EOL;
                    $content .= "请检查原因并重启进程gameServer" . PHP_EOL . PHP_EOL;
                }

                $content .= '游戏厂商：' . $game->alias . PHP_EOL;
                $content .= '上次拉单时间：' . date('Y-m-d H:i:s', $r_time) . PHP_EOL;
                $content .= '告警时间：' .  date('Y-m-d H:i:s', $now) . PHP_EOL . PHP_EOL;
                $i++;
            }

            //echo $game->alias . ' - ' . $r_time.PHP_EOL;
        }
        if(!empty($content)){
            $this->sendAWSAlarmMsg($content);
        }
    }

    /**
     * game_order_error表警告
     */
    public function gameOrderErrorAWSAlarmMsg()
    {
        $site_type = $this->ci->get('settings')['website']['site_type'];
        $content = '';

        $now = time();

        $list = \DB::table('game_order_error')
            ->groupBy('game_type')
            ->selectRaw('game_type,error')
            ->get()->toArray();
        $i = 1;
        if($list){
            foreach($list as $val){
                if($i == 1){
                    $content = $site_type . "超管拉单报错：" . PHP_EOL;
                    $content .= "请检查game_order_error表SQL报错原因，字段需要加所有站" . PHP_EOL . PHP_EOL;
                }
                $content .= '游戏厂商：' . $val->game_type . PHP_EOL;
                $content .= 'SQL错误：' . $val->error . PHP_EOL;
                $content .= '告警时间：' .  date('Y-m-d H:i:s', $now) . PHP_EOL . PHP_EOL;
                $i++;
            }
        }


        if(!empty($content)){
            $this->sendAWSAlarmMsg($content);
        }
    }

    /**
     * AWS小飞机告警消息
     * @param string $content 消息内容
     */
    public function sendAWSAlarmMsg($content){
        $url="https://api.telegram.org/bot5678446635:AAENIzvtdA29iA-iljZ-bRb-kP7U8XcKaMs/sendMessage";
        $json = [
            'chat_id' => '-723590479',
            "text" => $content,
        ];

        \Utils\Curl::post($url, null, $json);
    }
}
