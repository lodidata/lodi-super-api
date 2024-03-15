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

if (RUNMODE != 'dev') {
    echo '不是开发环境不能启动', PHP_EOL;
    exit;
}

$fetch = new \Logic\Spider\Fetch($app->getContainer());
$logger = $app->getContainer()->logger;
$app->run();
$app->getContainer()->db->getConnection('default');
$worker = new \Workerman\Worker();
$worker->count = 4;
$worker->name = 'spiderTestServer';
$worker->onWorkerStart = function ($worker) {
    global $app, $fetch;
    $proccId = 0;

    $lottery = \Logic\Spider\Fetch::initTest();

    if ($worker->id === $proccId) {
        \Logic\Spider\Fetch::runLoadLotteryTest($lottery, $lottery);
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            if ($v['id'] == 44) {
                // $fetch->cakenoCreateLottery($v, date('Y-m-d'));
            } else {
                $fetch->createLottery($v, date('Y-m-d'));
                $fetch->createLottery($v, date('Y-m-d', strtotime('+1 days')));
            }

        }
    }

    // 生成奖果
    if ($worker->id === $proccId && true) {
        $interval = 5;
        \Workerman\Lib\Timer::add($interval, function() use (&$lottery)
        {
            global $app, $logger;
            \Logic\Spider\Fetch::runTest($lottery);
        });
    }

    $proccId++;
    // 生成彩期
    if ($worker->id === $proccId && true) {
        $interval = 10 * 3600;
        \Workerman\Lib\Timer::add($interval, function()
        {
            global $fetch;
            foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
                if ($v['id'] == 44) {
                    // $fetch->cakenoCreateLottery($v, date('Y-m-d'));
                } else {
                    $fetch->createLottery($v, date('Y-m-d'));
                    $fetch->createLottery($v, date('Y-m-d', strtotime('+1 days')));
                }

            }
        });
    }


};

\Workerman\Worker::runAll();