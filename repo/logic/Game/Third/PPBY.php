<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;

/**
 * PP 游戏接口 捕鱼游戏
 * Class PP
 * @package Logic\Game\Third
 */
class PPBY extends PP
{
    protected $game_type = 'PPBY';
    protected $dataTypeKey = 'BY';
    protected $dataTypeValue = 'R2';

}
