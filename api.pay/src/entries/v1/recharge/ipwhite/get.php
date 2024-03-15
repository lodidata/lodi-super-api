<?php
use Utils\Www\Action;
return new class extends Action {
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
        $channel_code = $this->request->getParam('channel_code');
        $channel_id = $this->request->getParam('channel_id');
        $ip = $this->request->getParam('ip');
        $size = $this->request->getParam('page_size',20);
        $page = $this->request->getParam('page',1);
        $query = \DB::table('callback_ip_white AS a')
            ->leftJoin('pay_channel AS b','a.channel_id','=','b.id')
            ->where('a.customer_id',CUSTOMERID);
        $channel_code && $query->where('a.channel_code','=',strtoupper($channel_code));
        $channel_id && $query->where('b.id','=',$channel_id);
        if($ip){
            $ip = \Utils\Utils::RSAEncrypt($ip);
            $query->where('a.ip','=',$ip);
        }
        $sum = clone $query;
        $ip_white = $query->orderBy('a.id','DESC')->forPage($page,$size)->get([
            'a.id',
            'b.name AS channel_name',
            'a.channel_code',
            'a.ip',
            ])->toArray();
        foreach ($ip_white as &$v){
            $v->ip = \Utils\Utils::RSADecrypt($v->ip);
        }
        $ip_switch = \DB::table('callback_ip_switch')->where('customer_id',CUSTOMERID)->where('channel_id',0)->value('switch');
        return ['data'=>$ip_white,'sum'=>$sum->count(),'ip_switch'=> $ip_switch ? 1 : 0];
    }
};