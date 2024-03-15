<?php

use Utils\Game\Action;

/**
 * 第三方游戏回调
 */
return new class extends Action
{
    public function run() {
        $params = $this->request->getParams();
        //游戏厂商名称
        $game = $this->request->getQueryParam('game','');
        //回调日志
        DB::table('game_callback_log')->insert([
            'game'=>$game,
            'params'=>json_encode($params),
       ]);

        \Logic\Game\GameApi::addElkLog($params,'game-callback');

        $action = $this->request->getQueryParam('action','verifyToken');
        $game = strtoupper($game);
        $class = 'Logic\Game\Third\\'.$game;
        if (!class_exists($class)) {
            return $this->response->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withJson([
                    'state' => 99999,
                    'code' => 99999,
                    'message' => $game . ' not funds',
                    'ts' => date('Y-m-d\TH:i:s', time())
                ]);
        }
        if(!method_exists($class, $action)){
            return $this->response->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withJson([
                    'state' => 99999,
                    'code' => 99999,
                    'message' => $action . ' not funds in ' . $game,
                    'ts' => date('Y-m-d\TH:i:s', time())
                ]);
        }
        $class = new $class($this->ci);
        return $class->$action($params);
    }



};