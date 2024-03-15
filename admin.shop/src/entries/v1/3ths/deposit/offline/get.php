<?php
use Logic\Admin\BaseController;

return new class() extends BaseController{
    const TITLE       = '线下入款详情';
    const DESCRIPTION = '线下入款详情';
    const QUERY       = [
        'id'         => 'int(optional)   #页码',
    ];
    const TYPE        = 'text/json';
    const SCHEMAs     = [
        200 => [
            'type'  => 'enum[rowset, row, dataset]',
            'size'  => 'unsigned',
            'total' => 'unsigned',
            'data'  => 'rows[id:int,trade_no:string,ranting:int,agent_name:string,user_name:string,deposit_type:int,pay_type:string
                name:string,money:int,pay_bank_info:string,receive_bank_info:string, coupon_money:int,recharge_time:string,
                status:set[paid,pending,cancel,deposit], ip:string,memo:string,state:set[show,new,auto,online],update_uname:string,update:string]',
        ],
    ];
    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run($id=null){
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
            'deposit.deposit_type',
            'deposit.marks',
            'deposit.memo',
            'deposit.money',
            'deposit.pay_bank_info',
            'deposit.receive_bank_info',
            'deposit.process_time',
            'deposit.recharge_time',
            'deposit.status',
            'deposit.state',
            'deposit.trade_no',
            'deposit.user_id',
            'deposit.name as deposit_name',
            'user.name as user_name',
            'deposit.ip',
//            'deposit.withdraw_bet',
//            'level.name as ranting',
//            'agent.uid_agent_name as agent_name',
//            'profile.name as true_name',
            'user.created as generate_time',
        ]);
        //获取所有的活动
//        $activeData = \DB::table('active')->leftJoin('active_rule AS rule','active.id','=','rule.active_id')
//            ->where('rule.issue_mode','=','auto')->select(['active.id','active.name'])->get()->toArray();
//        $activeArr=[];
//        foreach ($activeData ?? [] as $k=>$v){
//            $v = (array)$v;
//            $id = $v['id'];
//            $activeArr[$id] = $v['name'];
//        }
//        if(isset($activeArr[$data->active_id])) {
//            $data->active_name = $activeArr[$data->active_id];
//        }
//        if(isset($activeArr[$data->active_id_other])) {
//            $data->active_name .= "/".$activeArr[$data->active_id_other];
//        }
//        $data->active_name = trim($data->active_name,'/');
        $data->active_name = '';
        $actives = property_exists($data,'active_apply') ? $data->active_apply : '';
        if($actives){
            foreach (explode(',',$actives) ?? [] as $active_apply_id){
                $active_apply= DB::table('active_apply')->find($active_apply_id);
//                    ->leftJoin('active as a','ap.active_id','=','a.id')
//                    ->leftJoin('active_template as template','a.type_id','=','template.id')
//                    ->selectRaw('ap.memo,ap.state,template.name as active_name,ap.coupon_money')
//                    ->where('ap.id',$active_apply_id)
//                    ->first();
                if($active_apply)
                    $data->active_name .= " ".$active_apply->active_name .'赠送'.($active_apply->coupon_money/100). ($active_apply->state == 'auto' ? '(自动)' : '(手动)');
            }
        }
        $data->in_money = $data->money;
        $state = \DB::table('user_data')->where('user_id','=',$data->user_id)->first(['deposit_num', 'deposit_amount']);
        $data->total_deposit_money = $state->deposit_amount ?? 0;
        $data->total_deposit_times = $state->deposit_num ?? 0;
        $data = (array)$data;
        $data = \Utils\Utils::DepositPatch($data);
        $data["receive_bank_info"] = json_decode($data["receive_bank_info"]);
        $data["pay_bank_info"] = json_decode($data["pay_bank_info"]);
        return $data;
    }
};
