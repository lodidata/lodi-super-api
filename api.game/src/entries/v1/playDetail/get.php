<?php

use Utils\Game\Action;
use Logic\Define\CacheKey;

/**
 * 获取某天已开奖的对局详情
 * 以ID 来区分是否拉过的订单
 */
return new class extends Action
{

    public function run()
    {
        $this->verifyApiToken();
        $type = $this->request->getParam('type');
        $tid = $this->request->getParam('tid');
        $max_id = $this->request->getParam('max_id');
        if(!$type || !$max_id ||!$tid)
            $this->lang->set(0);

        $game = new \Logic\Game\GameApi($this->ci);
        $obj = $game->getGameObj(strtoupper($type));

        if($obj === false)
            return $this->lang->set(-1);
        $res = $obj->getPlayDetail(intval($tid),intval($max_id));
        return $this->lang->set(0,[],$res['data'], ['total' => $res['total'], 'lastId' => $res['lastId']]);
    }

};