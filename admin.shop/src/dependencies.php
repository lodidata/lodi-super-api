<?php
use Slim\Http\Request;
use Slim\Http\Response;
use Utils\Admin\Controller;
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
        $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    }
    
    // if (isset($settings['type']) && $settings['type'] == 'mongodb') {
    //     $settings   = $c->get('settings')['mongodb'];
    //     $appId = $c->get('settings')['app']['tid'];
    //     if ($settings['user'] !== null && $settings['password'] !== null) {
    //         $auth = $settings['user'].':'.$settings['password'].'@';
    //     } else {
    //         $auth = '';
    //     }

    //     $host = isset($settings['port']) ? $settings['host'].':'.$settings['port'] : $settings['host'];
    //     $mongo = new MongoDB\Client("mongodb://{$auth}{$host}");
    //     $set = 'core_'.$appId;
    //     $mongodb = new Monolog\Handler\MongoDBHandler($mongo, $set, "admin_logger"); 
    //     $logger->pushHandler($mongodb);
    // }

    //$logger->pushHandler(new Monolog\Handler\ErrorLogHandler(Monolog\Handler\ErrorLogHandler::OPERATING_SYSTEM, Monolog\Logger::INFO));
    return $logger;
};


//$container['db'] = function ($c) {
//    $capsule = new \Illuminate\Database\Capsule\Manager;
//    $db_config = $c['settings']['shop']['db'];
//    foreach ($db_config as $key => $v) {
//        $capsule->addConnection($v, $key);
//    }
//    $capsule->setEventDispatcher(new Dispatcher(new Container));
//    $capsule->setAsGlobal();
//    $capsule->bootEloquent();
//
//    return $capsule;
//};
//
//$container['redis'] = function ($c) {
//    $settings   = $c->get('settings')['shop']['redis'];
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


$container['validator'] = function () {
    return new Awurth\SlimValidation\Validator(true, require __DIR__ . '/../../config/lang/validator.php');
};

$container['lang'] = function ($c) {
    $langConfig =  
        (require __DIR__ . '/../../config/lang/admin.php')
//        + (require __DIR__ . '/../../config/lang/api.www.php')
//        + (require __DIR__ . '/../../config/lang/jobs.php')
    ;
    return new \Logic\Define\ErrMsg($c, $langConfig);
};

//$container['auth'] = function ($c) {
//    return new \Logic\Auth\Auth($c);
//};
//$container['admin'] = function ($c) {
//    return new \Logic\Admin\Admin($c);
//};

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
        return $c['response']->withStatus(500)
            // ->withHeader('Access-Control-Allow-Origin', '*')
            // ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            // ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Content-Type', 'application/json')
            ->withJson($data);
    };
};