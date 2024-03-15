<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require __DIR__ . '/../repo/vendor/autoload.php';
$settings = require __DIR__ . '/../config/settings.php';

$app = new \Slim\App($settings);
// Set up dependencies
require __DIR__ . '/src/dependencies.php';

// Register middleware
require __DIR__ . '/src/middleware.php';

$fetch = new \Logic\Spider\Fetch($app->getContainer());
$logger = $app->getContainer()->logger;

\Workerman\Worker::$logFile =  ROOT_PATH.'/data/logs/php/spiderServer.log';

$app->run();
$app->getContainer()->db->getConnection('default');

$worker = new \Workerman\Worker();
$worker->count = 23;
$worker->name = 'spiderServer';
$worker->onWorkerStart = function ($worker) {
    // global $app;
    $proccId = 0;
    // 生成彩期
    if ($worker->id === $proccId) {
        $interval = 10 * 3600;
        \Workerman\Lib\Timer::add($interval, function () {
            global $fetch;
            foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
                if ($v['id'] == 44 || $v['id'] == 52) {
                    $fetch->specCreateLottery($v, date('Y-m-d'));//加拿大快乐8和六合彩生成彩期
                    // $fetch->cakenoCreateLottery($v, date('Y-m-d'));
                } else {
                    $fetch->createLottery($v, date('Y-m-d'));
                    $fetch->createLottery($v, date('Y-m-d', strtotime('+1 days')));
                }

            }
        });
    }

    //开彩网浆果拉取  总共 19个进程  定时器，一个彩票一个定时器拉取
    foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
        // 【开彩网】加拿大 & 六合彩 彩果拉取
        if(in_array($v['id'],[44,52])){
            $proccId++;
            if ($worker->id === $proccId) {
                $interval = 10;
                \Workerman\Lib\Timer::add($interval, function ($v) {
                    global $app;
                    $req = new \Logic\Spider\ApiCaipaokong($app->getContainer());
                    $req->getSpec('', $v);
                },[$v]);
            }
        }else{
            $proccId++;
            if ($worker->id === $proccId) {
                $interval = 10;
                \Workerman\Lib\Timer::add($interval, function ($v) {
                    global $app;
                    $req = new \Logic\Spider\ApiCaipaokong($app->getContainer());
                    $req->getFast($v);
                },[$v]);
            }
        }
    }

    $proccId++;
    // 【彩票控】彩果拉取
    if ($worker->id === $proccId) {
        // $interval = 3600;
        $interval = 60;
        \Workerman\Lib\Timer::add($interval, function () {
            global $app;
            $req = new \Logic\Spider\ApiCaipaokong($app->getContainer());
            foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
                $req->getFast($v);
                usleep(500000);
            }
        });
    }

    $proccId++;
    // 全量彩果拉取
    if ($worker->id === $proccId) {
        // $interval = 3600;
        $interval = 5 * 60;
        \Workerman\Lib\Timer::add($interval, function () {
            global $app;
            $req = new \Logic\Spider\ApiCaipaokong($app->getContainer());

            foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
                if (time() - strtotime(date('Y-m-d')) > 20 * 60) {
                    $date = date('Y-m-d');
                } else {
                    $date = date('Y-m-d', strtotime('-1 days'));
                }

                if ($v['id'] == 52) {
                    $req->getHistory($v, $date, true);
                } else {
                    $req->getHistory($v, $date);
                }

                usleep(1500000);
            }
        });
    }

    // 检查异常彩种
    $proccId++;
    if ($worker->id === $proccId) {
        $interval = 60;
        \Workerman\Lib\Timer::add($interval, function () {
            global $app, $fetch;
            foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
                // 检查不通过则停售90秒
                if (($ids = $fetch->check($v)) !== true) {
                    foreach ($ids as $id) {
                        $key = \Logic\Define\CacheKey::$perfix['commonLotterySaleStatus'];
                        $app->getContainer()->redisCommon->setex($key . $id, 90, 'close');
                    }
                }
                usleep(500000);
            }
        });
    }
};

\Workerman\Worker::runAll();