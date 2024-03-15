<?php

if (count($argv) < 3) {
    echo "参数不合法\n\r";
    return;
}

global $app;
$ci = $app->getContainer();
$type = $argv[2];
$key = $argv[3];
$value = $argv[4]??'';
$hvalue = $argv[5]??'';
echo $type . ' ' . $key . ' ' . $value. ' ' . $hvalue.PHP_EOL;
if($type == 'set'){
    $ci->redis->set($key, $value);
}elseif($type == 'del'){
    $ci->redis->del($key);
}elseif($type == 'get'){
    echo $ci->redis->get($key);
    die;
}elseif($type == 'hGetAll'){
   var_dump($ci->redis->hGetAll($key));
   die;
}elseif($type == 'hSet'){
    $ci->redis->hSet($key, $value, $hvalue);
}elseif($type == 'hGet'){
    var_dump($ci->redis->hGet($key, $value));
    die;
}else{
    echo 'no type ' . $type;
    exit;
}

echo 'SUCCESS';die;