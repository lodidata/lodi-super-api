<?php
use Utils\Shop\Action;
use Logic\Define\ErrMsg;

// 用户注册
return new class extends Action {
    const TITLE = "POST 会员注册";
    const DESCRIPTION = "提交会员注册";
    const TYPE = "text/json";

    public function run() {
        $mobile = $this->request->getParam('telphone');//手机号码
        $telphoneCode = $this->request->getParam('telphone_code', '+86');//区号
        $telCode = $this->request->getParam('tel_code');//短信验证码，也是图形验证码
        $password = $this->request->getParam('password');//密码

        $user = new \Logic\Shop\User($this->ci);
        $res = $user->registerByMobile($mobile, $password, $telCode, $telphoneCode);
        if ($res instanceof ErrMsg && $res->allowNext()){
            // 自动登录
            $res = $this->auth->login($mobile, $password, 2);
            return $res;
        }
        return $res;
    }
};