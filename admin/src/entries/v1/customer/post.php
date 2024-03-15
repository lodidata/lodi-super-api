<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 15:33
 */

use Lib\Validate\Admin\CustomerValidate;
use Logic\Admin\Log;

/**
 * 新增客户
 */
return new class extends Logic\Admin\BaseController
{

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $params=$this->request->getParams();
        (new CustomerValidate())->paramsCheck('post', $this->request, $this->response);

        $result = \DB::table('customer')->insertGetId($params);
        if ($result) {
            if($params['type']=='game'){
                $result = DB::table('quota')->insert(['cust_id'=>$result,'cust_name'=>$params['name'],'customer'=>$params['customer']]);
                \Logic\Recharge\Recharge::requestPaySitByCustomer("postQuota",'', ['cust_id'=>$result,'cust_name'=>$params['name'],'customer'=>$params['customer']]);
            }

            /*============================日志操作代码=====================*/
            $sta = $result !== false ? 1 : 0;
            (new Log($this->ci))->create(null, null, Log::MODULE_CUSTOMER, '客户设置', '新增客户', "添加", $sta, "客户名称：{$params['name']}");
            /*============================================================*/

            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }

};