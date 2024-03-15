<?php
/**
 * @author Taylor 2019-03-09
 * 支付通道查询
 */
use Logic\Admin\BaseController;

return new class extends BaseController
{
    const TITLE = 'GET 支付通道';
    const DESCRIPTION = '支付通道';
    const HINT = '';
    const QUERY = [
        'name' => '渠道名称',
        'code' => '渠道代码',
        'page' => '页码',
        'page_size' => '每页大小',
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            'name' => '通道名称',
            'code' => '通道代码',
            'rule' => '支付规则',
            'moneys' => '金额取值范围',
            'return' => '给第三方返回标识',
            'param' => '接收第三方值的方式',
            'order_number' => '对应的第三方订单下标',
            'mode' => '接受参数方式',
            'status' => '启用状态',
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $this->verifyToken();
        $params = $this->request->getParams();
        $sql = DB::connection('pay')->table('pay_channel');

        $sql = isset($params['name']) && !empty($params['name']) ? $sql->where('name', 'like', "%{$params['name']}%") : $sql;
        $sql = isset($params['code']) && !empty($params['code']) ? $sql->where('code', 'like', "%{$params['code']}%") : $sql;
        $total = $sql->count();
        $data = $sql->orderBy('id', 'desc')->forPage($params['page'],$params['page_size'])->get()->toArray();
        return $this->lang->set(0, [], $data, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    }
};