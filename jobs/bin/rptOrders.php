<?php
/**
 * 跑一整数据
 * 第二位是否跑一天
 * 第三位 日期2022-07-22
 */

//是否跑昨天数据
$yestaday = $argv[2]?? 1;
$date = $argv[3]?? '';
echo $yestaday . '-' . $date;

global $app;
$game = new \Logic\Game\GameApi($app->getContainer());
$game->rptAllOrdersMiddleDay($yestaday, $date);
