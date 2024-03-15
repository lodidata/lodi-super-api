<?php
use Utils\Shop\Action;
/**
 * 获取图形验证码
 */
return new class extends Action {
    public function run() {
        return (new \Logic\Captcha\Captcha($this->ci))->getImageCode();
    }
};