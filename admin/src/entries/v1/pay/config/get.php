<?php
/**
 * @author Taylor 2019-03-09
 * 第三方支付配置查询
 */
use Logic\Admin\BaseController;

return new class extends BaseController
{
    const TITLE = 'GET 第三方配置查询';
    const DESCRIPTION = '第三方配置查询';
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
            'id' => '第三方配置id',
            'channel_id' => '通道id',
            'scene' => '场景，wx,alipay,unionpay,qq,jd,ysf',
            'action' => '该通道第三方方法',
            'payurl' => '支付调用地址',
            'bank_data' => '银行参数',
            'link_data' => '链接参数',
            'return_type' => '跳转方式，code,jump,url',
            'show_type' => '显示方式，js,quick,h5,code',
            'field' => '备用字段',
            'sort' => '排序',
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $this->verifyToken();
        $params = $this->request->getParams();

        $sql = DB::connection('pay')->table('passageway_config')
            ->leftJoin('pay_channel', 'passageway_config.channel_id', '=', 'pay_channel.id')
            ->select('pay_channel.name', 'pay_channel.code', 'passageway_config.*');
        $sql = isset($params['name']) && !empty($params['name']) ? $sql->where('pay_channel.name', 'like', "%{$params['name']}%") : $sql;
        $sql = isset($params['code']) && !empty($params['code']) ? $sql->where('pay_channel.code', 'like', "%{$params['code']}%") : $sql;
        $total = $sql->count();
        $data = $sql->orderBy('id', 'desc')->forPage($params['page'],$params['page_size'])->get()->toArray();
        return $this->lang->set(0, [], $data, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    }
};