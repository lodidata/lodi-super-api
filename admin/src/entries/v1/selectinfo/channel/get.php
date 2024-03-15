<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:31
 */

return new class extends Logic\Admin\BaseController {
    const TITLE = 'GET 支付渠道信息下拉框   返回 pay_con_id';
    const DESCRIPTION = '支付渠道信息下拉框    新增支付类型的时候需要使用这个接口';
    const HINT = '';
    const QUERY = [
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            "id"   => "支付渠道id",
            "name" => "支付名称",
        ],
    ];

    protected $beforeActionList = [
        'verifyToken',
        'authorize',
    ];

    public function run()
    {
        $data = DB::connection('pay')->table('pay_channel')->orderBy('created', 'desc')->get(['id', 'name'])->toArray();

        $types = [
            ['code' => 'wx', 'name' => '微信'],
            ['code' => 'alipay', 'name' => '支付宝'],
            ['code' => 'unionpay', 'name' => '银联'],
            ['code' => 'qq', 'name' => 'QQ'],
            ['code' => 'jd', 'name' => '京东'],
            ['code' => 'ysf', 'name' => '云闪付'],
        ];

        return $this->lang->set(0, [], ['channel' => $data, 'type' => $types]);
    }
};