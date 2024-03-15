<?php
require __DIR__ . '/../../repo/vendor/autoload.php';
$settings = require __DIR__ . '/../../config/settings.php';

\Workerman\Worker::$logFile = LOG_PATH . '/php/gameServer.log';
$worker = new \Workerman\Worker();
$worker->count = 21;
$worker->name = 'mxnSuperGameServer';

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
    // 清理game_order_error表 1
    if ($worker->id === $processId) {
        $interval = 30;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameLogic($app->getContainer());
            $game->clearGameOrderError();
        });
    }

    $processId++;
    // rpt_orders_middle_day表 2
    if ($worker->id === $processId) {
        $interval = 600;//10分钟一次
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->rptAllOrdersMiddleDay();
        });
    }

    $processId++;
    // 重新统计昨天数据 3
    if ($worker->id === $processId) {
        $interval = 600;//10分钟一次
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            if (date("H") == '06') {
                $game = new \Logic\Game\GameApi($app->getContainer());
                $game->rptAllOrdersMiddleDay(1);
            }
        });
    }

    $processId++;
    //小飞机警告消息 4
    if ($worker->id === $processId && RUNMODE == 'product') {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->gameOrderAWSAlarmMsg();
        });
    }

    $processId++;
    //小飞机game_order_error警告消息 5
    if ($worker->id === $processId && RUNMODE == 'product') {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->gameOrderErrorAWSAlarmMsg();
        });
    }

    $processId++;
    //JDB 拉订单 6
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('JDB');
        });
    }

    $processId++;
    //JILI 拉订单 7
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('JILI');
        });
    }
    
    $processId++;
    //PP电子 拉订单 8
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PP');
        });
    }

    $processId++;
    //PP捕鱼 拉订单 9
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PPLC');
        });
    }


    $processId++;
    //MG 拉订单 10
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('MG');
        });
    }

    $processId++;
    //PB 拉订单 11
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PB');
        });
    }

    $processId++;
    //FC 拉订单 12
    if ($worker->id === $processId) {
        $interval = 10;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('FC');
        });
    }

    $processId++;
    //BSG 拉订单 13
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('BSG');
        });
    }

    $processId++;
    //HB 拉订单 14
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('HB');
        });
    }

   /* $processId++;
    //PP捕鱼 拉订单 15
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PPBY');
        });
    }*/

    $processId++;
    //QT 拉订单 15
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('QT');
        });
    }

    $processId++;
    //ALLBET 拉订单 16 //一分钟10次
    if ($worker->id === $processId) {
        $interval = 10;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('ALLBET');
        });
    }

    $processId++;
    //BTI 拉订单 16
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('BTI');
        });
    }

    $processId++;
    //PG 拉订单 18
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PG');
        });
    }

    $processId++;
    //IG 拉订单 19
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('IG');
        });
    }

    $processId++;
    //EVO 拉订单 20
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('EVO');
        });
    }

    $processId++;
    //EVOPLAY 拉订单 21
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('EVOPLAY');
        });
    }
};

\Workerman\Worker::runAll();