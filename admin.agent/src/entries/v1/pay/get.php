<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:31
 */

return new class extends Logic\Admin\BaseController
{
    const TITLE = 'GET 支付渠道信息查询';
    const DESCRIPTION = '支付渠道信息查询';
    const HINT = '';
    const QUERY = [
        'status' => '支付渠道状态',
        'customer_id' => '客户ID',
        'channel_id' => '渠道ID',
        'partner_id' => '商户ID',
        'page' => '页码',
        'page_size' => '每页大小',
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            'cust_name' => '客户名称',
            'pay_name' => '支付渠道名称',
            'id' => 'id',
            'partner_id' => '商户号',
            'pub_key' => '公钥',
            'key' => '秘钥',
            'app_id' => 'appID',
            'app_secret' => 'app_secret',
            'app_site' => 'app_site',
            'token' => 'token',
            'terminal' => 'terminal'
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $params=$this->request->getParams();
        (new \Lib\Validate\Admin\PayValidate())->paramsCheck('get',$this->request,$this->response);
        $sql = DB::connection('pay')->table('pay_config')
            ->leftJoin('pay_channel', 'pay_config.channel_id', '=', 'pay_channel.id')
            ->leftJoin('customer', 'customer_id', '=', 'customer.id')
            ->select('customer.name as cust_name', 'pay_channel.name as pay_name','customer_id','pay_channel.id as pay_chan_id', 'pay_config.id as pay_con_id', 'partner_id', 'pub_key', 'key', 'app_id', 'app_secret', 'app_site', 'token', 'terminal','pay_config.created','pay_config.updated','pay_config.status')
            ->where('pay_config.status','<>','deleted');

        $sql=isset($params['status'])&&!empty($params['status'])?$sql->where('pay_config.status', $params['status']):$sql;
        $sql=isset($params['channel_id'])&&!empty($params['channel_id'])?$sql->where('pay_config.channel_id', $params['channel_id']):$sql;
        $sql=isset($params['customer_id'])&&!empty($params['customer_id'])?$sql->where('pay_config.customer_id', $params['customer_id']):$sql;
        $sql=isset($params['partner_id'])&&!empty($params['partner_id'])?$sql->where('pay_config.partner_id', $params['partner_id']):$sql;
        $total = $sql->count();
        $data = $sql->orderBy('pay_config.created', 'desc')
            ->forPage($params['page'],$params['page_size'])
            ->get()
            ->toArray();
        return $this->lang->set(0, [], $data, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    }
};