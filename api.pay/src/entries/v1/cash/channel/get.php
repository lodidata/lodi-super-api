<?php
use Utils\Www\Action;

return new class extends Action {

    const TITLE       = '第三方支付名称列表';
    const DESCRIPTION = '第三方支付名称列表';
    const HINT        = '';
    const QUERY       = [
    ];
    const TYPE        = 'text/json';

    public function run(){
        $rs = \DB::table('pay_config AS p')->leftJoin('pay_channel AS c','p.channel_id','=','c.id')
              ->where('p.customer_id','=',CUSTOMERID)->select(['c.id','c.name','c.code'])->get()->toArray();
        return $rs;
   }

};