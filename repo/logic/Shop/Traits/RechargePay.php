<?php
namespace Logic\Shop\Traits;
//use Logic\Admin\Message;

trait RechargePay{
    /**
     * 回调修改入款单状态并且加入钱包
     * @param string $ordernumber 入款单号
     */
    public function onlineCallBack($deposit, $adminId=0){
        if ($deposit == null || $deposit->status != 'pending') {
            return false;
        }
        $model     = [
//            'valid_bet'     => 0,
            'process_time'  => date('Y-m-d H:i:s'),
            'recharge_time' => date('Y-m-d H:i:s'),
            'status'        => 'paid',
            'memo'          =>  "在线充值". ($adminId ? '-补单' : '-回调'),
            'process_uid'  => $adminId
        ];

        // 将充值金额转入钱包
//        $amount = $deposit->money + $deposit->coupon_money;// 充值金额=本金+优惠
//        $user  = (new \Logic\User\User($this->ci))->getInfo($deposit->user_id);
        $user = \DB::table('user')->where('id', $deposit->user_id)
            ->select(['name as user_name', 'name', 'wallet_id','last_login'])->first();
        $user = (array)$user;
        $resData = $this->rechargeMoney($model, $deposit, $user, $deposit->money);
        return $resData;

    }

    public function onlinePaySuccessMsg($order,$pay_channel, $pay_way= "没有返回支付方式"){
        $message = new Message($this->ci);
        $content="尊敬的 ".$order['name']." ，您好！你已成功充值 ".$order['money']." 元";
        $insertId = $this->messageAddByMan('充值到账',$order['name'],$content);
        $message->messagePublish($insertId);

        if($order['coupon'] > 0) {
            $content = "尊敬的 " . $order['name'] . " ，您好！充值赠送 " . $order['coupon'] . " 元已到账";
            $insertId = $this->messageAddByMan('充值赠送', $order['name'], $content);
            $message->messagePublish($insertId);
        }
        $this->noticeInfo($pay_channel,$pay_way,$order['order_no'],$order['user_id'],$order['trade_no'],$order['money']*100,$order['trade_time']);
    }

    public function messageAddByMan($title,$user,$content){
        $messageModel = new \Model\Admin\Message();
        $messageModel->send_type = 3;
        $messageModel->title = $title ?? '消息';
        $messageModel->admin_uid = 0;
        $messageModel->recipient = $user;
        $messageModel->type = '2';
        $messageModel->content = $content;
        $messageModel->admin_name = 0;
        $messageModel->save();
        return $messageModel->id;
    }

    /**
     * 支付交易信息
     *
     * @param string $platform
     * @param string $pay_scene
     * @param string $trade_no
     * @param int $user_id
     * @param string $trans_id
     * @param int $money
     * @param string $pay_time
     */
    public function noticeInfo($platform, $pay_scene, $trade_no, $user_id, $trans_id, $money, $pay_time) {
        global $app;

        $data['platform'] = $platform;
        $data['pay_scene'] = $pay_scene;
        $data['user_id'] = $user_id;
        $data['trade_no'] = $trade_no;
        $data['trans_id'] = $trans_id;
        $data['money'] = $money;
        $data['pay_time'] = $pay_time ?? date('Y-m-d H:i:s');
        $data['created'] = date('Y-m-d H:i:s');

        $app->getContainer()->db->getConnection()
            ->table('funds_pay_callback')
            ->insert($data);
    }
}