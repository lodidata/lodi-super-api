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

$app->run();
$app->getContainer()->db->getConnection('default');
// 将db实例存储在全局变量中(也可以存储在某类的静态成员中)

// $data = new Data($db);        Args:

$fetch = new \Logic\Spider\Fetch($app->getContainer());
$argv[1] = isset($argv[1]) ? $argv[1] : '';

switch ($argv[1]) {
    //手动创建所有彩期
    case 'createAll':
        $fetch = new \Logic\Spider\Fetch($app->getContainer());
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            if ($v['id'] == 44 || $v['id'] == 52) {
                $fetch->specCreateLottery($v, date('Y-m-d'));//加拿大快乐8和六合彩生成彩期
                // $fetch->cakenoCreateLottery($v, date('Y-m-d'));
            } else {
                $fetch->createLottery($v, date('Y-m-d'));
                $fetch->createLottery($v, date('Y-m-d', strtotime('+1 days')));
            }
        }
        break;

    // 手动创建彩期
    // php spiderCmd.php createLottery 44 2020-09-13
    // php spiderCmd.php createLottery 42 2020-09-13
    case 'createLottery':
        if (!isset($argv[3])) {
            throw new \Exception("缺少参数");
        }
        $lotteryIds = explode(',', str_replace(' ', '', $argv[2]));
        $lotteryIds = array_map("intval", $lotteryIds);
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            if (in_array($v['id'], $lotteryIds)) {
                if ($v['id'] == 44 || $v['id'] == 52) {
                    $fetch->specCreateLottery($v, $argv[3]);
                } else {
                    $fetch->createLottery($v, $argv[3], $manual = true);
                }
            }
        }
        break;

    // 手动创建彩期
    case 'createLottery2':
        if (!isset($argv[3])) {
            throw new \Exception("缺少参数");
        }
        $lotteryIds = explode(',', str_replace(' ', '', $argv[2]));
        $lotteryIds = array_map("intval", $lotteryIds);
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            if (in_array($v['id'], $lotteryIds)) {
                if ($v['id'] == 44 || $v['id'] == 52) {
                    $fetch->specCreateLottery2($v, $argv[3]);
                } else {
                    $fetch->createLottery($v, $argv[3], $manual = true);
                }
            }
        }
        break;

    // 手动创建全部彩期
    case 'createAllLottery':
        if (!isset($argv[2])) {
            throw new \Exception("缺少参数");
        }
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            if ($v['id'] == 44 || $v['id'] == 52) {
                $fetch->specCreateLottery($v, $argv[2]);
            } else {
                $fetch->createLottery($v, $argv[2], $manual = true);
            }
        }
        break;

    // 手动创建全部彩期
    case 'createAllLottery2':
        if (!isset($argv[2])) {
            throw new \Exception("缺少参数");
        }
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            if ($v['id'] == 44 || $v['id'] == 52) {
                $fetch->specCreateLottery2($v, $argv[2]);
            } else {
                $fetch->createLottery($v, $argv[2], $manual = true);
            }
        }
        break;
        
    // 开彩网
    case 'openprize':
        if (!isset($argv[3])) {
            throw new \Exception("缺少参数");
        }
        $lotteryIds = explode(',', str_replace(' ', '', $argv[2]));
        $lotteryIds = array_map("intval", $lotteryIds);
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            if (in_array($v['id'], $lotteryIds)) {
                $fetch->openprize($v, $argv[3]);
            }
        }
        break;

    // 彩票控
    case 'openprize2':
        if (!isset($argv[3])) {
            throw new \Exception("缺少参数");
        }
        $lotteryIds = explode(',', str_replace(' ', '', $argv[2]));
        $lotteryIds = array_map("intval", $lotteryIds);
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            if (in_array($v['id'], $lotteryIds)) {
                $fetch->openprize2($v, $argv[3]);
            }
        }
        break;

    // 捉取彩果
    case 'allprize':
        if (!isset($argv[2])) {
            throw new \Exception("缺少参数");
        }
        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            $fetch->openprize($v, $argv[2]);
        }
        break;

    // 捉取彩果
    case 'allprize2':
        if (!isset($argv[2])) {
            throw new \Exception("缺少参数");
        }

        foreach (\Logic\Spider\Fetch::getTaskConfig() as $v) {
            $fetch->openprize2($v, $argv[2]);
        }
        break;
        
    default:
        echo '【开奖服务CMD工具】 Date:' . date('Y-m-d H:i:s'), PHP_EOL;
        echo PHP_EOL;
        echo '【手动创建彩期】', PHP_EOL;
        echo 'php spiderCmd.php createLottery [lottery_id] [date]', PHP_EOL;
        echo 'example:', PHP_EOL;
        echo 'php spiderCmd.php createLottery 43 2017-10-25', PHP_EOL;
        echo 'php spiderCmd.php createLottery 42,44 2017-10-25', PHP_EOL;
        echo 'php spiderCmd.php createAllLottery 2017-10-25', PHP_EOL;
        echo PHP_EOL;


        echo '【手动执行开奖, 默认=开彩网, 2=彩票控】', PHP_EOL;
        echo 'php spiderCmd.php openprize [lottery_id] [date]', PHP_EOL;
        echo 'example:', PHP_EOL;
        echo 'php spiderCmd.php openprize 43,44 2017-10-25', PHP_EOL;
        echo 'php spiderCmd.php openprize2 43,44 2017-10-25', PHP_EOL;
        echo 'php spiderCmd.php allprize 2017-10-25', PHP_EOL;
        echo 'php spiderCmd.php allprize2 2017-10-25', PHP_EOL;
        echo PHP_EOL;

        echo '配置请读取repo/logic/Spider/TaskConfig.php lottery_id只能执行第一层', PHP_EOL;
        echo PHP_EOL;
        break;
}

echo 'finish', PHP_EOL;