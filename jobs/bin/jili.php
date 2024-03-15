<?php
global $app;
$game = new \Logic\Game\Third\JILI($app->getContainer());
$start_time = '2023-03-22 12:00:00';
$end_time = '2023-03-23 14:00:00';
while (1) {
    $tmp_end_time = date('Y-m-d H:i:s', strtotime($start_time) + 3600);
    if ($tmp_end_time > $end_time) {
        exit;
    }
    $game->orderByTime($start_time, $tmp_end_time);
    sleep(10);
    $start_time = $tmp_end_time;
}