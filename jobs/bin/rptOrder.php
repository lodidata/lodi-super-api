<?php
//Linux定时器以防进程没跑了

if (count($argv) < 2) {
    echo "参数不合法\n\r";
    return;
}

$game = $argv[2];
$date = $argv[3]?? '';

echo $game . '-' . $date;
$class = 'Logic\Game\Third\\'.strtoupper($game);
global $app;
$obj = new $class($app->getContainer());
$obj->queryOperatesOrder($date);
