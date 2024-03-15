<?php
use Utils\Shop\Action;

// 忘记密码
return new class extends Action {
    public function run() {
        $mobile = $this->request->getParam('telphone');//手机号码
        $telphoneCode = $this->request->getParam('telphone_code', '+86');//区号
        $telCode = $this->request->getParam('tel_code');//短信验证码
        $password = $this->request->getParam('password');//新密码
        $cfm_password = $this->request->getParam('cfm_password');//确认密码
        if($password !== $cfm_password){
            return $this->lang->set(886, ['确认密码和密码不一致']);
        }

        $user = new \Logic\Shop\User($this->ci);
        $res = $user->forget($mobile, $password, $telCode, $telphoneCode);
        return $res;
    }
};