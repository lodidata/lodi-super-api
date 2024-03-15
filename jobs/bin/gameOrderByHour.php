<?php
//Linux定时器以防进程没跑了

if (count($argv) < 4) {
    echo "参数不合法\n\r";
    return;
}


$game = $argv[2];
$stime = $argv[3];
$end = $argv[4];
echo $game.'('.$stime.'-'.$end.')';
$class = 'Logic\Game\Third\\'.strtoupper($game);
global $app;
$obj = new $class($app->getContainer());
$obj->orderByHour($stime,$end);
