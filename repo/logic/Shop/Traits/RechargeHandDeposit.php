<?php
namespace Logic\Shop\Traits;
//use Model\Bank;
//use Model\FundsDeposit;
//use Logic\Activity\Activity;

trait RechargeHandDeposit {
    /**
     * 线下入款申请
     *
     * @param int    $userId 用户id
     * @param int    $money 存款金额
     * @param string $name 存款人姓名
     * @param string $trans_time 转账时间
     * @param int    $type 存款方式
     * @param int    $pay_type 支付类型
     * @param int    $payBankId 存款银行
     * @param int    $receiveBankAccountId 收款账户id
     * @param string $ip 存款ip
     * @param bool   $needPre 是否需要优惠
     * @param int    $withdrawbet 打码量
     * @param int    $couponmoney 优惠
     * @return int 入款单id
     */
    public function handDeposit($userId, $money, $name, $trans_time, $type, $payBankId,
        $receiveBankAccountId, $ip, $needPre, $userCard = "", $pay_type = "") {
        // 获取付款银行信息
        if($payBankId){
            $payBank  = \DB::table('bank')->where('id', $payBankId)->first();
            $payBank = (array)$payBank;
            $bankInfo = json_encode(['bank' => $payBank['name'], 'name' => $name,'card'=>"$userCard"], JSON_UNESCAPED_UNICODE);
        }else {
            $paynames = [2 => '支付宝', 3 => '微信', 4 => 'QQ钱包', 5 => '京东支付'];
            $payname = $paynames[$pay_type] ?? '';
            $bankInfo = json_encode(['bank' => $payname, 'name' => $name,'card'=>"$userCard"], JSON_UNESCAPED_UNICODE);
        }
        // 获取收款银行信息
        $receiveBankAccount = \DB::table('bank_account')
            ->leftJoin('bank', 'bank_account.bank_id', '=', 'bank.id')
            ->where('bank_account.id', '=', $receiveBankAccountId)
            ->first(['bank_account.bank_id','bank_account.name','bank_account.card',
                \DB::raw('bank.name AS bank_name')]);
        $receiveBankInfo    = json_encode([
            'id'          => $receiveBankAccount->bank_id,
            'bank'        => $receiveBankAccount->bank_name,
            'accountname' => $receiveBankAccount->name,
            'card'        => $receiveBankAccount->card // fixme，这里不需要加密，由下面的addDeposit()函数加密
        ], JSON_UNESCAPED_UNICODE);
        $tradeNo            = date("YmdHis").rand(pow(10, 3), pow(10, 4) - 1);
        $time               = time();
        //fixme 增加存款校验码
        $marks = '';
        //获取来源
        $origins = ['pc' => 1, 'h5' => 2, 'ios' => 3, 'android' => 4];
        $origin = isset($this->request->getHeaders()['HTTP_PL']) && is_array($this->request->getHeaders()['HTTP_PL']) ? current($this->request->getHeaders()['HTTP_PL']) : '';
        $model = [
            'trade_no'                => $tradeNo,
            'user_id'                 => $userId,
            'money'                   => $money,
            'name'                    => $name,
            'recharge_time'           => $trans_time,
            'deposit_type'            => $type,
            'pay_type'                => $pay_type,
            'pay_bank_id'             => $payBankId,
            'receive_bank_account_id' => $receiveBankAccountId,
            'ip'                      => $ip,
            'origin'                  => isset($origins[$origin]) ? $origins[$origin] : 0,
            'pay_bank_info'           => $bankInfo,
            'receive_bank_info'       => $receiveBankInfo,
            'marks'                   => $marks,
            'memo'                   => '线下入款',
            'created'                 => date('Y-m-d H:i:s', $time)
        ];
        // 获取用户信息
//        $user = \Model\User::find($userId)->toArray();
        $user = \DB::table('user')->where('id', $userId)->first();

        $result = \DB::table('funds_deposit')->insertGetId($model);
//        $active = new Activity($this->ci);
//        $activeData = $active->rechargeActive($userId, $user, $money, $needPre, 'offline', $result->id);
//        $update['coupon_money'] = $activeData['coupon_money'];
//        $update['active_apply'] = $activeData['activeApply'];
//        $update['withdraw_bet'] = $activeData['withdraw_bet'];
//        $update['coupon_withdraw_bet'] = $activeData['coupon_withdraw_bet'];
//        $update['active_id']    = $activeData['activeArr'][0] ?? 0;
//        $update['state']    = $activeData['state'] ?? '';
//        $update['active_id_other']    = $activeData['activeArr'][1] ?? 0;
//        FundsDeposit::where('id', $result->id)->update($update);
//         写入日志555
        $logs = [
            'user_id' => $userId,
            'name' => $user->name,
            'log_value' => '线下入款申请',
            'log_type' => 3,
            'status' => $result ? 1 : 0,
            'log_ip' => \Utils\Client::getIp(),
            'domain' => str_replace(['https', 'http'], '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''),
        ];
        \DB::table('user_logs')->insert($logs);
        return $result;
    }
}