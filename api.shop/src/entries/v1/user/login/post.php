<?php
use Utils\Shop\Action;
use Respect\Validation\Validator as V;

return new class extends Action {
    const TITLE = "POST 用户登录";
    const TYPE = "text/json";
    const PARAMs = [
       "name" => "string(require, 30)",
       "password" => "string(password, 12)",
       "vcode" => "int(require, 4)",
       "token" => "string()#验证码token"
   ];


    public function run() {
        $validator = $this->validator->validate($this->request, [
            'name' => V::username(),
            'password' => V::password(),
        ]);

        if ($validator->isValid()) {
            $lang = $this->auth->login($this->request->getParam('name'), $this->request->getParam('password'));
            return $lang;
        } else {
            return $validator;
        }
    }
};