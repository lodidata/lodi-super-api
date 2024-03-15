<?php

use Logic\Admin\BaseController;

return new class() extends BaseController
{
    //前置方法
    protected $beforeActionList = [
        'verifyToken',
        'authorize',
    ];

    public function run()
    {
        $res = \DB::table('quota')
            ->get()
            ->toArray();

        $log = DB::table('quota_log')
            ->orderBy('updated', 'desc')
            ->groupBy('cust_id')
            ->get()
            ->toArray();
        $data = [];
        foreach ($res as $key=>$value) {
            $value = (array)$value;
            $data[$key]['id'] = $value['id'];
            $data[$key]['cust_id'] = $value['cust_id'];
            $data[$key]['cust_name'] = $value['cust_name'];
            $data[$key]['total_quota'] = $value['total_quota'];
            $data[$key]['use_quota'] = $value['use_quota'];
            $data[$key]['surplus_quota'] = $value['surplus_quota'];
            $data[$key]['scale'] = $value['scale'];
            if (!$log) {
                $data[$key]['clear_start_date'] = '';
                $data[$key]['clear_end_date'] = '';
                $data[$key]['clear_user'] = '';
            } else {
                foreach ($log as $item) {
                    $item=(array)$item;
                    if ($data[$key]['cust_id'] == $item['cust_id']) {
                        $data[$key]['clear_start_date'] = $item['clear_start_date'];
                        $data[$key]['clear_end_date'] = $item['clear_end_date'];
                        $data[$key]['clear_user'] = $item['clear_user'];
                    }
                }
            }
        }

        return $data;
    }

};