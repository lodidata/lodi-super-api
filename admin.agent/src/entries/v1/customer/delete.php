<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */


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
        $res =DB::table('customer_notify')->where('id', $notify_id)->delete();
        if($notify_id==null){
            return $this->lang->set(3);
        }else{
            if ($res) {
                return $this->lang->set(0);
            }
            return $this->lang->set(-2);
        }
    }

};