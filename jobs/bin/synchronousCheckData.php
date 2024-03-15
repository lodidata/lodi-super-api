<?php

if (count($argv) < 2) {
    echo "参数不合法\n\r";
    return;
}
global $app;
$game = $argv[2];
echo $game.PHP_EOL;
$class = 'Logic\Game\Third\\'.strtoupper($game);
$obj = new $class($app->getContainer());
print_r($obj->synchronousCheckData());
