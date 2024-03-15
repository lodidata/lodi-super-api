<?php
use Logic\Admin\BaseController;
return new class() extends BaseController {
    const TITLE = "第三方支付的存款详情";
    const TYPE = "text/json";
    const QUERY = [
       "id" => "int(required) #第三方支付ID",
       "date_from" => "string(optional) #起始日期",
       "date_to" => "string(optional) #结束日期",
       "page" => "int(optional) #页码",
       "page_size" => "int(optional) #每页大小"
   ];
    const SCHEMAs = [
       200 => [
           "pay_no " => "string #订单号",
           "money" => "string #存款金额",
           "recharge_time" => "string  #交易时间",
           "user_name" => "string  #用户名称",
           "level_name" => "string  #用户等级",
           "memo" => "string  #备注"
       ]
   ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run($id= ''){
        $page = $this->request->getParam('page') ?? 1;
        $size= $this->request->getParam('page_size') ?? 20;
        $stime = $this->request->getParam('start_time');
        $etime = $this->request->getParam('end_time');
        $i = $this->request->getParam('id');
        if($i)
            $id = $i;

        $deposit = \DB::table('funds_deposit as d')->leftJoin('user','d.user_id','=','user.id')
            ->where('d.pay_bank_id','=', $id)->where('d.status','=','paid');
        if($stime) {
            $deposit->where('d.recharge_time', '>=', $stime);
        }
        if($etime) {
            $deposit->where('d.recharge_time', '<=', $etime.' 23:59:59');
        }
        $sum = clone $deposit;
        $state_data = $sum->first([
            \DB::raw('count(1) as total'),
            \DB::raw('sum(d.money) as totalMoney'),
            \DB::raw('max(d.recharge_time) as lastTime'),
        ]);
        $state_data->size = $size;
        $state_data->number = $page;
        $deposit_data = $deposit->orderBy('d.id','DESC')->forPage($page,$size)->get([
            'd.id',
            'd.created',
            'd.money',
            'd.pay_no',
            'd.recharge_time',
            'd.trade_no',
            'd.recharge_time',
            'd.created',
            'd.memo',
            'd.marks',
            'd.status',
            'd.user_id',
            'user.name as user_name',
        ])->toArray();
        return  $this->lang->set(0, [], $deposit_data, $state_data);
    }
};
