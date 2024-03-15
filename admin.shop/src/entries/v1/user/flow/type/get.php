<?php
use Logic\Admin\BaseController;
return new class() extends BaseController{
    const TITLE       = '交易流水(记录)/资金流水--类别与类型';
    const QUERY       = [];
    const TYPE        = 'text/json';
    const PARAMs      = [];
    const STATEs      = [];
    const SCHEMAs     = [
        200 => ['rowset']
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run(){
        return array_values(\Logic\Shop\FundsDealLog::getDealLogTypes());
    }
};
