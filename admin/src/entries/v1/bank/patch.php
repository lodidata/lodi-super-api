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
 * 修改银行状态
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
        $status=$this->request->getParam('status');

        $result = DB::table('bank')->where('id', '=', $id)->update(['status'=>$status]);
        if ($result!==false) {
            $res = \Logic\Recharge\Recharge::requestPaySit("banksStatus",'all', ['status'=>$status,'id'=>$id]);
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }

};