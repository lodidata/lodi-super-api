<?php
use Utils\Shop\Action;
//用户退出
return new class extends Action {
    const TITLE = "GET 用户注销";
    const TYPE = "text/json";
    const SCHEMAs = [
       200 => [
           "uid" => "int(require, 11) #当前用户uid"
       ]
   ];

    public function run() {
        return $this->auth->logout(null, $this->auth->getCurrentPlatformGroupId());
    }
};