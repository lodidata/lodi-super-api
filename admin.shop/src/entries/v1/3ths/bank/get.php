<?php
use Logic\Admin\BaseController;

return new class() extends BaseController{
    const TITLE       = '获取所有可用银行列表';
    const TYPE        = 'text/json';
    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run(){
        $bank = \DB::table('bank')
            ->where('type','=',1)
            ->whereRaw('FIND_IN_SET("enabled",status)')
            ->orderBy('sort','DESC')
            ->get(['id','name','code','logo as img','status'])
            ->toArray();
        return $bank;
    }
};
