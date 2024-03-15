<?php
global $app;
$game = new \Logic\Game\GameApi($app->getContainer());
$game->gameOrderAWSAlarmMsg();