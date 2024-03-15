<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:31
 */

return new class extends Logic\Admin\BaseController
{
    const TITLE = 'GET 支付渠道信息下拉框   返回 channel_id';
    const DESCRIPTION = '支付渠道信息下拉框';
    const HINT = '';
    const QUERY = [
        'cust_id' => '客户id'
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            "channel_id" => "支付id",
            "name" => "支付名称",
        ]
    ];

    protected $fieldDesc = [
          "app_id" => '#提供商分配的应用id',
          "app_secret" => '#提供商分配的应用secret',
          "app_site" => '#提供商分配的应用site',
          "key" => '#提供商分配厅主的加密私钥',
          "pub_key" => '#提供商分配厅主的解密公钥(只有一个则和key相同)',
          "token" => '#提供商分配的token',
          "terminal" => '#提供商分配的terminal',
          "partner_id" => '#提供商分配的商户ID',
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $cust_id = $this->request->getParam('cust_id');
        $query = DB::connection('pay')->table('pay_channel')->orderBy('id','desc');
        if($cust_id){
            $query->leftJoin('pay_config','pay_channel.id', '=', 'pay_config.channel_id')
                ->where('pay_config.customer_id', $cust_id);
        }
        $data = $query->get(['pay_channel.id','pay_channel.name','pay_channel.name','pay_channel.desc'])->toArray();
        foreach ($data as $key=>&$item) {
            $item = (array)$item;
            //判断是否是json格式
            if(strpos($item['desc'],'{') !== false && strpos($item['desc'],'}') !== false){
                $tmp = json_decode($item['desc'],true);
                foreach ($tmp as $k=>$v) {
                    $item['config'][] = ['code' => $k, 'str' => $v ? '   #'.$v : '请找技术核对'];
                }
            }else {
                $tmp = explode(',', $item['desc']);
                foreach ($tmp as $v) {
                    $item['config'][] = ['code' => $v, 'str' => $this->fieldDesc[$v] ?? '请找技术核对'];
                }
            }
            $item['str'] = '如有疑问请看其它客户的配置';
        }
        return $this->lang->set(0, [], $data);

    }
};