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
    const TITLE = 'PUT 修改域名';
    const DESCRIPTION = '修改域名';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [
        'notify_id' => 'integer(required)#回调地址ID',
        'customer_id' => 'integer(required)#客户ID',
        'admin_notify' => 'string(required)#admin回调地址',
        'www_notify' => 'string(required)#www回调地址',
    ];
    const SCHEMAs = [

    ];
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($notify_id = null)
    {
        (new CustomerValidate())->paramsCheck('update',$this->request,$this->response);
        $param=$this->request->getParams();
            $result =DB::table('customer_notify')
                ->where('id', $notify_id)
                ->update(['customer_id' => $param['customer_id'], 'admin_notify' => $param['admin_notify'], 'www_notify' => $param['www_notify']]);

            if ($result !== false) {
                return $this->lang->set(0);
            }
            return $this->lang->set(-2);
    }
};