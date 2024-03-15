<?php
use Logic\Admin\BaseController;
return new class() extends BaseController{
    const STATE       = '';
    const TITLE       = '线上入款详情';
    const DESCRIPTION = '获取线上入款详情';
    const HINT        = '';
    const QUERY       = [];
    const TYPE        = 'text/json';
    const PARAMs      = [];
    const SCHEMAs     = [
        200 => [
            'trade_no '           => 'string #订单号',
            'name'                => 'string #用户名',
            'ranting'             => 'string  #用户等级',
            'true_name'           => 'string #存款人',
            'created'             => 'string(datetime) #注册时间',
            'agent_name'          => 'string   #上级代理人',
            'total_deposit_times' => 'int   #存款次数',
            'total_deposit_money' => 'int   #存款金额',
            'money'               => 'int #存入金额',
            'receive_bank_info'   => 'string    #支付信息',
            'pay_no'              => 'string #交易号',
            'ip'                  => 'string   #ip',
            'recharge_time'       => 'string(datetime)    #交易时间',
            'coupon_money'        => 'int    #优惠金额',
            'withdraw_bet'        => 'int    #取款要求',
            'coupon_name'         => 'int #优惠名称',
            'memo'                => 'string #备注',
            'status'              => 'enum[paid, pending, failed]    #状态',
            'state'               => 'set[show, new, auto, online] #集合',
        ],
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run($id){
        $this->checkID($id);
        $query = \DB::table('funds_deposit as deposit')
            ->leftJoin('user','deposit.user_id','=','user.id')
//            ->leftJoin('user_level as level','user.ranting','=','level.level')
//            ->leftJoin('profile','deposit.user_id','=','profile.user_id')
//            ->leftJoin('user_agent as agent','user.id','=','agent.user_id')
            ->where('deposit.id','=',$id);
        $data = $query->first([
//            'deposit.active_id',
//            'deposit.active_apply',
//            'deposit.active_name',
//            'deposit.active_id_other',
            'deposit.coupon_money',
            'deposit.created',
            'deposit.marks',
            'deposit.memo',
            'deposit.money',
//            'deposit.withdraw_bet',
            'deposit.receive_bank_info as channel_name',
            'deposit.process_time',
            'deposit.recharge_time',
            'deposit.status',
            'deposit.state',
            'deposit.trade_no',
            'deposit.pay_no',
            'deposit.user_id',
            'deposit.name as user_name',
            'deposit.ip',
//            'level.name as ranting',
//            'agent.uid_agent_name as agent_name',
//            'profile.name as true_name',
            'user.created as register_time',
        ]);
        $state = \DB::table('user_data')->where('user_id','=',$data->user_id)->first(['deposit_num', 'deposit_amount']);
        //获取所有的活动
        $data->active_name = '';
//        $actives = property_exists($data,'active_apply') ? $data->active_apply : '';
//        if($actives){
//            foreach (explode(',',$actives) ?? [] as $active_apply_id){
//                $active_apply= DB::table('active_apply')->find($active_apply_id);
//                if($active_apply)
//                    $data->active_name .= " ".$active_apply->active_name .'赠送'.($active_apply->coupon_money/100). ($active_apply->state == 'auto' ? '(自动)' : '(手动)');
//            }
//        }
        $data->active_name = trim($data->active_name,'/');
        $data->total_deposit_money = $state->deposit_amount ?? 0;
        $data->total_deposit_times = $state->deposit_num ?? 0;
        return (array)$data;
    }
};
