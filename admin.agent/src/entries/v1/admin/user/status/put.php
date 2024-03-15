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
        200 => [
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id=''){

        $this->checkID($id);
        (new AdminValidate())->paramsCheck('status',$this->request,$this->response);

        $adminModel = (new Admin())::find($id);
        if(!$adminModel)
            return $this->lang->set(9);
        $params = $this->request->getParams();
        $adminModel->status = $params['status'];
        $res = $adminModel->save();
        if(!$res)
            return $this->lang->set(-2);
        return $this->lang->set(0);
    }
};