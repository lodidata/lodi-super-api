<?php
require __DIR__ . '/../../repo/vendor/autoload.php';
$settings = require __DIR__ . '/../../config/settings.php';

\Workerman\Worker::$logFile = LOG_PATH . '/php/gameServer.log';
$worker = new \Workerman\Worker();
$worker->count = 32;
$worker->name = 'ncgSuperGameServer';

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
    //CQ9 拉订单 6
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
    //JILI 拉订单 10
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('JILI');
        });
    }

    $processId++;
    //SA 拉订单 11
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('SA');
        });
    }

    $processId++;
    //ncg  JOKER 拉订单 12
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('JOKER');
        });
    }

    $processId++;
    //ncg  PP电子 拉订单 13
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PP');
        });
    }

    $processId++;
    //ncg  PP捕鱼 拉订单 14
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PPLC');
        });
    }

    $processId++;
    //ncg  EVO 拉订单 15
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('EVO');
        });
    }

    $processId++;
    //ncg  PG 拉订单 16
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PG');
        });
    }

    $processId++;
    //ncg  RCB 拉订单 17
    if ($worker->id === $processId) {
        $interval = 300;//5分钟一次
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('RCB');
        });
    }

    $processId++;
    //ncg  SBO 拉订单 18
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('SBO');
        });
    }

    $processId++;
    //ncg  SGMK 拉订单 19
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('SGMK');
        });
    }
    $processId++;
    //ncg  SV388 拉订单 20
    if ($worker->id === $processId) {
        $interval = 300;//5分钟一次
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('SV388');
        });
    }
    $processId++;
    //ncg  TF 拉订单 21
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('TF');
        });
    }

    $processId++;
    //ncg  PNG 拉订单 22
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PNG');
        });
    }

    $processId++;
    //ncg  SBOV 拉订单 23
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('SBOV');
        });
    }
    $processId++;
    //ncg DG 拉订单 24
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('DG');
        });
    }

    $processId++;
    //ncg BG 拉订单 25
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('BG');
        });
    }

    $processId++;
    //ncg  PB 拉订单 26
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PB');
        });
    }

    $processId++;
    //ncg BGBY 拉订单 27
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('BGBY');
        });
    }

    /*$processId++;
    //PP捕鱼 拉订单 27
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('PPBY');
        });
    }*/

    $processId++;
    // FC 拉订单 28
    if ($worker->id === $processId) {
        $interval = 30;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('FC');
        });
    }

    $processId++;
    // TCG 拉订单 29
    if ($worker->id === $processId) {
        $interval = 120;   //5分钟一次
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('TCG');
        });
    }

    $processId++;
    //RSG 30
    if ($worker->id === $processId) {
        $interval = 30;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('RSG');
        });
    }
        
    $processId++;
    //RSGBY 31
    if ($worker->id === $processId) {
        $interval = 30;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('RSGBY');
        });
    }

    $processId++;
    //IG 拉订单 32
    if ($worker->id === $processId) {
        $interval = 120;
        \Workerman\Lib\Timer::add($interval, function () use ($app) {
            $game = new \Logic\Game\GameApi($app->getContainer());
            $game->synchronousData('IG');
        });
    }

};

\Workerman\Worker::runAll();