<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/7/2
 * Time: 10:56
 */

use Utils\Game\Action;
use Logic\Game\GameApi;
use GuzzleHttp\Client;
use Logic\Define\CacheKey;
use GuzzleHttp\Exception\ClientException ;
return new class extends Action {


    public function run (){
        $game = new \Logic\Game\GameLogic($this->ci);
        $game->clearGameOrderError();

    }

};