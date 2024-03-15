<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, X-Request-Uri, Content-Type, Accept, Origin, Authorization, pl, mm, av, sv, uuid');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
define('COSTSTART', microtime(true));

if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}
// Instantiate the app  加载对应客户配置信息

require __DIR__ . '/../../repo/vendor/autoload.php';

$settings = require __DIR__ . '/../../config/settings.php';
$app = new \Slim\App($settings);

// session_start();
define('APP', __DIR__);


if (RUNMODE == 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors','On');
}

$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register routes
require __DIR__ . '/../src/routes.php';

// Run app
$app->run();
