<?php
require __DIR__ . '/../../repo/vendor/autoload.php';
$settings = require __DIR__ . '/../../config/settings.php';

\Workerman\Worker::$logFile = LOG_PATH . '/php/gameCheckServer.log';
$worker = new \Workerman\Worker();
$worker->count = 6;
$worker->name = 'mxnSuperGameCheckServer';

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
    // 第三方  JILI 拉订单 1
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('JILI');
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
    // 第三方  FC 拉订单 3
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('FC');
        });
    }

    $processId++;
    // 第三方  QT 拉订单 4
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('QT');
        });
    }

    $processId++;
    // 第三方  PG 拉订单 5
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('PG');
        });
    }

    $processId++;
    // 第三方  IG 拉订单 6
    if ($worker->id === $processId) {
        $interval = 600;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousCheckData('IG');
        });
    }
};

\Workerman\Worker::runAll();