<?php
//Linux定时器以防进程没跑了

if (count($argv) < 3) {
    echo "参数不合法\n\r";
    return;
}

$game = $argv[2];
$day = $argv[3];
echo $game.'('.$day.')';
$class = 'Logic\Game\Third\\'.strtoupper($game);
global $app;
$obj = new $class($app->getContainer());
print_r($obj->updateRptOrdersMiddleDay($day));
