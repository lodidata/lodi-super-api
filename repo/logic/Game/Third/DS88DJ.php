<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;
use function GuzzleHttp\Psr7\str;

/**
 * Explain: DS88DJ
 *
 * OK
 */
class DS88DJ extends LDAPI
{

    protected $game_type = 'DS88DJ';
    protected $orderTable = 'game_order_ds88dj';

}
