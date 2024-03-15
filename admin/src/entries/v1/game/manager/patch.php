<?php
/**
 * Created by PhpStorm.
 * User: Nico
 * Date: 2018/7/27
 * Time: 16:23
 */

use Logic\Admin\BaseController;
use Logic\Admin\Log;

/**
 * 游戏维护开关
 */
return new class extends BaseController
{

//    protected $beforeActionList = [
//        'verifyToken', 'authorize',
//    ];

    public function run($id = null)
    {
        $this->checkID($id);
        $switch = $this->request->getParam('switch', '');


        if (!$switch) {
            return $this->lang->set(886, ['开关状态不能为空！']);
        }

        $result = DB::table('game_menu')
            ->where('id', '=', $id)
            ->update(['switch' => $switch]);

        if ($result !== false) {
            $game_name = DB::table('game_menu')
                ->where('id', '=', $id)->value('name');
            $res = \Logic\Recharge\Recharge::requestPaySit("gameSwitch",'', ['switch'=>$switch,'id' => $id]);
            /*============================日志操作代码=====================*/
            $sta = $res !== false ? 1 : 0;
            (new Log($this->ci))->create(null, null, Log::MODULE_GAME, '游戏管理', '游戏维护开关', "修改", $sta, "游戏名称：{$game_name} 状态：{$switch}");
            /*============================================================*/
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);

    }
};