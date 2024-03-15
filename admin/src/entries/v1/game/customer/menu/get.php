<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */

use Logic\Admin\BaseController;

/**
 * 获取菜单
 * type_level 菜单级别（父级菜单：p;子级菜单：c）
 *
 * pid 根据父级ID查询子级菜单
 */
return new class extends BaseController
{

    protected $beforeActionList = [
//        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $query = \DB::table('game_menu')
            ->where('pid','<>',0)
            ->whereNotIn('id',[26,27]);
        $data = $query->groupBy('alias')->get(['id','type','name','alias'])->toArray();
        return $data;
    }

};