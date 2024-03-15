<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:37
 */

use Lib\Validate\Admin\CustomerValidate;

return new class extends Logic\Admin\BaseController
{
    const TITLE = 'PUT 修改状态';
    const DESCRIPTION = '修改状态';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [
        'notify_id' => 'integer(required)#回调地址ID',
        'status' => 'string(required)#状态'
    ];
    const SCHEMAs = [
        200 => [
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($notify_id = null)
    {
        $this->checkID($notify_id);
        (new CustomerValidate())->paramsCheck('putsta', $this->request, $this->response);
        $status = $this->request->getParam('status', null);
        if ($status == null) {
            return $this->lang->set(3);
        } else {
            $result = DB::table('customer_notify')
                ->where('id', $notify_id)
                ->update(['status' => $status]);
            if ($result !== false) {
                return $this->lang->set(0);
            }
        }
        return $this->lang->set(-2);
    }
};