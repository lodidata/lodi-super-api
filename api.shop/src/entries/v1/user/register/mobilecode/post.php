<?php
use Utils\Shop\Action;
use Respect\Validation\Validator as V;

//发送短信
return new class extends Action {
    const TITLE = "GET 发送手机验证码";
    const TYPE = "text/json";
    const PARAMs = [
       "telphone" => "string(required) #手机号码",
       "token" => "string(required) #验证码",
       "code" => "string(required) #验证码"
    ];
    const SCHEMAs = [
       200 => [
           "telphone" => "string(required) #手机号码"
       ]
    ];

    public function run() {
        $tel_code = $this->request->getParam('telphone_code', '+86');
        if ( $tel_code != '+86') {
            $validator = $this->validator->validate($this->request, [
                'telphone' => V::mobile()->setName('手机号码'),
                'token' => V::alnum()->noWhitespace()->length(32)->setName('图片验证码串'),
                'code' => V::intVal()->noWhitespace()->length(4)->setName('图片验证码'),
                'telphone_code' => V::telephoneCode()->setName('区号'),
            ]);
        } else {
            $validator = $this->validator->validate($this->request, [
                'telphone' => V::chinaMobile()->setName('手机号码'),
                'token' => V::alnum()->noWhitespace()->length(32)->setName('图片验证码串'),
                'code' => V::intVal()->noWhitespace()->length(4)->setName('图片验证码'),
                'telphone_code' => V::telephoneCode()->setName('区号'),
            ]);
        }

        if (!$validator->isValid()) {
            return $validator;
        }

        $captcha = new \Logic\Captcha\Captcha($this->ci);
        //图形验证码
        if ($captcha->validateImageCode($this->request->getParam('token'), $this->request->getParam('code'))) {
            $mobile = $this->request->getParam('telphone');
//            if (DB::table('user')->where('name', $mobile)->count() > 0) {
//                return $this->lang->set(104);
//            }

            return $captcha->sendTextCode($tel_code.$mobile);
        }
        return $this->lang->set(105);
    }
};