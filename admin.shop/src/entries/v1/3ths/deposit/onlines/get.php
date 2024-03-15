<?php
use Logic\Admin\BaseController;

return new class() extends BaseController{
    const STATE       = '';
    const TITLE       = '线上入款列表';
    const DESCRIPTION = '获取线上充值列表';
    const HINT        = '';
    const QUERY       = [
        'user_name'  => 'string(optional)   #用户名称',
        //'name'       => 'string(required) #商户名称id',
        'trade_no'   => 'string(optional)   #订单号',
//        'ranting'    => 'string(optional)   #用户等级，查询多个，逗号(,)分隔',
        'pay_scene'  => 'enum[wx,alipay,unionpay,qq,tz,jd] #支付类型/场景',
        'status'     => 'enum[pending,failed,paid]   #交易状态,支付状态(paid(已支付), pending(待支付),failed(支付失败))',
        //'channel'    => 'int(optional)   #渠道ID',
        'date_from'  => 'string(date,optional)   #开始时间',
        'date_to'    => 'string(date,optional) #结束时间',
        'money_from' => 'int(optional)  #存款金额，起始',
        'money_to'   => 'int(optional)    #存款金额，结束',
        'name'       => 'string(optional) #商户名称',
        'page'       => 'int(required)   #页码',
        'page_size'  => 'int(required)    #每页大小',
    ];
    const TYPE        = 'text/json';
    const PARAMs      = [];
    const SCHEMAs     = [
        200 => [
            "id"            => "int",
            "trade_no"      => "string #订单号",
            "ranting"       => "int",
            "agent_name"    => "string",
            "user_name"     => "string",
            "app_id"        => "uint #商户编号",
            "vender_name"   => "string #商户名称",
            "channel_id"    => "int #渠道id",
            "pay_no"        => "string #外部交易号",
            "money"         => "int",
            "coupon_money"  => "int",
            "recharge_time" => "string #交易时间",
            "status"        => "set[pending,failed,paid]",
            "ip"            => "string #存款ip",
            "memo"          => "string #备注",
            "state"         => "set[show,new,auto,online]",
        ]
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run(){
        $pay_channel = $this->request->getParam('pay_channel');
        $pay_scence = $this->request->getParam('pay_scence');
        $trade_no = $this->request->getParam('trade_no');
        $username = $this->request->getParam('user_name');
        $status = $this->request->getParam('status');
//        $ranting = $this->request->getParam('ranting') ? explode(',',$this->request->getParam('ranting')) : false;
        $smoney = $this->request->getParam('money_from');
        $emoney = $this->request->getParam('money_to');
        $stime = $this->request->getParam('date_from');
        $etime = $this->request->getParam('date_to');
        $cstime = $this->request->getParam('create_from');
        $cetime = $this->request->getParam('create_to');
        $page = $this->request->getParam('page',1);
        $size= $this->request->getParam('page_size',20);

        if ($page == 1) {
            $this->redis->set('admin:UnreadNum1', date('Y-m-d H:i:s'));
        }

        $query = \DB::table('funds_deposit as deposit')
            ->leftJoin('user','deposit.user_id','=','user.id')
//            ->leftJoin('user_level as level','user.ranting','=','level.level')
//            ->leftJoin('user_agent as agent','user.id','=','agent.user_id')
            ->leftJoin('admin as admin','deposit.process_uid','=','admin.id')
            ->whereRaw('FIND_IN_SET("online",deposit.state)');

        if($pay_scence){
            $types = \Logic\Shop\Pay::getPayType('type');
            $query->where('deposit.pay_type','=',$types[$pay_scence]);
        }
        $pay_channel && $query->where('deposit.pay_bank_id','=',$pay_channel);
        $trade_no && $query->where('deposit.trade_no','=',$trade_no);
        $username && $query->where('deposit.name','=',$username);
        $status && $query->whereRaw('FIND_IN_SET("'.$status.'",deposit.status)');
        $smoney && $query->where('deposit.money','>=',$smoney);
        $emoney && $query->where('deposit.money','<=',$emoney);
//        $ranting && $query->whereIn('user.ranting',$ranting);
//        $stime && $query->where('deposit.recharge_time','>=',$stime." 00:00:00");
//        $etime && $query->where('deposit.recharge_time','<=',$etime ." 23:59:59");
        $stime && $query->where('deposit.created','>=',$stime." 00:00:00");
        $etime && $query->where('deposit.created','<=',$etime ." 23:59:59");
        $cstime && $query->where('deposit.created','>=',$cstime." 00:00:00");
        $cetime && $query->where('deposit.created','<=',$cetime ." 23:59:59");
        $sum = clone $query;
        $total = $sum->count();
        $data = $query->orderBy('deposit.id','DESC')->forPage($page,$size)->get([
            'deposit.id',
//            'deposit.active_apply',
//            'deposit.active_id',
//            'deposit.active_name',
//            'deposit.active_id_other',
            'deposit.coupon_money',
            'deposit.created',
            'deposit.over_time',
            'deposit.deposit_type',
            'deposit.marks',
            'deposit.memo',
            'deposit.money',
            'deposit.receive_bank_info as channel_name',
            'admin.name as process_uname',
            'deposit.process_time',
            'deposit.recharge_time',
            'deposit.status',
            'deposit.state',
            'deposit.origin',
            \DB::raw("concat(deposit.trade_no,'') as trade_no "),
            'deposit.pay_no',
            'deposit.user_id',
            'deposit.ip',
            'deposit.name as user_name',
//            'level.name as ranting'
//            'vender.name as vender_name',

        ])->toArray();


        $attributes = [
            'total' => $total,'sum' => 0,
            'failed_count' => 0,'failed_sum' => 0,
            'pending_count' => 0,'pending_sum' => 0,
            'refuse_count' => 0,'refuse_sum' => 0,
            'success_count' => 0,'success_sum' => 0,
        ];
        $attributes['cur_sum'] = 0;
        $origins = [0=>'',1=>'PC',2=>'H5',3=>'APP',4=>'APP'];
        foreach ($data as &$val){
            if($val->status == 'paid')
                $val->in_money = $val->money + $val->coupon_money;
            else
                $val->in_money = 0;
                $val->channel_name = json_decode($val->channel_name,true);
            $attributes['cur_sum'] += $val->money;
            $val->active_name = '';
//            $actives = property_exists($val,'active_apply') ? $val->active_apply : '';
//            if($actives){
//                foreach (explode(',',$actives) ?? [] as $active_apply_id){
//                    $active_apply= DB::table('active_apply')->find($active_apply_id);
//                    if($active_apply)
//                        $val->active_name .= " ".$active_apply->active_name .'赠送'.($active_apply->coupon_money/100). ($active_apply->state == 'auto' ? '(自动)' : '(手动)');
//                }
//            }
            $val->origin_str = $origins[$val->origin] ? : '';
            $val->vender_name = $val->channel_name['vender'];
        }
        $attributes['count'] = $total;
        $attributes['size'] = $size;
        $attributes['number'] = $page;
        return $this->lang->set(0,[],$data,$attributes);
    }
};
