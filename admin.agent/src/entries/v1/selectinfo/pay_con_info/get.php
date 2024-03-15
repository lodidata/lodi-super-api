<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:31
 */

return new class extends Logic\Admin\BaseController
{
    const TITLE = 'GET 支付渠道信息下拉框   返回 pay_con_id';
    const DESCRIPTION = '支付渠道信息下拉框    新增支付类型的时候需要使用这个接口';
    const HINT = '';
    const QUERY = [
        'cust_id' => '客户id'
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            "pay_con_id" => "支付渠道id",
            "name" => "支付名称",
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $cust_id = $this->request->getParam('cust_id');

        $sql = DB::connection('pay')->table('pay_config')
            ->join('pay_channel', 'pay_channel.id', '=', 'pay_config.channel_id')
            ->where('pay_config.status', '<>', 'deleted');
        if ($cust_id != null) {
            $sql =$sql
                ->where('customer_id', $cust_id);
        } else {
            $sql;

        }
        $data = $sql
            ->select( 'pay_config.id as pay_con_id', 'pay_channel.name','partner_id','pay_channel.id as channel_id')
            ->get()
            ->toArray();
        foreach ($data as $key=>$item) {
            $str=$data[$key]->name.'  (商户号--'.$data[$key]->partner_id.')';
            $data[$key]->name=$str;
        }
        return $this->lang->set(0, [], $data);

    }
};