<?php
use Utils\Shop\Action;
//use Model\Advert;

return new class extends Action {
    const TITLE = "项目启动后配置参数";
    const TYPE = "text/json";
    const SCHEMAs = [
       200 => [
           "code" => "string(optional) #第三方客服URL",
           "registbr_user" => "array(optional) #  用户注册是否要手机验证",
           "withdraw_need_mobile" => "boole(optional) #提现是否需要手机验证"
       ]
   ];

    public function run() {
        $data = (new \Logic\Shop\SystemConfig($this->ci))->getStartGlobal();
        return $data;
    }
};