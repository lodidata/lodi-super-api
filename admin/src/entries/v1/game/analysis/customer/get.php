<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */

use Logic\Admin\BaseController;

/**
 * 客户信息查询
 */
return new class extends BaseController
{
//    protected $beforeActionList = [
//        'verifyToken', 'authorize',
//    ];

    public function run()
    {

        $data = \DB::table('customer')
            ->selectRaw('id,name')
            ->where('type','game')
            ->get()->toArray();

        return $data;
    } 

};