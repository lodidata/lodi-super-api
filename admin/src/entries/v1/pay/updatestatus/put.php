<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/2
 * Time: 18:09
 */


/**
 * 修改禁用启用状态
 */
return new class extends Logic\Admin\BaseController
{
    const TITLE = 'PUT 修改禁用启用状态';
    const DESCRIPTION = '修改禁用启用状态';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [
        'pay_con_id' => 'integer(required)# pay_con_id  支付id',
        'status' => 'string(required)#状态'
    ];
    const SCHEMAs = [
        200 => [
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($pay_con_id =null)
    {
        $this->checkID($pay_con_id);
        (new \Lib\Validate\Admin\PayValidate())->paramsCheck('putsta', $this->request, $this->response);
        $status = $this->request->getParam('status');
        $result = DB::connection('pay')->table('pay_config')
            ->where('id', $pay_con_id)
            ->update(['status' => $status]);
        if($status == 'disabled') {
            DB::connection('pay')->table('passageway')
                ->where('pay_config_id', $pay_con_id)
                ->update(['status' => $status]);
        }
        if ($result !== false) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};