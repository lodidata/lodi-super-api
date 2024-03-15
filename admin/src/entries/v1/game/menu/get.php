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
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $params = $this->request->getParams();
        if (isset($params['type_level']) && $params['type_level'] == 'p') {
            $data = DB::table('game_menu')
                ->get()
                ->toArray();
        } else {
            if (isset($params['pid']) && $params['pid']) {
                $data = DB::table('game_3th')
                    ->where('game_id','=',$params['pid'])
                    ->get()
                    ->toArray();
            }else{
                $data = DB::table('game_3th')
                    ->get()
                    ->toArray();
            }
        }
        return $data;
    }

};