<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:31
 */

return new class extends Logic\Admin\BaseController
{
    const TITLE = 'GET 支付回调信息查询';
    const DESCRIPTION = '支付回调信息查询';
    const HINT = '';
    const QUERY = [
        'order_number' => '订单号',
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            'id' => '回调日志id',
            'order_number' => '订单号',
            'created' => '订单创建时间',
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
        (new \Lib\Validate\Admin\PayValidate())->paramsCheck('get_order_callback', $this->request, $this->response);
        $sql = DB::connection('pay')
            ->table('log_callback')
            ->select('id','order_number','created','ip','desc')
            ->where('order_number', $params['order_number']);
        $total = $sql->count();
        $data = $sql
            ->orderBy('created', 'desc')
            ->forPage($params['page'], $params['page_size'])
            ->get()
            ->toArray();

        $order = (array)DB::connection('pay')
            ->table('order')
            ->leftJoin('customer','customer.id','=','order.customer_id')
            ->leftJoin('passageway','passageway.id','=','order.passageway_id')
            ->leftJoin('pay_config','pay_config.id','=','passageway.pay_config_id')
            ->leftJoin('pay_channel','pay_channel.id','=','pay_config.channel_id')
            ->where('order.order_number', $params['order_number'])
            ->first(['customer.name','passageway.scene','pay_channel.name AS channel_name','order.third_order']);
        $arrScene = [
            'wx' => '微信',
            'alipay' => '支付宝',
            'unionpay' => '银联',
            'qq' => 'QQ',
            'jd' => '京东',
        ];
        foreach ($data as &$v){
            $v = (array)$v;
            $v['ip'] = \Utils\Utils::RSADecrypt($v['ip']);
            $v['customer_name'] = $order['name'] ?? '';
            $v['channel_name'] = $order['channel_name'] ?? '';
            $v['pay_type'] = $arrScene[$order['scene']] ?? '';
            $v['third_order'] = $order['third_order'] ?? '';
        }

        return $this->lang->set(0, [], $data, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    }
};