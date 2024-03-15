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

        $data = DB::table('game_menu')
            ->selectRaw('id,type,name')
            ->where('pid','<>',0)
            ->whereNotIn('id',[26,27])
            ->get()->toArray();

        return $data;
    } 

};