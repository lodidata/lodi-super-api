<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/7/6
 * Time: 10:55
 */

use Lib\Validate\Admin\AdminValidate;
use Logic\Admin\Log;
use Model\Admin\Admin;
use Logic\Admin\AdminToken;

return new class extends Logic\Admin\BaseController {

    const TITLE = '停用/启用管理员';
    const DESCRIPTION = '停用/启用管理员';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [
        'status' => 'integer(required)#状态 0：停用，1：启用',
    ];
    const SCHEMAs = [

    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id = '') {
        $this->checkID($id);

        (new AdminValidate())->paramsCheck('status', $this->request, $this->response);

        $adminModel = (new Admin())::find($id);
        if (!$adminModel) {
            return $this->lang->set(9);
        }
        $name = $adminModel->name;
        $params = $this->request->getParams();
        $adminModel->status = $params['status'];
        $res = $adminModel->save();

        if (!$params['status']) {
            (new AdminToken($this->ci))->remove($id);
        }

        if (!$res) {
            /*============================日志操作代码=====================*/
            $sta = $res !== false ? 1 : 0;
            $status_str = $params['status']==1 ? "启用": '停用';
            (new Log($this->ci))->create(null, null, Log::MODULE_USER, '账号列表', '停用/启用管理员', '停用/启用', $sta, "账号：{$name} 状态：{$status_str}");
            /*============================================================*/
            return $this->lang->set(-2);
        }

        return $this->lang->set(0);
    }
};