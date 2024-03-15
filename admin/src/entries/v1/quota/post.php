<?php

use Lib\Validate\BaseValidate;
use Logic\Admin\BaseController;

return new class() extends BaseController
{

    public function run($id = null)
    {
        $params = $this->request->getParams();
        $c_name = \DB::table('customer')->where('customer', $params['customer'])->first(['id', 'name']);
        if(empty($c_name)){
            return $this->lang->set(886, ['customer不能为空']);
        }

        $res = \DB::table('quota')
            ->where('customer', '=', $params['customer'])
            ->update(['use_quota'=>$params['use_quota'],'surplus_quota'=>$params['surplus_quota']]);
        echo true;
    }

};