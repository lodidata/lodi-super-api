<?php
require __DIR__ . '/../../repo/vendor/autoload.php';
$settings = require __DIR__ . '/../../config/settings.php';

\Workerman\Worker::$logFile = LOG_PATH . '/php/tempServer.log';
$worker = new \Workerman\Worker();
$worker->count = 22;
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
        $end_time = '2023-03-23 00:00:00';
        while (1) {
            $tmp_end_time = date('Y-m-d H:i:s', strtotime($start_time) + 3600);
            if ($tmp_end_time > $end_time) {
                exit;
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
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }


    $processId++;
    //JILI 拉订单 3
    if ($worker->id === $processId) {
        $interval = 10;
        $game = new \Logic\Game\Third\JILI($app->getContainer());
        $start_time = '2023-03-22 12:00:00';
        $end_time = '2023-03-23 00:00:00';
        while (1) {
            $tmp_end_time = date('Y-m-d H:i:s', strtotime($start_time) + 3600);
            if ($tmp_end_time > $end_time) {
                exit;
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
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //AT 拉订单 5
    if ($worker->id === $processId) {
        $game = new \Logic\Game\Third\AT($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //CG 拉订单 6
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\CG($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //FC 拉订单 7
    if ($worker->id === $processId) {
        $interval = 10;
        $game = new \Logic\Game\Third\FC($app->getContainer());
        $start_time = '2023-03-22 12:00:00';
        $end_time = '2023-03-23 00:00:00';
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
    //BNG 拉订单 8
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\Third\BNG($app->getContainer());
            $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
        });
    }


    $processId++;
    //WM 拉订单 9
    if ($worker->id === $processId) {
        $interval = 60;
        $game = new \Logic\Game\Third\WM($app->getContainer());
        $start_time = '2023-03-22 12:00:00';
        $end_time = '2023-03-23 00:00:00';
        while (1) {
            $tmp_end_time = date('Y-m-d H:i:s', strtotime($start_time) + 900);
            if ($tmp_end_time > $end_time) {
                break;
            }
            $game->orderByTime($start_time, $tmp_end_time);
            sleep(10);
            $start_time = $tmp_end_time;
        }
    }

    $processId++;
    //AWS 拉订单 10
    if ($worker->id === $processId) {
        $interval = 10;
        $game = new \Logic\Game\Third\AWS($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //IG 拉订单 11
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\IG($app->getContainer());
        $start_time = '2023-03-22 12:00:00';
        $end_time = '2023-03-23 00:00:00';
        while (1) {
            $tmp_end_time = date('Y-m-d H:i:s', strtotime($start_time) + 900);
            if ($tmp_end_time > $end_time) {
                break;
            }
            $game->orderByTime($start_time, $tmp_end_time);
            sleep(100);
            $start_time = $tmp_end_time;
        }
    }


    $processId++;
    //GFG捕鱼 拉订单 13
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\GFG($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //EVO 拉订单 14
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\EVO($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //YGG详情表处理取消 15
    if ($worker->id === $processId) {
        $interval = 30;
        $game = new \Logic\Game\Third\YGG($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //YESBINGO 16
    if ($worker->id === $processId) {
        $interval = 30;
        $game = new \Logic\Game\Third\YESBINGO($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //EVORT 17
    if ($worker->id === $processId) {
        $interval = 30;
        $game = new \Logic\Game\Third\EVORT($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //SEXYBCRT 拉订单 18
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\SEXYBCRT($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //SA 拉订单 19
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\SA($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //KMQM 拉订单 20
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\KMQM($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }

    $processId++;
    //AVIA 拉订单 21
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\AVIA($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }


    $processId++;
    //MG 拉订单 22
    if ($worker->id === $processId) {
        $interval = 120;
        $game = new \Logic\Game\Third\MG($app->getContainer());
        $game->orderByTime('2023-03-22 12:00:00', '2023-03-23 00:00:00');
    }
};

\Workerman\Worker::runAll();