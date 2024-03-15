<?php
require __DIR__ . '/../repo/vendor/autoload.php';

$settings = require __DIR__ . '/../config/settings.php';

$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/src/dependencies.php';

// Register middleware
require __DIR__ . '/src/middleware.php';

$app->run();
$app->getContainer()->db->getConnection('default');

//$alias = $app->getContainer()
//             ->get('settings')['website']['alias'];

//$alias = strtoupper($alias) . 'Server';
$alias = 'Server';

if (empty($alias)) {
    echo 'please input website alias';
    exit;
}

$logger = $app->getContainer()->logger;

\Workerman\Worker::$logFile =  ROOT_PATH.'/data/logs/php/callbackServer.log';

$worker = new \Workerman\Worker();
$worker->count = 4;
$worker->name = 'PlatCallback'.$alias;

// 防多开配置
// if ($app->getContainer()->redis->get(\Logic\Define\CacheKey::$perfix['callbackServer'])) {
//     echo 'callbackServer服务已启动，如果已关闭, 请等待5秒再启动', PHP_EOL;
//     exit;
// }

$worker->onWorkerStart = function ($worker) {
    global $app, $logger;

    $processId = 0;

    if ($worker->id === $processId) {
        $exchange = 'recharge_callback';
        $queue = $exchange . '_1';

        $callback = function ($msg) use ($exchange, $queue, $logger, $app) {
            try {
                $logger->info("【 $exchange, $queue 】" . $msg->body);
                $params = json_decode($msg->body, true);
                $callback = new Logic\Recharge\Recharge($app->getContainer());
                $callback->creaseCustomer($params['order_number'], $params['money']);
                echo $params['order_number'], ':', $params['money'], PHP_EOL;
            } catch (\Exception $e) {
                $logger->error($msg->body);
            }
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        };

        \Utils\MQServer::startServer($exchange, $queue, $callback);
    }

    $processId++;
    // 定时跑因其它原因失败的
    if ($worker->id === $processId) {
        $interval = 10 * 60;
        \Workerman\Lib\Timer::add($interval, function () {
            Logic\Recharge\Recharge::callbackFailedRepeat();
        });
    }

    $processId++;
    // 定时跑因进程线程出问题而未向客户提交通知的订单  补发客户通知
    if ($worker->id === $processId) {
        $interval = 30;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $callback = new Logic\Recharge\Recharge($app->getContainer());
            echo 'test', PHP_EOL;
            $callback->notifyCustomer();
        });
    }

    $processId++;
    // 定时跑官方支付渠道，只负责回调验签
    if ($worker->id === $processId) {
        $interval = 30;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $callback = new Logic\Recharge\Recharge($app->getContainer());
            echo 'test', PHP_EOL;
            $callback->notifyCustomer2();
        });
    }
};

\Workerman\Worker::runAll();