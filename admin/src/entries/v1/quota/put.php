<?php

use Lib\Validate\BaseValidate;
use Logic\Admin\BaseController;

return new class() extends BaseController
{
    //前置方法
    protected $beforeActionList = [
        'verifyToken',
        'authorize',
    ];

    public function run($id = null)
    {
        $this->checkID($id);
        $validate = new BaseValidate([
            'charge_balance' => 'require',
            'buy_balance' => 'require',
            'quota' => 'require',
        ], [
                'charge_balance' => '收费金额比重',
                'buy_balance' => '购彩金额比重',
                'quota' => '授信额度',
            ]
        );
        $validate->paramsCheck('', $this->request, $this->response);

        $charge_balance = $this->request->getParam('charge_balance');//收费金额比重
        $buy_balance = $this->request->getParam('buy_balance');//购彩金额比重
        $quota = $this->request->getParam('quota');//授信额度

        $res = \DB::table('quota')
            ->where('id', '=', $id)
            ->update(['total_quota' => $quota, 'admin_user' => $this->getAdminUserName(), 'scale' => $charge_balance . ':' . $buy_balance]);
        if ($res !== false) {
             $re=\Logic\Recharge\Recharge::requestPaySit("putQuota",'', ['total_quota' => $quota, 'admin_user' => $this->getAdminUserName(), 'scale' => $charge_balance . ':' . $buy_balance]);
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }

};