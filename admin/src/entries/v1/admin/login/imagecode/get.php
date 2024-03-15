<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/6/13
 * Time: 15:08
 */

use Logic\Admin\BaseController;

return new class extends BaseController{

    const TITLE       = '获取图形验证码';
    const DESCRIPTION = '后台登录获取验证码';
    const HINT        = '';
    const QUERY       = [

    ];
    const TYPE        = 'text/json';
    const PARAMs      = [];
    const SCHEMAs     = [

    ];

    public $beforeActionList = [

    ];

    public function run(){

        return (new \Logic\Captcha\Captcha($this->ci))->getImageCode();

    }
};
