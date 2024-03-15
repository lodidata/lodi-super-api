<?php

use Utils\Game\Action;
use Logic\Define\CacheKey;

/**
 * 获取某天已开奖的彩期列表
 */
return new class extends Action
{

    public function run()
    {
        $this->verifyApiToken();
        $type = $this->request->getParam('type');
        $gameClass = new Logic\Game\Third\CG($this->ci);
        $config = $gameClass->initConfigMsg(strtoupper($type));
        return base64_encode(json_encode($config, JSON_UNESCAPED_UNICODE));
    }

};