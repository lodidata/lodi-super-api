<?php
use Utils\Shop\Action;
return new class extends Action {
    const TITLE = 'GET 获取线上类型';
    const TYPE = 'application/json';
    const SCHEMAs = [
        'data' => [
            'money' => [
                'min_money' => 'int #该类型金额支持最小值',  //分
                'max_money' => 'int #该类型金额支持最大值'  //分
            ],
            'type'  => [
                'id'        => 'int #ID',
                'd_title'   => 'string #类型描述',
                'name'      => 'string #名称',
                'imgs'      => 'string #图片地址',
                'min_money' => 'int #该类型金额支持最小值',  //分
                'max_money' => 'int #该类型金额支持最大值'  //分
            ],
        ],
    ];

    public function run() {
        $verify = $this->auth->verfiyToken();

        if (!$verify->allowNext()) {
            return $verify;
        }

        $pay = new \Logic\Shop\Pay($this->ci);
//
//        $user = (new \Logic\User\User($this->ci))->getInfo($this->auth->getUserId());
//        $userLevel = $user['ranting'];  //用户层级

        $onlineRecharges = $pay->getOnlineChannel();

        return $onlineRecharges;
    }
};
