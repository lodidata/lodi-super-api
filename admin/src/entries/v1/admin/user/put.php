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

return new class extends BaseController{
    const TITLE = '修改管理员';
    const PARAMs = [
        'truename' => 'string(optional)#用户名',
        'role'  => 'int(required)所属角色id',
    ];
    const SCHEMAs = [
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id=''){
        $this->checkID($id);
        (new AdminValidate())->paramsCheck('update',$this->request,$this->response);

        $adminModel = (new Admin())::find($id);
        if(!$adminModel)
            return $this->lang->set(9);
        $params = $this->request->getParams();
        if(isset($params['truename']) && !empty($params['truename'])){
            $adminModel->truename = $params['truename'];//真实姓名
        }

        if(isset($params['password']) && !empty($params['password'])){
            $adminModel->password = password_hash($params['password'],PASSWORD_DEFAULT);
        }
        $res = $adminModel->save();
        if(!$res)
            return $this->lang->set(-2);

        if(!empty($params['role'])) {
            $role = DB::table('admin_role_relation')->where(['uid' => $id])->get()->toArray();
            if(!empty($role)){
                DB::table('admin_role_relation')->where(['uid' => $id])->update(['rid' => $params['role']]);
            }else{
                DB::table('admin_role_relation')->insert(['uid' => $id, 'rid' => $params['role']]);
            }
        }
        /*============================日志操作代码=====================*/
        $sta = $res !== false ? 1 : 0;
        (new Log($this->ci))->create(null, null, Log::MODULE_USER, '账号列表', '修改管理员', '修改', $sta, "账号：{$params['name']}");
        /*============================================================*/
        return $this->lang->set(0);
    }
};