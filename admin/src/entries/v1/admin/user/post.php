<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/7/6
 * Time: 10:55
 */
use Logic\Admin\BaseController;
use Lib\Validate\Admin\AdminValidate;
use Logic\Admin\Log;
use Model\Admin\Admin;

return new class extends BaseController {
    const TITLE = '新建管理员';
    const PARAMs = [
        'name'             => 'string(required)#用户名',
        'password'         => 'string(required)#密码',
        'password_confirm' => 'string(required)#确认密码',
        'role'              => 'int(optional)所属角色id',
    ];
    const SCHEMAs = [

    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run() {
        global $playLoad;

        (new AdminValidate())->paramsCheck('create', $this->request, $this->response);
        $params = $this->request->getParams();

        $adminModel = new Admin();
        $adminModel->name = $params['name'];
        $adminModel->password = password_hash($params['password'], PASSWORD_DEFAULT);
        $adminModel->truename = $params['truename'];
        $adminModel->creater_id = $playLoad['uid'];
        $adminModel->creater = $playLoad['nick'];

        $res = $adminModel->save();
        $sta=0;
        if (!$res) {
            return $this->lang->set(-2);
        }
        //插入权限-角色表
        if(!empty($params['role'])){
            DB::table('admin_role_relation')->insert(['uid' => $adminModel->id, 'rid' => $params['role']]);
            DB::table('admin_role')->where('id', $params['role'])->increment('num');
        }

        /*============================日志操作代码=====================*/
        $sta = $sta !== false ? 1 : 0;
        (new Log($this->ci))->create(null, null, Log::MODULE_USER, '账号列表', '新建管理员', '添加', $sta, "账号：{$params['name']}");
        /*============================================================*/
        return $this->lang->set(0);
    }
};