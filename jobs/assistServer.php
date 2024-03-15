<?php
//棋牌辅助软件
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

\Workerman\Worker::$logFile =  ROOT_PATH.'/data/logs/php/assistServer.log';

$worker = new \Workerman\Worker();

$worker->count = 1;
$worker->name = 'assist'.$alias;

// 防多开配置
// if ($app->getContainer()->redis->get(\Logic\Define\CacheKey::$perfix['callbackServer'])) {
//     echo 'callbackServer服务已启动，如果已关闭, 请等待5秒再启动', PHP_EOL;
//     exit;
// }

$worker->onWorkerStart = function ($worker) {
    global $app, $logger;

    $processId = 0;
    // 第三方  AG 拉订单 1
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $time = date('Y-m-d H:i:s');
//            启用
            \DB::table('goods')->where('start_time', '<=', $time)->where('end_time', '>=', $time)->update(['status'=>1]);
//            禁用
            \DB::table('goods')->where('start_time', '>', $time)->orWhere('end_time', '<', $time)->update(['status'=>2]);
        });
    }
};

\Workerman\Worker::runAll();