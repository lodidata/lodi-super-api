<?php

namespace Logic\Game\Third;

/**
 * AWC游戏聚合平台
 * 游戏平台-RCB
 * Class RCB
 * @package Logic\Game\Third
 */
class RCB extends AWC {
    protected $orderTable = 'game_order_rcb';
    protected $platfrom = 'HORSEBOOK';
    protected $orderType = 'RCB';
    protected $gameType = 'LIVE';
}