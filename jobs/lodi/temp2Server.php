<?php
require __DIR__ . '/../../repo/vendor/autoload.php';
$settings = require __DIR__ . '/../../config/settings.php';

\Workerman\Worker::$logFile = LOG_PATH . '/php/tempServer.log';
$worker = new \Workerman\Worker();
$worker->count = 2;
$worker->name = 'lodiSuperTempServer';

// 防多开配置
// if ($app->getContainer()->redis->get(\Logic\Define\CacheKey::$perfix['callbackServer'])) {
//     echo 'callbackServer服务已启动，如果已关闭, 请等待5秒再启动', PHP_EOL;
//     exit;
// }

$worker->onWorkerStart = function ($worker) {
    global $app, $logger;
    /**********************config start*******************/
    $settings = require __DIR__ . '/../../config/settings.php';
    $app = new \Slim\App($settings);
    require __DIR__ . '/../src/dependencies.php';
    require __DIR__ . '/../src/middleware.php';
    $app->run();
    $app->getContainer()->db->getConnection('default');
    $logger = $app->getContainer()->logger;

    /**********************config end*******************/

    $processId = 0;

    //FC 拉订单 7
    if ($worker->id === $processId) {
        $interval = 10;
        $game = new \Logic\Game\Third\FC($app->getContainer());
        $start_time = '2023-03-23 00:00:00';
        $end_time = '2023-03-23 14:00:00';
        while (1) {
            $tmp_end_time = date('Y-m-d H:i:s', strtotime($start_time) + 300);
            if ($tmp_end_time > $end_time) {
                exit;
            }
            $game->orderByTime($start_time, $tmp_end_time);
            sleep(10);
            $start_time = $tmp_end_time;
        }
    }

    $processId++;
    //IG 拉订单 11
    if ($worker->id === $processId) {
        $interval = 100;
        $game = new \Logic\Game\Third\IG($app->getContainer());
        $start_time = '2023-03-23 00:00:00';
        $end_time = '2023-03-23 14:00:00';
        while (1) {
            $tmp_end_time = date('Y-m-d H:i:s', strtotime($start_time) + 900);
            if ($tmp_end_time > $end_time) {
                break;
            }
            $game->orderByTime($start_time, $tmp_end_time);
            sleep($interval);
            $start_time = $tmp_end_time;
        }
    }

};

\Workerman\Worker::runAll();