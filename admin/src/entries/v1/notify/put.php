<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:37
 */


use Lib\Validate\Admin\NotifyValidate;
use Logic\Admin\Log;

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
        200 => [
        ]
    ];
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($notify_id = null)
    {
        (new NotifyValidate())->paramsCheck('update',$this->request,$this->response);
        $param=$this->request->getParams();
            $result =DB::table('customer_notify')
                ->where('id', $notify_id)
                ->update(['customer_id' => $param['customer_id'], 'admin_notify' => $param['admin_notify'], 'www_notify' => $param['www_notify']]);

            if ($result !== false) {
                $customer_name = \DB::table('customer')->where('id', $param['customer_id'])->value('name');
                /*============================日志操作代码=====================*/
                $sta = $result !== false ? 1 : 0;
                $str = "客户名称：{$customer_name} 前端域名:{$param['www_notify']} 后台域名：{$param['admin_notify']}";
                (new Log($this->ci))->create(null, null, Log::MODULE_CUSTOMER, '客户域名设置', '修改域名', "修改", $sta, $str);
                /*============================================================*/
                return $this->lang->set(0);
            }
            return $this->lang->set(-2);
    }
};