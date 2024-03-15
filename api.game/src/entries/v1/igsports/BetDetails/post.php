<?php

use Utils\Game\Action;
use Logic\Game\GameApi;

return new class extends Action {
    const TITLE = "STG游戏获取用户信息";
    const TAGS = 'STG游戏';
    const DESCRIPTION = "";
    const QUERY = [
        'Orders'  => 'json() #主注单',
        'OrderBet' => 'json(required) #投注',
        'OrderBetStake'    => 'json(required) #子注单',
    ];
    public function run()
    {
        $params = $this->request->getParams();
        GameApi::addElkLog(['method' => 'BetDetails', 'params' => $params], 'STG');
        $gameClass = new Logic\Game\Third\STG($this->ci);
        $gameClass->updateOrder($params);

        return $this->response->withStatus(200)
            ->withHeader('Content-Type', 'application/json')
            ->withJson(['ResponseCode' => 0,"Description" => 'Success']);
    }

};