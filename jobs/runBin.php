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
$logger = $app->getContainer()->logger;
$suffix = '.php';
// 打印sql
//if (isset($settings['settings']['website']['DBLog']) && $settings['settings']['website']['DBLog']) {
    $app->getContainer()->db->getConnection()->enableQueryLog();
//}
//print_r($argv);exit;
if (!isset($argv[1])) {
    echo '请输入执行的bin名称', PHP_EOL;
    return;
}

$file = __DIR__ .'/bin/'. $argv[1] . $suffix;
if (!is_file($file)) {
    echo 'bin脚本不存在:'.$file, PHP_EOL;
    return;
}
require $file;

// 写入sql
//if (isset($settings['settings']['website']['DBLog']) && $settings['settings']['website']['DBLog']) {

    foreach ($app->getContainer()->db->getConnection()->getQueryLog() ?? [] as $val) {
        $logger->error('DBLog', $val);
        $sql_format = str_replace('?', '%s', $val['query']);
        $sql = call_user_func_array('sprintf', array_merge((array)$sql_format,$val['bindings']));
        //echo $sql.';'.PHP_EOL;
    }
//}

