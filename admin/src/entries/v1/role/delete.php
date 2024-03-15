<?php
/**
 * 角色删除
 * @author Taylor 2019-01-25
 */

use Logic\Admin\Log;

return new class extends Logic\Admin\BaseController
{
    const TITLE = '角色删除';
    const PARAMs = [
        'id' => 'int(required)角色id',
    ];
    const SCHEMAs = [
        200 => []
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id)
    {
        $this->checkID($id);

        //判断角色是否有使用
        $roleArr = DB::table('admin_role_relation')->select('id')
            ->where('rid', $id)->get()->toArray();

        if($roleArr){
            return $this->lang->set(886, ['角色有使用,不可删除！']);
        }

      $info = DB::table('admin_user_role')->find($id);

        //删除
        $res = DB::table('admin_role')->where('id', $id)->delete();

        if($res !== false){
            /*============================日志操作代码=====================*/
            $sta = $res !== false ? 1 : 0;
            (new Log($this->ci))->create(null, null, Log::MODULE_USER, '管理员角色', '管理员角色', "删除", $sta, "角色名称：{$info->role}");
            /*============================================================*/
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};