<?php
require __DIR__ . '/../../repo/vendor/autoload.php';
$settings = require __DIR__ . '/../../config/settings.php';

\Workerman\Worker::$logFile = LOG_PATH . '/php/gameServer.log';
$worker = new \Workerman\Worker();
$worker->count = 31;
$worker->name = 'lodiSuperGameServer';

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

    //CQ9 拉订单 6
    $processId++;
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('CQ9');
        });
    }

    $processId++;
    //JDB 拉订单 7
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('JDB');
        });
    }

    $processId++;
    //KMQM 拉订单 8
    if ($worker->id === $processId) {
        $interval = 300; //5分钟一次
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('KMQM');
        });
    }

    $processId++;
    //SEXYBCRT 拉订单 9
    if ($worker->id === $processId) {
        $interval = 300;//5分钟一次
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('SEXYBCRT');
        });
    }

    $processId++;
    //SA 拉订单 10
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('SA');
        });
    }

    $processId++;
    //AT 拉订单 11
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('AT');
        });
    }

   /* $processId++;
    //AVIA 拉订单 13
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('AVIA');
        });
    }*/

    $processId++;
    //CG 拉订单 12
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('CG');
        });
    }

    $processId++;
    //DS88 拉订单 13
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('DS88');
        });
    }

    $processId++;
    //BNG 拉订单 14
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('BNG');
        });
    }

    $processId++;
    //UG 拉订单 15
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('UG');
        });
    }

    $processId++;
    //DG 拉订单 16
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('DG');
        });
    }

    $processId++;
    //WM 拉订单 17
    if ($worker->id === $processId) {
        $interval = 60;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('WM');
        });
    }

    $processId++;
    //AWS 拉订单 18
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('AWS');
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
    //YGG 拉订单 20
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('YGG');
        });
    }

    /*$processId++;
    //XG 拉订单 24
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('XG');
        });
    }*/

    $processId++;
    //YGG详情表处理 21
    if ($worker->id === $processId) {
        $interval = 30;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\Third\YGG($app->getContainer());
            $game->syncOrderDetail();
        });
    }

    $processId++;
    // PG 拉订单 22
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PG');
        });
    }

    $processId++;
    // PB 拉订单 23
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PB');
        });
    }

    $processId++;
    // MG 拉订单 24
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('MG');
        });
    }

   /* $processId++;
    //PP捕鱼 拉订单 29
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PPBY');
        });
    }*/

    $processId++;
    //GFG捕鱼 拉订单 25
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('GFG');
        });
    }

    $processId++;
    //EVO 拉订单 26
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('EVO');
        });
    }

    $processId++;
    //YGG详情表处理取消 27
    if ($worker->id === $processId) {
        $interval = 30;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\Third\YGG($app->getContainer());
            $game->syncOrderDetailDel();
        });
    }

    $processId++;
    //YESBINGO 28
    if ($worker->id === $processId) {
        $interval = 30;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('YESBINGO');
        });
    }

    $processId++;
    //EVORT 29
    if ($worker->id === $processId) {
        $interval = 30;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('EVORT');
        });
    }

    $processId++;
    //WMATCH 拉订单 30
    if ($worker->id === $processId) {
        $interval = 30;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('WMATCH');
        });
    }

    $processId++;
    //EVOPLAY 拉订单 31
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('EVOPLAY');
        });
    }

};

\Workerman\Worker::runAll();