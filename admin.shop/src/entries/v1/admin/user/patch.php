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
return new class extends BaseController{
    const TITLE = '管理员修改密码';
    const PARAMs = [
        'name' => 'string(required)#用户名',
        'password' => 'string(optional)#密码',
    ];
    const SCHEMAs = [
        "200" => '操作成功'
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
        if(!$res)
            return $this->lang->set(-2);

        return $this->lang->set(0);
    }
};