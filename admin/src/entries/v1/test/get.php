<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/7/2
 * Time: 10:56
 */

use Logic\Admin\BaseController;
use Logic\Game\GameApi;
use GuzzleHttp\Client;
use Logic\Define\CacheKey;
use GuzzleHttp\Exception\ClientException ;
return new class extends BaseController {

    //前置方法 检查权限等
    protected $beforeActionList = [
//        'verifyToken',
//        'authorize',
    ];

    public function run (){

//        (new \Logic\Game\GameLogic($this->ci))->sendGameRequestInfo();
//        exit;
        $date = $this->request->getParam('date',date('Y-m-d'));
        $cacheKey = CacheKey::$perfix['gameGetOrderRequestInfo'] . $date;
        $lastCacheInfos = $this->redis->hgetall($cacheKey);
        print_r($lastCacheInfos);
        foreach ($lastCacheInfos as $key=>$val){
            if(!$val) continue;
            $lastCacheInfo = json_decode($val,true);
            $recombine['platformName'] = $lastCacheInfo['game_type'];
            $recombine['pullTime'] = date('Y-m-d H:i:s',$lastCacheInfo['pullTime']);
            $recombine['pullCount'] = $lastCacheInfo['pullCount'];
            $recombine['starTime'] = is_numeric($lastCacheInfo['starTime']) ? date('Y-m-d H:i:s',$lastCacheInfo['starTime']) : $lastCacheInfo['starTime'];
            $recombine['endTime'] = is_numeric($lastCacheInfo['endTime']) ? date('Y-m-d H:i:s',$lastCacheInfo['endTime']) : $lastCacheInfo['endTime'];
                var_dump($recombine);
        }
        exit;


        $type = 'VTDZ';
        $config = DB::table('game_menu')->where('type',$type)->first();
        $game = '\Logic\Game\Third\\' . $type;
        (new $game($this->ci))->synchronousData((array)$config);
        exit;
        $date = date('Y-m-d');
        $type = 'AGIN';
        $cacheKey = CacheKey::$perfix['gameGetOrderRequestInfo'] . $date;
        $params = [
            'game_type'=>$type,
            'pullTime'=>time(),
            'pullCount'=>rand(0,50),
            'starTime'=>time(),
            'endTime'=>time(),
        ];
        if(empty($this->redis->hgetall($cacheKey))){
            $this->redis->hset($cacheKey,$type,json_encode($params));
            $this->redis->expire($cacheKey,120);
        }else{
            $this->redis->hset($cacheKey,$type,json_encode($params));
        }

        exit;

        $customers = \DB::table('customer as c')
            ->leftJoin('customer_notify as n','c.id','=','n.customer_id')
            ->where('c.type','game')
//            ->where('c.id',$params['customer_id'])
            ->get()->toArray();
        print_r($customers);exit;
//        echo \Logic\Game\Third\AGIN::$queryOrderParams
//
//        $gameConfig = GameApi::getGameConfig();
        $config = DB::table('game_menu')->where('type','AGIN')->first();
        (new \Logic\Game\Third\JDBDZ($this->ci))->synchronousData((array)$config);
        exit;

//        $this->redis->hmset('mytest-test','last_time',time());
//        $this->redis->hmset('mytest-test','total',10);
//        $res = $this->redis->hgetall('mytest-test');//ZRANGE salary 1 2 WITHSCORES
//        print_r($res);
//        exit;
        $games = \DB::table('game_menu')
            ->where('pid','!=',0)
            ->whereNotIn('id',[26,27])
            ->where('switch','enabled')
            ->get([
                "id",
                "type",
                "name"
            ])->toArray();
//        $games = json_decode($games,true);
//        $games = array_column($games,null,'id');
//        print_r($games);exit;
//        echo var_export($games,true);exit;
        foreach ($games as $game){
            if(!isset($gameConfig[$game->id])){
                continue;
            }
            if(!$gameConfig[$game->id]['getOrderInfo']['request']) continue;
            $gameGetOrderLastTime = $this->ci->redis->get(\Logic\Define\CacheKey::$perfix['gameGetOrderLastTime'].$game->type);
            echo $gameGetOrderLastTime.PHP_EOL;
            if($game->type == 'KAIYUAN'||$game->type == 'LONGCHEN'||$game->type == 'NWG'){
//                echo $gameGetOrderLastTime.PHP_EOL;
                $gameGetOrderLastTime = $gameGetOrderLastTime/1000 + 300;
            }
            $datetime = $gameGetOrderLastTime ? date('Y-m-d H:i:s',$gameGetOrderLastTime) : 'null';
            echo $game->name .' : '.$datetime . PHP_EOL;
//            $res = (new GameApi($this->ci))->getReferralLink($game->type . '('.$game->name.')',$datetime);
//            print_r($res);

        }
        exit;

//        $date = date('Y-m-d');
        $date = $this->request->getParam('date','2019-02-11');
        $req = new \Logic\Spider\ApiCaipaokong($this->ci);
        $req = new \Logic\Spider\ApiPlus($this->ci);
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            echo '彩种名称：'.$v['name'].PHP_EOL;
            echo '开奖间隔：'.($v['createLottery']['interval']/60).'分钟每期'.PHP_EOL;
            $strings = $v['createLottery']['saleTime'];
            echo '开盘时间：'.$strings[0].PHP_EOL;
            echo '封盘时间：'.$strings[1].PHP_EOL;
            echo '每日期数：'.$strings[2].PHP_EOL;
            echo '---------------------------------'.PHP_EOL;
//            if(in_array($v['id'],[11,14,25,27,40]))
//            if ($v['id'] == 30) {
////                $req->getFast($v);
//                $req->getHistory($v,$date);

//            }
        }

        exit;
        $req = new \Logic\Spider\ApiPlus($this->ci);
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            if ($v['id'] == 52) {
                $req->getSpec('', $v);
            }
        }
        exit;
        $fetch = new \Logic\Spider\Fetch($this->ci);
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            if ($v['id'] == 44 || $v['id'] == 52) {
                // $fetch->cakenoCreateLottery($v, date('Y-m-d'));
            } else{
                $fetch->createLottery($v, $date);
                $fetch->createLottery($v, date('Y-m-d', strtotime('+1 days')));
            }

        }
        exit;
        echo $date;
        $stopshelling = Logic\Set\SetConfig::DATA['system.config.global']['stopshelling'];

        if($date >= $stopshelling['date_start'] && $date <= $stopshelling['date_end']){
            echo 666;
        }else{
            echo 777;
        }

        print_r($stopshelling);exit;

        $params = $this->request->getParams();


        echo 777;
        print_r($this->request->getParams());exit;

    }

};