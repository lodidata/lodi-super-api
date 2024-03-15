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
    const TITLE = '管理员修改密码';
    const PARAMs = [
        'name' => 'string(required)#用户名',
        'password' => 'string(optional)#密码',
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
        $adminModel->name = $params['name'];
        if(isset($params['password']) && !empty($params['password'])){
            $adminModel->password = password_hash($params['password'],PASSWORD_DEFAULT);
        }
        $res = $adminModel->save();
        if(!$res){
            /*============================日志操作代码=====================*/
            $sta = $res !== false ? 1 : 0;
            (new Log($this->ci))->create(null, null, Log::MODULE_USER, '账号列表', '修改管理员密码', '修改', $sta, "账号：{$params['name']}");
            /*============================================================*/
            return $this->lang->set(-2);
        }

        return $this->lang->set(0);
    }
};