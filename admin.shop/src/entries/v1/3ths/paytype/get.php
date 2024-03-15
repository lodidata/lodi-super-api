<?php
use Logic\Admin\BaseController;

return new class() extends BaseController{
    const TITLE       = '支付类型';
    const QUERY       = [
        'type' => 'int(optional)   #1线下2线上',
    ];
    const TYPE        = 'text/json';
    const SCHEMAs     = [
        200 => [
            'id '         => 'int #支付类型ID',
            'name'              => 'string #名称',
        ]
    ];
    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];
    //   无配置表，无相关表，之前取的不对，暂时写死，线下数据与bank_account表中type取值范围对应,线上
    public function run(){
        $types = array(
            ['id'=>'1','name'=>'银行转账'],
            ['id'=>'2','name'=>'支付宝'],
            ['id'=>'3','name'=>'微信'],
            ['id'=>'4','name'=>'QQ钱包'],
            ['id'=>'5','name'=>'京东支付'],
        );
        return $types;
    }
};
