<?php
global $app;
$game = new \Logic\Game\Third\YGG($app->getContainer());
$game->syncOrderDetail();