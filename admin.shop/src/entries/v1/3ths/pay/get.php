<?php
use Logic\Admin\BaseController;

//第三方支付列表，获取第三方支付商户与类型
return new class() extends BaseController{
    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run(){
        $channel =  Logic\Shop\Recharge::requestPaySit('getChannel');
        $pay_channel = [];
        if($channel && is_array($channel)){
            foreach ($channel as $val) {
                $t['code'] = $val['id'];
                $t['name'] = $val['name'];
                $pay_channel[] = $t;
            }
        }
        $types = \Logic\Shop\Pay::getPayType('name');
        $pay_scene = [];
        foreach ($types as $key=>$val){
            $t['name'] = $val;
            $t['pay_scene'] = $key;
            $pay_scene[] = $t;
        }
        if($pay_channel)
            $pay_channel = array_unique($pay_channel,SORT_REGULAR);
        return ['pay_channel'=>$pay_channel,'pay_scene'=>$pay_scene];
    }
};
