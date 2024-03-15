<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 15:33
 */

use Lib\Validate\Admin\CustomerValidate;

return new class extends Logic\Admin\BaseController
{
    const TITLE = 'POST 新增域名';
    const DESCRIPTION = '新增域名';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [
        'customer_id' => 'integer(required)#客户ID',
        'admin_notify' => 'string(required)#admin回调地址',
        'www_notify' => 'string(required)#www回调地址'
    ];
    const SCHEMAs = [
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        (new CustomerValidate())->paramsCheck('put', $this->request, $this->response);
        $customer_id = $this->request->getParam('customer_id');
        $admin_notify = $this->request->getParam('admin_notify');
        $www_notify = $this->request->getParam('www_notify');

        $msg=DB::table('customer_notify')
            ->where('admin_notify',$admin_notify)
            ->where('www_notify',$www_notify)
            ->where('customer_id',$customer_id)
            ->count();
        if($msg>0){
            return $this->lang->set(8);
        }else{
            $result = DB::table('customer_notify')->insert(
                ['admin_notify' => $admin_notify, 'www_notify' => $www_notify, 'customer_id' => $customer_id]
            );
            if ($result) {
                return $this->lang->set(0);
            }
        }
        return $this->lang->set(-2);
    }

};