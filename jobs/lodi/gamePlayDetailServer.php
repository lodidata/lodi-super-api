<?php
require __DIR__ . '/../../repo/vendor/autoload.php';
$settings = require __DIR__ . '/../../config/settings.php';

\Workerman\Worker::$logFile = LOG_PATH . '/php/gamePlayDetailServer.log';
$worker = new \Workerman\Worker();
$worker->count = 4;
$worker->name = 'lodiSuperPlayDetailGameServer';

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
    //IG 拉取对局详情
    if ($worker->id === $processId) {
        $interval = 20;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousPlayDetail('IG2');
        });
    }

    $processId++;
    //IG 拉订单 2
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('IG2');
        });
    }

    $processId++;
    //IG 拉订单 3
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('IG3');
        });
    }

    $processId++;
    //IG 拉取对局详情 4
    if ($worker->id === $processId) {
        $interval = 20;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousPlayDetail('IG3');
        });
    }
};

\Workerman\Worker::runAll();