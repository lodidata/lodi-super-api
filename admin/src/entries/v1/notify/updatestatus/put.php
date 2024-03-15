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

    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($notify_id = null)
    {
        $this->checkID($notify_id);
        (new NotifyValidate())->paramsCheck('putsta', $this->request, $this->response);
        $status = $this->request->getParam('status', null);
        if ($status == null) {
            return $this->lang->set(3);
        } else {
            $result = DB::table('customer_notify')
                ->where('id', $notify_id)
                ->update(['status' => $status]);
            if ($result !== false) {
                $customer_id = \DB::table('customer_notify')->where('id', $notify_id)->value('customer_id');
                if(!$customer_id){
                    return $this->lang->set(3);
                }
                $customer_name = \DB::table('customer')->where('id', $customer_id)->value('name');
                /*============================日志操作代码=====================*/
                $sta = $result !== false ? 1 : 0;
                $status_str = $status=='enabled' ? '开启':'关闭';
                $str = "客户名称：{$customer_name} 状态:{$status_str}";
                (new Log($this->ci))->create(null, null, Log::MODULE_CUSTOMER, '客户域名设置', '修改状态', "修改", $sta, $str);
                /*============================================================*/
                return $this->lang->set(0);
            }
        }
        return $this->lang->set(-2);
    }
};