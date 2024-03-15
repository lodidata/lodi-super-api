<?php

/**
 * 修改客户信息
 */

use Logic\Admin\Log;

return new class extends Logic\Admin\BaseController
{
//    protected $beforeActionList = [
//        'verifyToken', 'authorize',
//    ];

    public function run($customer_id)
    {
        $maintaining = $this->request->getParam('maintaining');
        $maintaining = intval($maintaining) ? 1 : 0;

        $notify = \DB::table('customer_notify')->where('customer_id', $customer_id)->where('status', 'enabled')->pluck('admin_notify')->toArray();
        if(!$notify)
            return $this->lang->set(-2);
        $customer_url = array_random($notify) . '/system';
        $res = \Utils\Curl::post($customer_url,null,['maintaining'=>$maintaining],'PUT');
        if(!$res) {
            return $this->lang->set(-2);
        }
        $res = DB::table('customer')
            ->where('id', $customer_id)
            ->update(['maintaining' => $maintaining]);
        if($res !== false){
            /*============================日志操作代码=====================*/
            $sta = $res !== false ? 1 : 0;
            (new Log($this->ci))->create(null, null, Log::MODULE_CUSTOMER, '客户设置', '修改客户信息', "修改", $sta, "客户ID".$customer_id."maintaining：{$maintaining}");
            /*============================================================*/
            return $this->lang->set(0);
        }
        return $this->lang->set(0);
    }
};