<?php
return new class extends Logic\Admin\BaseController {
    const TITLE = '回调IP白名单';
    const QUERY = [
        'type' => 'string(optional) #类型',
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            'id' =>'string #id',
            'channel_name' => 'string #渠道名',
            'channel_code' => 'string #渠道code', //兼容旧平台
            'ip' => 'string #IP白名单',  //加密串，  加密在相应的业务平台   支付平台不作加密
        ],
    ];
    public function run() {
        $customer_id = $this->request->getParam('customer_id');
        $channel_id = $this->request->getParam('channel_id');
        $ip = $this->request->getParam('ip');
        $size = $this->request->getParam('page_size',20);
        $page = $this->request->getParam('page',1);
        $query = \DB::connection('pay')->table('callback_ip_white AS a')
            ->leftJoin('pay_channel AS b','a.channel_id','=','b.id')
            ->leftJoin('customer AS c','a.customer_id','=','c.id');
        $customer_id && $query->where('a.customer_id','=',$customer_id);
        $channel_id && $query->where('b.id','=',$channel_id);
        if($ip){
            $query->where('a.ip','=',Utils\Utils::RSAEncrypt($ip));
        }
        $sum = clone $query;
        $ip_white = $query->orderBy('a.id','DESC')->forPage($page,$size)->get([
            'a.id',
            'a.customer_id',
            'a.channel_id',
            'b.name AS channel_name',
            'c.name AS customer_name',
            'a.channel_code',
            'a.ip',
            ])->toArray();
        foreach ($ip_white as &$v){
            $v->ip = \Utils\Utils::RSADecrypt($v->ip);
        }
        return $this->lang->set(0, [], $ip_white, ['number' => $page, 'size' => $size, 'total' => $sum->count()]);
    }
};