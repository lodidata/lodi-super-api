<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:31
 */

return new class extends Logic\Admin\BaseController
{
    const TITLE = 'GET 订单信息查询';
    const DESCRIPTION = '订单信息查询';
    const HINT = '';
    const QUERY = [
        'customer_id' => '客户ID',
        'page' => '页码',
        'page_size' => '每页大小',
        'pay_chan_id' => '支付渠道id',
        'order_number'=>'厅主订单号',
        'third_order'=>'第三方订单号'
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            'id' => '订单id',
            'cust_name' => '客户名称',
            'pay_chan_name'=>'渠道名称',
            'scene'=>'渠道类型',
            'order_number' => '第三方订单号',
            'order_money'=>'订单金额',
            'third_order'=>'第三方订单号',
            'third_money'=>'第三方金额',
            'created'=>'订单创建时间',
            'status'=>'支付状态',
            'page' => '页码',
            'page_size' => '每页大小',
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {

        $params = $this->request->getParams();
        if(count($params) <= 2) {
            $params['start_time'] = date('Y-m-d');
            $params['end_time'] = date('Y-m-d' . ' 23:59:59');
            $params['status'] = 'pending';
        }
        (new \Lib\Validate\Admin\PayValidate())->paramsCheck('get_order', $this->request, $this->response);

        $sql = DB::connection('pay')
            ->table('order')
            ->leftJoin('customer', 'customer_id', '=', 'customer.id')
            ->leftJoin('passageway', 'passageway_id', '=', 'passageway.id')
            ->leftJoin('pay_config', 'pay_config_id', '=', 'pay_config.id')
            ->leftJoin('pay_channel', 'channel_id', '=', 'pay_channel.id')
            ->select('order.id','customer.name as cust_name', 'pay_channel.name as pay_chan_name', 'scene', 'order.order_number', 'order_money', 'third_order', 'third_money', 'order.created', 'order.status');
        $sql = isset($params['customer_id']) && !empty($params['customer_id']) ? $sql->where('order.customer_id', $params['customer_id']) : $sql;
        $sql = isset($params['pay_chan_id']) && !empty($params['pay_chan_id']) ? $sql->where('pay_config.channel_id', $params['pay_chan_id']) : $sql;
        $sql = isset($params['order_number']) && !empty($params['order_number']) ? $sql->where('order.order_number', $params['order_number']) : $sql;
        $sql = isset($params['third_order']) && !empty($params['third_order']) ? $sql->where('order.third_order', $params['third_order']) : $sql;
        $sql = isset($params['start_time']) && !empty($params['start_time']) ? $sql->where('order.created', $params['start_time']) : $sql;
        $sql = isset($params['end_time']) && !empty($params['end_time']) ? $sql->where('order.created', $params['end_time']) : $sql;
        $sql = isset($params['status']) && !empty($params['status']) ? $sql->where('order.status', $params['status']) : $sql;

        $total = $sql->count();
        $data = $sql
            ->orderBy('order.created', 'desc')
            ->forPage($params['page'], $params['page_size'])
            ->get()
            ->toArray();
        return $this->lang->set(0, [], $data, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    }
};