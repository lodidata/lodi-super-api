<?php
use Utils\Www\Action;
return new class extends Action {
    const TITLE = '平台请求支付通道';
    const QUERY = [
        'type' => 'string(optional) #类型',
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            'scene' =>'string #通道标识',
            'min_money' => 'int #通道最小金额',
            'max_money' => 'int #通道最大金额',
            'pay_id' => 'int #渠道名',
        ],
    ];
    public function run() {
        $type = $this->request->getParam('type');
        $query = \DB::table('passageway AS w')->leftJoin('pay_config AS c','w.pay_config_id','=','c.id')
            ->leftJoin('pay_channel AS p','c.channel_id','=','p.id')
            ->where('w.customer_id',CUSTOMERID)
            ->where('w.status','=','enabled');
        $type && $query->where('w.scene','=',$type);
        $passageway = $query->orderBy('w.sort')->get([
            'w.id','w.show_type','w.scene','w.min_money','w.field as comment',
            'w.max_money','w.link_data','w.sort',
            'w.money_used','w.money_day_used','w.money_day_stop','w.money_stop',
            'p.id as pay_id','p.name as pay_name']);
        if($passageway)
            return $passageway->toArray();
        return '';
    }
};