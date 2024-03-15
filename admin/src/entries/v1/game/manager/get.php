<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */

use Logic\Admin\BaseController;

/**
 *  游戏维护管理
 *
 * 获取游戏维护信息
 */
return new class extends BaseController
{

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $data = DB::table('game_menu')
            ->select(['id', 'type', 'name', 'switch', 'm_start_time', 'm_end_time'])
            ->whereNotIn('pid', [0,23])
            ->where('switch','enabled')
            ->groupBy('alias')
            ->get()
            ->toArray();
        return $data;
    }

};