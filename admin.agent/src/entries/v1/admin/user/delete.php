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

    const TITLE = '删除管理员';
    const DESCRIPTION = '删除管理员';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [

    ];
    const SCHEMAs = [
        200 => [
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id = ''){

        $this->checkID($id);

        $adminModel = (new Admin())::find($id);
        if(!$adminModel)
            return $this->lang->set(9);

        $adminModel->delete();
        if(!$adminModel->trashed())
            return $this->lang->set(-2);
        return $this->lang->set(0);

    }
};