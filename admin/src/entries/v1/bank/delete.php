<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */

use Lib\Validate\Admin\BankValidate;
use Lib\Validate\Admin\CustomerValidate;
use Logic\Admin\BaseController;

/**
 * 删除银行信息
 */
return new class extends BaseController
{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id = null)
    {
        $this->checkID($id);
        (new BankValidate())->paramsCheck('patch', $this->request, $this->response);

        $result = DB::table('bank')->delete($id);
        if ($result) {
            $res = \Logic\Recharge\Recharge::requestPaySit("banksDel",'all', ['id'=>$id]);
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }

};