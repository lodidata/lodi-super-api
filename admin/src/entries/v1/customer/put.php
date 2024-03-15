<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:37
 */

use Lib\Validate\Admin\CustomerValidate;
use Logic\Admin\Log;

/**
 * 修改客户信息
 */
return new class extends Logic\Admin\BaseController
{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($customer_id = null)
    {
        (new CustomerValidate())->paramsCheck('update', $this->request, $this->response);
        $params = $this->request->getParams();

        $data=[];
        if(isset($params['website'])) $data['website']=$params['website'];
        if(isset($params['ip'])) $data['ip']=$params['ip'];
        if(isset($params['updated'])) $data['updated']=$params['updated'];

        $data['customer']=$params['customer'];
        $data['name']=$params['name'];
        $data['type']=$params['type'];

        $result = DB::table('customer')
            ->where('id', $customer_id)
            ->update($data);

        if ($result !== false) {
            $res=DB::table('quota')->where('cust_id','=',$customer_id)->get()->first();
            if($params['type']=='game'){
                if(!$res){
                    $res = DB::table('quota')->insert(['cust_id'=>$customer_id,'cust_name'=>$params['name'],'customer'=>$params['customer']]);
                    if($res){
                        \Logic\Recharge\Recharge::requestPaySitByCustomer("postQuota",'', ['cust_id'=>$customer_id,'cust_name'=>$params['name'],'customer'=>$params['customer']]);
                    }
                }else{
                    \Logic\Recharge\Recharge::requestPaySitByCustomer("putQuota",'', ['cust_id'=>$customer_id,'cust_name'=>$params['name'],'customer'=>$params['customer']]);
                }
            }
            /*============================日志操作代码=====================*/
            $sta = $result !== false ? 1 : 0;
            (new Log($this->ci))->create(null, null, Log::MODULE_CUSTOMER, '客户设置', '修改客户信息', "修改", $sta, "客户名称：{$params['name']}");
            /*============================================================*/
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};