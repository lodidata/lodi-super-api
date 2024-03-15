<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * PP 游戏接口 真人游戏
 * Class PP
 * @package Logic\Game\Third
 */
class PPLC extends PP
{
    protected $game_type = 'PPLC';
    protected $dataTypeKey = 'ECasino';
    protected $dataTypeValue = 'LC';

}
