<?php
use Utils\Shop\Action;
return new class extends Action {
    const TITLE = "GET 获取用户数据";
    const TYPE = "text/json";

    public function run(){
        $verify = $this->auth->verfiyToken();
        if (!$verify->allowNext()) {
            return $verify;
        }
        $user = (new \Logic\Shop\User($this->ci))->getInfo($this->auth->getUserId());
        return $this->lang->set(0, [], ['name'=>$user['name'], 'balance'=>$user['balance']]);
    }
};
