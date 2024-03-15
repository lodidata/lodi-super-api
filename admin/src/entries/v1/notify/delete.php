<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */

use Logic\Admin\Log;


/**
 * 客户信息或者异步回调地址删除
 */
return new class extends Logic\Admin\BaseController
{
    const TITLE = 'DELETE 异步回调地址删除';
    const DESCRIPTION = '异步回调地址删除';
    const HINT = '';
    const QUERY = [
        'notify_id' => '回调地址ID'
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
        ]
    ];


    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];
    public function run($notify_id='')
    {
        $this->checkID($notify_id);
        if($notify_id==null){
            return $this->lang->set(3);
        }else{
            $customer_id = \DB::table('customer_notify')->where('id', $notify_id)->value('customer_id');
            if(!$customer_id){
                return $this->lang->set(3);
            }
            $customer_name = \DB::table('customer')->where('id', $customer_id)->value('name');
            $res =DB::table('customer_notify')->where('id', $notify_id)->delete();
            if ($res) {
                /*============================日志操作代码=====================*/
                $sta = $res !== false ? 1 : 0;
                (new Log($this->ci))->create(null, null, Log::MODULE_CUSTOMER, '客户域名设置', '删除回调地址', "删除", $sta, "客户名称：{$customer_name}");
                /*============================================================*/
                return $this->lang->set(0);
            }
            return $this->lang->set(-2);
        }
    }

};