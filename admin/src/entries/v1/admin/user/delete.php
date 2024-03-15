<?php

use Logic\Admin\Log;
use Model\Admin\Admin;
use Logic\Admin\AdminToken;

return new class extends Logic\Admin\BaseController {

    const TITLE = '删除管理员';
    const DESCRIPTION = '删除管理员';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [

    ];
    const SCHEMAs = [
        "200" => '操作成功'
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id = '') {
        $this->checkID($id);

        $admin = DB::table('admin')->find($id);
        if (!$admin) {
            return $this->lang->set(9);
        }

        (new AdminToken($this->ci))->remove($id);

        //角色删除
        DB::table('admin')->where('id', $id)->delete();
        DB::table('admin_role_relation')->where('uid', $id)->delete();
        /*============================日志操作代码=====================*/
        (new Log($this->ci))->create(null, null, Log::MODULE_USER, '账号列表', '删除管理员', '删除', 1, "账号：{$admin->name}");
        /*============================================================*/
        return $this->lang->set(0);
    }
};