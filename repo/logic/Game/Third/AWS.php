<?php

namespace Logic\Game\Third;

/**
 * AWC游戏聚合平台
 * 游戏平台-AWS
 * Class AWS
 */
class AWS extends AWC {
    protected $orderTable = 'game_order_aws';
    protected $platfrom = 'AWS';
    protected $orderType = 'AWS';
    protected $gameType = 'SLOT/EGAME/TABLE';
}