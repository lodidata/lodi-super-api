<?php
use Utils\Shop\Action;

return new class extends Action {
    const TITLE = "GET 查询 充值记录";
    const TYPE = "text/json";
    const QUERY = [
       "start_time" => "int() #查询开始日期",
       "end_time" => "int() #查询结束日期",
       "page" => "int() #当前第几页",
       "page_size" => "int() #每页数目",
   ];
    const SCHEMAs = [
       200 => [
           'id' => '',
          'user_id' => '存款用户ID',
          'trade_no' => '订单号',
          'money' => '存入金额',
          'coupon_money' => '优惠金额',
          'pay_type' => '支付类型(线上(1网银支付)线下(1银行转账)2支付宝3微信4QQ钱包5JD支付)',
          'pay_no' => '支付号(外部/第三方交易号)',
          'name' => '存款人姓名',
          'recharge_time' => '交易时间',
          'pay_bank_info' => '支付账户详情，用以线下支付。json字符串( {"bank":"银行名称", "name":"帐号名", "card":"卡号"} )',
          'receive_bank_account_id' => '目标账户：线上-第三方账户。线下-银行账户',
          'receive_bank_info' => '目标账户详情, json字符串，线上：{"id":"渠道ID",“pay":"渠道名", "vender":"支付接口名"}，线下： {"id":"银行ID","bank":"银行名称", "name":"帐号名", "card":"卡号"} ',
          'status' => '#paid(已支付), pending(待支付), failed(支付失败), canceled(已取消),rejected:已拒绝)',
          'created' => '#创建时间',
       ]
   ];

    public function run() {
        $verify = $this->auth->verfiyToken();
        if (!$verify->allowNext()) {
            return $verify;
        }
        $page = $this->request->getParam('page', $this->page);
        $pageSize = $this->request->getParam('page_size', $this->page_size);
        $stime = $this->request->getParam('start_time');
        $etime = $this->request->getParam('end_time');

        $userId = $this->auth->getUserId();
        $query = \DB::table('funds_deposit')->where('user_id', $userId)->where('status', 'paid');
        $stime && $query->where('created','>=', $stime);
        $etime && $query->where('created','<=',$etime.' 23:59:59');
        $total = $query->count();
        $data = $query->forPage($page, $pageSize)->orderBy('id','desc')->get()->toArray();
//        1 网银支付) 线下(1银行转账) 2 支付宝 3 微信 4 QQ钱包 5 JD支付
        $types = [1 => '银联', 2 => '支付宝', 3 => '微信', 4 => '钱包', 5 => 'JD'];
        foreach ($data as &$val){
            if(strpos($val->state,'online') === false)
                $val->online = false;
            else
                $val->online = true;
            $val->pay_type_str = $types[$val->pay_type] ?? '手动';
        }
        return $this->lang->set(0, [], $data, [
            'number' => $page, 'size' => $pageSize, 'total' => $total
        ]);
    }
};