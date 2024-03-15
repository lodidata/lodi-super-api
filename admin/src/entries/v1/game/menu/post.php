<?php
/**
 * Created by PhpStorm.
 * User: Nico
 * Date: 2018/7/27
 * Time: 16:23
 */

use Logic\Admin\BaseController;
use Logic\Define\Cache3thGameKey;
/**
 * 第三方游戏新增菜单
 */
return new class extends BaseController
{

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {

        $params = $this->request->getParams();
        $result = false;
        if (isset($params['type_level'])) {
            if ($params['type_level'] == 'p') {
                unset($params['type_level']);
                $result = DB::table('game_menu')
                    ->insert([$params]);
            } else {
                unset($params['type_level']);
                $result = DB::table('game_3th')
                    ->insert([$params]);
            }
        }

        if ($result) {
            $this->redis->del(\Logic\Define\CacheKey::$perfix['ApiThirdGameJumpMsg']);
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);

    }
};