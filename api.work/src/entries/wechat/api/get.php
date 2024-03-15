<?php
use Utils\Work\Action;
use Logic\Work\WeChat;

/**
 * 短链生成
 */
return new class extends Action{

    public function run(){
        $params = $this->request->getParams();
        if(empty($params['url'])){
            return $this->lang->set(886, ['url不能为空']);
        }

        $logic = new WeChat($this->ci);
        $data = $logic->getShortUrl($params['url']);
        return $data;
    }

};