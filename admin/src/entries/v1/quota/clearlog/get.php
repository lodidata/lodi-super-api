<?php

use Logic\Admin\BaseController;

return new class() extends BaseController
{
    //前置方法
    protected $beforeActionList = [
        'verifyToken',
        'authorize',
    ];

    public function run()
    {
        $custId = $this->request->getParam('cust_id');
        $this->checkID($custId);


        $res = \DB::table('quota_log')
            ->where('cust_id', '=', $custId)
            ->orderBy('updated','desc')
            ->get()
            ->toArray();

        return $res;
    }

};