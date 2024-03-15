<?php

namespace Logic\Recharge\Traits;

use Model\FundsDealLog;
use Model\FundsDealManual;
use Model\FundsTrialDealLog;

trait RechargeTzHandRecharge {

    /**
     * 手动增加余额
     *
     * @param int $userId 用户id
     * @param int $amount 金额
     * @param int $withdrawBet 取款条件
     * @param string $memo 备注
     * @param int $currentUserId 当前用户id
     * @param int $dealType 交易类型
     *
     * @return bool
     */
    public function tzHandRecharge(
        int $userId,
        int $amount,
        int $withdrawBet,
        string $memo,
        int $currentUserId,
        int $dealType = FundsDealLog::TYPE_ADDMONEY_MANUAL
    ) {
        $userType = 1;
        $user = \Model\User::where('id', $userId)
                           ->first();

        if (!$memo) {
            $memo = '厅主后台手动增加余额';
        }

        try {
            $this->db->getConnection()
                     ->beginTransaction();

            //流水里面添加打码量可提余额等信息
            $dml = new \Logic\Wallet\Dml($this->ci);
            $dmlData = $dml->getUserDmlData((int)$userId, intval($withdrawBet), 2);

            $wallet = new \Logic\Wallet\Wallet($this->ci);

            // 锁定钱包            
            $oldFunds = \Model\Funds::where('id', $user['wallet_id'])
                                    ->lockForUpdate()
                                    ->first();

            // 加钱
            $wallet->crease($user['wallet_id'], $amount);

            $funds = \Model\Funds::where('id', $user['wallet_id'])
                                 ->first();

            $tradeNo = date('YmdHis') . rand(pow(10, 3), pow(10, 4) - 1);

            if (isset($GLOBALS['playLoad'])) {
                $admin_id = $GLOBALS['playLoad']['uid'];
                $admin_name = $GLOBALS['playLoad']['nick'];
            } else {
                $admin_id = 0;
                $admin_name = '';
            }

            // 增加资金流水
            FundsDealLog::create([
                'user_id'           => $userId,
                'user_type'         => 1,
                'username'          => $user['name'],
                'order_number'      => $tradeNo,
                'deal_type'         => $dealType,
                'deal_category'     => FundsDealLog::CATEGORY_INCOME,
                'deal_money'        => $amount,
                'balance'           => intval($funds['balance']),
                'memo'              => '厅主后台手动增加余额',
                'wallet_type'       => FundsDealLog::WALLET_TYPE_PRIMARY,
                'total_bet'         => $dmlData->total_bet,
                'withdraw_bet'      => $withdrawBet,
                'total_require_bet' => $dmlData->total_require_bet,
                'free_money'        => $dmlData->free_money,

                'admin_id'   => $admin_id,
                'admin_user' => $admin_name,
            ]);

            // 增加手动入款记录
            FundsDealManual::create([
                'user_id'       => $userId,
                'username'      => $user['name'],
                'user_type'     => $userType,
                'type'          => 5,
                'trade_no'      => $tradeNo,
                'operator_type' => 1,
                'front_money'   => intval($oldFunds['balance']),
                'money'         => $amount,
                'balance'       => intval($funds['balance']),
                'admin_uid'     => $currentUserId,
                'wallet_type'   => 1,
                'memo'          => $memo,
                'withdraw_bet'  => $withdrawBet,
            ]);

            $this->db->getConnection()
                     ->commit();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('tzHandRecharge 出错:' . $e->getMessage(), compact('userId', 'amount', 'withdrawBet', 'memo', 'currentUserId'));
            $this->db->getConnection()
                     ->rollback();

            return false;
        }
    }

    /**
     * 手动增加试玩余额
     *
     * @param int $userId 用户id
     * @param int $amount 金额
     * @param int $withdrawBet 取款条件
     * @param string $memo 备注
     * @param int $currentUserId 当前用户id
     * @param int $dealType 交易类型
     *
     * @return bool
     */
    public function tzTrialHandRecharge(
        int $userId,
        int $amount,
        int $withdrawBet,
        string $memo,
        int $currentUserId,
        int $dealType = FundsTrialDealLog::TYPE_ADDMONEY_MANUAL
    ) {
        $user = \Model\TrialUser::where('id', $userId)->first();

        try {
            $this->db->getConnection()->beginTransaction();
            $wallet = new \Logic\Wallet\Wallet($this->ci);
            // 锁定钱包
            $oldFunds = \Model\TrialFunds::where('id', $user['wallet_id'])->lockForUpdate()->first();

            // 加钱
            $wallet->Trialcrease($user['wallet_id'], $amount);

            $funds = \Model\TrialUser::where('id', $user['wallet_id'])->first();

            $tradeNo = date('YmdHis') . rand(pow(10, 3), pow(10, 4) - 1);

            // 增加资金流水
            FundsTrialDealLog::create([
                'user_id'           => $userId,
                'username'          => $user['name'],
                'order_number'      => $tradeNo,
                'deal_type'         => $dealType,
                'deal_category'     => FundsDealLog::CATEGORY_INCOME,
                'deal_money'        => $amount,
                'balance'           => intval($funds['balance']),
                'memo'              => $memo,
            ]);

            $this->db->getConnection()->commit();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('tzHandRecharge 出错:' . $e->getMessage(), compact('userId', 'amount', 'withdrawBet', 'memo', 'currentUserId'));
            $this->db->getConnection()->rollback();
            return false;
        }
    }
}

