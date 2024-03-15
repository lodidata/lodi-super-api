<?php
use Utils\Www\Controller;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

$container = $app->getContainer();

$container['ci'] = function ($c) {
    return $c;
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    if (isset($settings['type']) && $settings['type'] == 'file') {
        $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    }
    

    //$logger->pushHandler(new Monolog\Handler\ErrorLogHandler(Monolog\Handler\ErrorLogHandler::OPERATING_SYSTEM, Monolog\Logger::INFO));
    return $logger;
};



//$container['db'] = function ($c) {
//
//    $capsule = new \Illuminate\Database\Capsule\Manager;
//    foreach ($c['settings']['db'] as $key => $v) {
//        $capsule->addConnection($v, $key);
//    }
//    $capsule->setEventDispatcher(new Dispatcher(new Container));
//    $capsule->setAsGlobal();
//    $capsule->bootEloquent();
//    return $capsule;
//};
//
//$container['cache'] = function ($c) {
//    $settings   = $c->get('settings')['cache'];
//    $config     = [
//        'schema' => $settings['schema'],
//        'host' => $settings['host'],
//        'port' => $settings['port'],
//        'database' => $settings['database'],
//    ];
//
//    if (!empty($settings['password'])) {
//        $config['password'] = $settings['password'];
//    }
//
//    $connection = new Predis\Client($config);
//    return new Symfony\Component\Cache\Adapter\RedisAdapter($connection);
//};
//
//$container['mongodb'] = function ($c) {
//     $settings   = $c->get('settings')['mongodb'];
//     $appId = $c->get('settings')['app']['tid'];
//     if ($settings['user'] !== null && $settings['password'] !== null) {
//         $auth = $settings['user'].':'.$settings['password'].'@';
//     } else {
//         $auth = '';
//     }
//
//     $host = isset($settings['port']) ? $settings['host'].':'.$settings['port'] : $settings['host'];
//     $m = new \MongoDB\Client("mongodb://{$auth}{$host}");
//     $db = $m->selectDatabase('core_'.$appId); // 选择一个数据库
//    return $db;
//};
//
//$container['redis'] = function ($c) {
//    $settings   = $c->get('settings')['cache'];
//    $config     = [
//        'schema' => $settings['schema'],
//        'host' => $settings['host'],
//        'port' => $settings['port'],
//        'database' => $settings['database'],
//    ];
//
//    if (!empty($settings['password'])) {
//        $config['password'] = $settings['password'];
//    }
//    return new Predis\Client($config);
//};

$container['Controller'] = function ($c) {
    return new Controller(__DIR__, $c);
};

$container['notFoundHandler'] = function ($c) {
    return function () use ($c) {
        $controller = new Controller(__DIR__, $c);
        return $controller->run();
    };
};

$container['phpErrorHandler'] = $container['errorHandler'] = function ($c) {
    return function ($request, $response, $exception) use ($c) {
        $debug = [
                    'type' => get_class($exception),
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => explode("\n", $exception->getTraceAsString())
                ];
        $c->logger->error('程序异常', $debug);
        $data = [
                    'data' => null,
                    'attributes' => null,
                    'state' => -9999,
                    'message' => '程序运行异常'
                ];
        if (RUNMODE == 'dev') {
            $data['debug'] = $debug;
        }
        return $c['response']
            ->withStatus(500)
            // ->withHeader('Access-Control-Allow-Origin', '*')
            // ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            // ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Content-Type', 'application/json')
            ->withJson($data);
    };
};