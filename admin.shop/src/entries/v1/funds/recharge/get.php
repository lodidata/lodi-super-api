<?php
use Logic\Admin\BaseController;
return new class() extends BaseController{
    const TITLE       = '交易流水(记录)/资金流水--类别与类型';
    const QUERY       = [
        'order_number' => 'string(optional) #订单ID',
        'money' => 'int(optional) #金额：分',
    ];
    const TYPE        = 'text/json';
    const SCHEMAs     = [
        200 => [
            'opt' => 'int #操作1：处理成功，2：已补单，3：失败需要继续添加，4：失败无需继续',
            'msg'  => 'string #错误信息，统一 SUCCESS为成功',
        ]
    ];

    public function run(){
        $ips = \DB::table('pay_site_config')->value('ip');
        if($ips){
            $ips = explode(',',$ips);
        }else{
            $ips = [];
        }
        $ip = \Utils\Client::getIp();
        if(in_array($ip,$ips) || true) {
            $order_number = $this->request->getParam('order_number');
            $money = $this->request->getParam('money');
            $this->pay = new \Logic\Shop\Pay($this->ci);
            //某些第三方我们在订单生成的时间拿不到金额，只有回调的时候才能拿到
            $money && \DB::table('funds_deposit')->where('trade_no','=',$order_number)->update(['money'=>$money]);
            if ($order_number) {
                $deposit = $this->pay->getDepositByOrderId($order_number);
                if ($deposit) {
                    if ($money == $deposit->money || true) {
                        if ($deposit->status == 'pending') {
                            $recharge = new \Logic\Shop\Recharge($this->ci);
                            $result = $recharge->onlineCallBack($deposit);
                            //发送消息
                            if ($result) {
//                                $result['order_no'] = $order_number;
//                                $result['trade_no'] = $order_number;
//                                $result['trade_time'] = date('Y-m-d H:i:s', time());
//                                $recharge->onlinePaySuccessMsg($result, null);
                                return ['opt' => 1, 'msg' => ''];
                            }
                            return ['opt' => 3, 'msg' => ''];
                        }
                        return ['opt' => 2, 'msg' => '客户已补单'];
                    }
                    return ['opt' => 4, 'msg' => '订单金额不一致，请核对'];
                }
                return ['opt' => 4, 'msg' => '查无此订单,请核对'];
            }
            return ['opt' => 4, 'msg' => '数据缺失，请核对'];
        }
        return ['opt' => 4, 'msg' => '请联系添加白名单'];
    }
};
