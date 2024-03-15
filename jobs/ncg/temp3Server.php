<?php
require __DIR__ . '/../../repo/vendor/autoload.php';
$settings = require __DIR__ . '/../../config/settings.php';

\Workerman\Worker::$logFile = LOG_PATH . '/php/tempServer.log';
$worker = new \Workerman\Worker();
$worker->count = 20;
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
    //CQ9 拉订单1
    if ($worker->id === $processId) {
        $interval = 10;
        $game = new \Logic\Game\Third\CQ9($app->getContainer());
        $start_time = '2023-03-22 12:00:00';
        $end_time = '2023-03-22 14:00:00';
        while (1) {
            $tmp_end_time = date('Y-m-d H:i:s', strtotime($start_time) + 3600);
            if ($tmp_end_time > $end_time) {
                break;
            }
            $game->orderByTime($start_time, $tmp_end_time);
            sleep(10);
            $start_time = $tmp_end_time;
        }
    }

    $processId++;
    //JDB 拉订单 2
    if ($worker->id === $processId) {
        $interval = 10;
        $game = new \Logic\Game\Third\JDB($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-22 14:00:00');
    }


    $processId++;
    //JILI 拉订单 3
    if ($worker->id === $processId) {
        $interval = 10;
        $game = new \Logic\Game\Third\JILI($app->getContainer());
        $start_time = '22023-03-22 12:00:00';
        $end_time = '2023-03-23 14:00:00';
        while (1) {
            $tmp_end_time = date('Y-m-d H:i:s', strtotime($start_time) + 3600);
            if ($tmp_end_time > $end_time) {
                break;
            }
            $game->orderByTime($start_time, $tmp_end_time);
            sleep(10);
            $start_time = $tmp_end_time;
        }
    }

    $processId++;
    //PP 拉订单 4
    if ($worker->id === $processId) {
        $interval = 10;
        $game = new \Logic\Game\Third\PP($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-22 14:00:00');
    }

    $processId++;
    //JOKER 拉订单 5
    if ($worker->id === $processId) {
        $interval = 10;
        $game = new \Logic\Game\Third\JOKER($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-22 14:00:00');
    }


    $processId++;
    //SEXYBCRT 拉订单 6
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\SEXYBCRT($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-22 14:00:00');
    }

    $processId++;
    //SA 拉订单 7
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\SA($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-22 14:00:00');
    }

    $processId++;
    //KMQM 拉订单 8
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\KMQM($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-22 14:00:00');
    }

    $processId++;
    //SGMK 拉订单 9
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\SGMK($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-22 14:00:00');
    }


    $processId++;
    //RSG 拉订单 10
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\RSG($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-22 14:00:00');
    }

    $processId++;
    //TCG 拉订单 11
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\TCG($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 14:00:00');
    }
};

\Workerman\Worker::runAll();