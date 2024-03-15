<?php

namespace Logic\Game\Third;

use Logic\Define\CacheKey;
use Logic\Game\GameApi;
use Logic\Game\GameLogic;
use Utils\Curl;
use function GuzzleHttp\Psr7\str;

/**
 * Explain: FACHAI
 *
 * OK
 */
class FACHAI extends LDAPI
{

    protected $game_type = 'FACHAI';
    protected $orderTable = 'game_order_fachai';

}
