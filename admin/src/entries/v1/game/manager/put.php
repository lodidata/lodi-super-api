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
 * 设置游戏维护
 */
return new class extends BaseController
{

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        //$this->checkID($id);
        $params = $this->request->getParams();
        $m_start_time = isset($params['m_start_time']) ? $params['m_start_time'] : '';
        $m_end_time = isset($params['m_end_time']) ? $params['m_end_time'] : '';
        $alias = $params['type'];
        if(!$alias){
            return $this->lang->set(25);
        }
        if($m_start_time){
            $m_start_time = date('Y-m-d H:i:s', strtotime($m_start_time));
            $params['m_start_time'] = $m_start_time;
        }
        if($m_end_time){
            $m_end_time = date('Y-m-d H:i:s', strtotime($m_end_time));
            $params['m_end_time'] = $m_end_time;
        }
        //开始时间和结束时间验证
        if ($m_start_time > $m_end_time) {
            return $this->lang->set(25);
        }
        //$alias = \DB::table('game_menu')->where('id', '=', $id)->value('alias');
        $result = DB::table('game_menu')
            ->where('alias', '=', $alias)
            ->update(['m_start_time' => $m_start_time, 'm_end_time' => $m_end_time]);


        if ($result !== false) {

            $res = \Logic\Recharge\Recharge::requestPaySit("gameM",'', $params);
            /*============================日志操作代码=====================*/
            $sta = $res !== false ? 1 : 0;
            (new Log($this->ci))->create(null, null, Log::MODULE_GAME, '游戏管理', '设置游戏维护', "修改", $sta, "游戏：{$alias} 维护时间：{$m_start_time} - {$m_end_time}");
            /*============================================================*/
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);

    }
};