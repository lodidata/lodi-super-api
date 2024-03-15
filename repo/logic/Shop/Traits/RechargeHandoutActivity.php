<?php
namespace Logic\Recharge\Traits;

trait RechargeHandoutActivity {
    /**
     * 手动发放优惠
     *
     * @param int    $userId 发给用户id
     * @param int    $couponMoney 优惠金额
     * @param int    $withdrawBet 取款条件
     * @param int    $activeId 优惠id
     * @param string $memo
     * @param int    $currentUserId 当前用户id
     */
    public function handoutActivity(
        $userId,
        $couponMoney,
        $withdrawBet,
        $activeId,
        $memo,
        $currentUserId
    ) {

        try {
            $this->db->getConnection()->beginTransaction();
            $user = \Model\User::where('id', $userId)->first();
            // 锁定钱包
            \Model\Funds::where('id', $user['wallet_id'])->lockForUpdate()->first();

            // 增加优惠入款单据
            \Model\ActiveApply::create([
                'user_id'          => $userId,
                'user_name'        => $user['name'],
                'coupon_money'     => $couponMoney,
                'withdraw_require' => $withdrawBet,
                'active_id'        => $activeId,
                'memo'             => $memo,
                'status'           => 'pass',
                'state'            => 'auto'
            ]);

            $wallet = new \Logic\Wallet\Wallet($this->ci);
            
            // 加钱
            $wallet->crease($user['wallet_id'], $couponMoney);

            $funds = \Model\Funds::where('id', $user['wallet_id'])->first();

            //添加打码量记录
            $dml = new \Model\Dml();
            $dml->addDml($userId,$withdrawBet,$couponMoney,'活动赠送添加打码量');
            //流水里面添加打码量可提余额等信息
            $dml = new \Logic\Wallet\Dml($this->ci);
            $dmlData =$dml->getUserDmlData((int)$userId,(int)$withdrawBet,2);

            \Model\FundsDealLog::create([
                "user_id" => $userId,
                "user_type" => 1,
                "username" => $user['name'],
                "deal_type" => \Model\FundsDealLog::TYPE_ACTIVITY_MANUAL,
                "deal_category" => \Model\FundsDealLog::CATEGORY_INCOME,
                "deal_money" => $couponMoney,
                'order_number'=>\Model\LotteryOrder::generateOrderNumber(),
                "balance" => intval($funds['balance']),
                "memo" => $memo,
                "wallet_type" => \Model\FundsDealLog::WALLET_TYPE_PRIMARY,
                'total_bet'=>$dmlData->total_bet,
                'withdraw_bet'=> $withdrawBet,
                'total_require_bet'=>$dmlData->total_require_bet,
                'free_money'=>$dmlData->free_money
            ]);
            $this->db->getConnection()->commit();
            $this->logger->info('手动发放优惠 成功', [
                'user_id'          => $userId,
                'user_name'        => $user['name'],
                'coupon_money'     => $couponMoney,
                'withdraw_require' => $withdrawBet,
                'active_id'        => $activeId,
                'memo'             => $memo,
                'status'           => 'pass',
                'state'            => 'auto',

                "user_type" => 1,
                "username" => $user['name'],
                "deal_type" => \Model\FundsDealLog::TYPE_ACTIVITY_MANUAL,
                "deal_category" => \Model\FundsDealLog::CATEGORY_INCOME,
                "deal_money" => $couponMoney,
                "balance" => intval($funds['balance']),
                "memo" => $memo,
                "wallet_type" => \Model\FundsDealLog::WALLET_TYPE_PRIMARY,
                "ip" => \Utils\Client::getIp(),
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('handoutActivity 出错:'.$e->getMessage(), compact('userId', 'couponMoney', 'withdrawBet', 'activeId', 'memo', 'currentUserId'));
            $this->db->getConnection()->rollback();
            return false;
        }
    }

}