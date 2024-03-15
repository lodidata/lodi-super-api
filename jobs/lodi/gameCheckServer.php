<?php
require __DIR__ . '/../../repo/vendor/autoload.php';
$settings = require __DIR__ . '/../../config/settings.php';

\Workerman\Worker::$logFile = LOG_PATH . '/php/gameCheckServer.log';
$worker = new \Workerman\Worker();
$worker->count = 15;
$worker->name = 'lodiSuperGameCheckServer';

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
    // 第三方  CQ9 拉订单 1
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('CQ9');
        });
    }

    $processId++;
    // 第三方  JDB 拉订单 2
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('JDB');
        });
    }

    $processId++;
    // 第三方  KMQM 拉订单 3
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('KMQM');
        });
    }

    $processId++;
    // 第三方  SEXYBCRT 拉订单 4
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('SEXYBCRT');
        });
    }

    $processId++;
    // 第三方  SA 拉订单 5
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('SA');
        });
    }

    $processId++;
    // 第三方  AT 拉订单 6
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('AT');
        });
    }

    $processId++;
    // 第三方  CG 拉订单 7
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('CG');
        });
    }

    $processId++;
    // 第三方  UG 拉订单 8
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('UG');
        });
    }

    $processId++;
    // 第三方  BNG 拉订单 9
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('BNG');
        });
    }


    $processId++;
    // 第三方  AWS 拉订单 10
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('AWS');
        });
    }

    $processId++;
    // 第三方  WM 拉订单 11
    if ($worker->id === $processId) {
        $interval = 40;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('WM');
        });
    }

   /* $processId++;
    // 第三方  XG 拉订单 14
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('XG');
        });
    }*/

    $processId++;
    // 第三方  PG 拉订单 12
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('PG');
        });
    }

    $processId++;
    // 第三方  IG 拉订单 13
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('IG');
        });
    }

    $processId++;
    // 第三方  YESBINGO 拉订单 14
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('YESBINGO');
        });
    }

    $processId++;
    // 第三方  EVOPLAY 拉订单 15
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('EVOPLAY');
        });
    }
};

\Workerman\Worker::runAll();