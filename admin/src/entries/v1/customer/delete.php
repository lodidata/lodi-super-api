<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */

use Logic\Admin\Log;


/**
 * 删除客户
 */
return new class extends Logic\Admin\BaseController
{

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($customer_id = '')
    {
        $this->checkID($customer_id);
        $info = DB::table('customer')->find($customer_id);
        $res = DB::table('customer')->where('id', $customer_id)->delete();
        if ($res) {
            $res1 = DB::table('customer_notify')->where('customer_id', $customer_id)->delete();
            $res2 = DB::table('quota')->where('cust_id', $customer_id)->delete();

            /*============================日志操作代码=====================*/
            $sta = $res !== false ? 1 : 0;
            (new Log($this->ci))->create(null, null, Log::MODULE_CUSTOMER, '客户设置', '删除客户', "删除", $sta, "客户名称：{$info->name}");
            /*============================================================*/
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }

};