<?php
global $app;
$game = new \Logic\Game\GameLogic($app->getContainer());
$game->clearGameOrderError();