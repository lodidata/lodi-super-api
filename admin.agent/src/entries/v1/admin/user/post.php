<?php
/**
 * Created by PhpStorm.
 * User: ben
 * Date: 2018/7/6
 * Time: 10:55
 */

use Logic\Admin\BaseController;
use Lib\Validate\Admin\AdminValidate;
use Model\Admin\Admin;
return new class extends Logic\Admin\BaseController{

    const TITLE = '新建管理员';
    const DESCRIPTION = '新建管理员';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [
        'name' => 'string(required)#用户名',
        'password' => 'string(required)#密码',
        'password_confirm' => 'string(required)#确认密码'
    ];
    const SCHEMAs = [
        200 => [
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run(){

        (new AdminValidate())->paramsCheck('create',$this->request,$this->response);
        $params = $this->request->getParams();

        $adminModel = new Admin();
        $adminModel->name = $params['name'];
        $adminModel->password = password_hash($params['password'],PASSWORD_DEFAULT);

        $res = $adminModel->save();
        if(!$res)
            return $this->lang->set(-2);
        return $this->lang->set(0);
    }
};