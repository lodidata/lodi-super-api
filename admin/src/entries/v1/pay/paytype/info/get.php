<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/3
 * Time: 15:07
 */

use Lib\Validate\Admin\PayValidate;

/**
 * 查询支付渠道类型信息
 */
return new  class extends \Logic\Admin\BaseController
{

    const TITLE = 'GET 查询支付渠道类型信息';
    const DESCRIPTION = '查询支付渠道类型信息';
    const HINT = '';
    const QUERY = [
        'scene' => '支付渠道类型',
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
            'pass_id' => '支付类型id',
            'cust_name' => '客户名称',
            'chan_name' => '支付渠道名称',
            'scene' => '支付渠道类型',
            'show_type' => '分类',
            'money_day_used' => '日累计金额',
            'money_day_stop' => '日停用金额',
            'min_money' => '单次最小',
            'max_money' => '单次最大',
            'payurl' => '第三方请求地址',
            'created' => '创建时间',
            'updated' => '更新时间',
            'page' => '页码',
            'page_size' => '每页大小',
            'total' => '总数'
        ]
    ];

    public function run()
    {
        $params = $this->request->getParams();
        $sql = DB::connection('pay')
            ->table('passageway')
            ->leftJoin('pay_config', 'pay_config_id', '=', 'pay_config.id')
            ->leftJoin('customer', 'passageway.customer_id', '=', 'customer.id')
            ->leftJoin('pay_channel', 'pay_channel.id', '=', 'pay_config.channel_id')
            ->select('pay_config.id AS pay_con_id','pay_config.partner_id','passageway.id as pass_id', 'customer.name as cust_name', 'pay_channel.name as chan_name', 'scene', 'show_type', 'money_day_used', 'money_day_stop', 'min_money', 'max_money', 'payurl', 'passageway.created', 'passageway.updated','pay_channel.id as pay_chan_id','customer.id as cust_id')
            ->where('passageway.status', '<>', 'deleted');

        $sql = isset($params['scene']) && !empty($params['scene']) ? $sql->where('scene', $params['scene']) : $sql;
        $sql = isset($params['customer_id']) && !empty($params['customer_id']) ? $sql->where('passageway.customer_id', $params['customer_id']) : $sql;
        $sql = isset($params['channel_id']) && !empty($params['channel_id']) ? $sql->where('pay_channel.id', $params['channel_id']) : $sql;
        $sql = isset($params['partner_id']) && !empty($params['partner_id']) ? $sql->where('pay_config.partner_id', $params['partner_id']) : $sql;
        $total = $sql->count();
        $data = $sql->orderBy('passageway.created', 'desc')
            ->forPage($params['page'], $params['page_size'])
            ->get()
            ->toArray();
//        print_r(DB::connection('pay')->getQueryLog());exit;
        return $this->lang->set(0, [], $data, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    }
};