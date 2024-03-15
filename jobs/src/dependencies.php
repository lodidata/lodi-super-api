<?php
use Slim\Http\Request;
use Slim\Http\Response;
use Utils\www\Controller;
// DIC configuration
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

$container = $app->getContainer();

// view renderer
// $container['renderer'] = function ($c) {
//     $settings = $c->get('settings')['renderer'];
//     return new Slim\Views\PhpRenderer($settings['template_path']);
// };

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());

    if (isset($settings['type']) && $settings['type'] == 'file') {
//        $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
        $logger->pushHandler(new Monolog\Handler\RotatingFileHandler($settings['path'], 0, $settings['level']));//每天生成一个日志
    }

//    if (RUNMODE == 'dev') {
//        $firephp = new Monolog\Handler\FirePHPHandler();
//        $logger->pushHandler($firephp);
//    }

    return $logger;
};

$container['db'] = function ($c) {
    $capsule = new \Illuminate\Database\Capsule\Manager;
    foreach ($c['settings']['db'] as $key => $v) {
        $capsule->addConnection($v, $key);
    }
    $capsule->setEventDispatcher(new Dispatcher(new Container));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    return $capsule;
};

$container['cache'] = function ($c) {
    $settings   = $c->get('settings')['cache'];
    $config     = [
        'scheme' => $settings['scheme'],
        'host' => $settings['host'],
        'port' => $settings['port'],
        'database' => $settings['database'],
    ];

    if (!empty($settings['password'])) {
        $config['password'] = $settings['password'];
    }
    if($config['scheme'] == 'tls'){
        $config['ssl'] = $settings['ssl'];
    }
    return new Predis\Client($config);
};

$container['redis'] = $container['cache'];

$container['redisCommon'] = $container['cache'];

$container['mongodb'] = function ($c) {
    // $settings   = $c->get('settings')['mongodb'];
    // $appId = $c->get('settings')['app']['tid'];
    // if ($settings['user'] !== null && $settings['password'] !== null) {
    //     $auth = $settings['user'].':'.$settings['password'].'@';
    // } else {
    //     $auth = '';
    // }
    
    // $host = isset($settings['port']) ? $settings['host'].':'.$settings['port'] : $settings['host'];
    // $m = new \MongoDB\Client("mongodb://{$auth}{$host}");
    // $db = $m->selectDatabase('core_'.$appId); // 选择一个数据库
//    return $db;
};

$container['notFoundHandler'] = function ($c) {
    return function ($req, $res) use ($c) {
        return $res->write('');
    };
};