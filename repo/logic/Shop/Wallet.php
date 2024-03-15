<?php
namespace Logic\Shop;

use Logic\Define\CacheKey;
use Logic\Funds\DealLog;
use Logic\Set\SystemConfig;
use Model\Admin\GameMenu;
use Model\Funds;
use Model\FundsChild;
use Model\Profile;
use Model\UserData;
use Model\TrialUser;
use Model\User;
use Model\TrialFunds;

/**
 * 钱包模块
 */
class Wallet extends \Logic\Logic{
    /**
     * 查询钱包
     * @param $uid
     * @return mixed
     */
    public function getWalletByUid($uid){
        $user = User::where('id', '=', $uid)->select('wallet_id')->first();
        return $this->getWallet($user['wallet_id']);
    }

    /**
     * 查询钱包
     * @param $userId
     * @return mixed
     */
    public function getWallet($userId){
        $user = User::where('id', $userId)->first();

        $primary = Funds::where('id', $user['wallet_id'])
            ->where('status', '=', 'enabled')
            ->selectRaw('id, name, balance, balance_before, freeze_withdraw, freeze_append,currency,freeze_money')
            ->first($user['wallet_id']);

        $rs = $primary->toArray();

        $secondary = FundsChild::where('pid', '=', $primary['id'])
            ->where('status', '=', 'enabled')
            ->selectRaw('id, uuid, name, game_type,balance')
            ->get()
            ->toArray();

        $rs['children'] = array_merge([$primary], $secondary);
        return $rs;
    }

    /**
     * 获取钱包信息
     * @param $userId
     * @return mixed
     */
    public function getInfo($userId){
        $wallet = $this->getWallet($userId);
        // 额度转换开关
        $wallet['transfer'] = 1;
        $wallet["all_balance"] = $wallet['balance'];
        $dml = new \Logic\Wallet\Dml($this->ci);
        $dmlData = $dml->getUserDmlData($userId);
        $wallet["take_balance"] = $dmlData->free_money;
        $wallet["require_bet"] = $dmlData->total_require_bet;
        return $wallet;
    }

    /**
     * 玩家获取钱包信息（require_bet = total_require_bet - total_bet）
     * @param $userId
     * @return mixed
     */
    public function getWalletInfo($userId){
        $wallet = $this->getWallet($userId);
        // 额度转换开关
        $wallet['transfer'] = 1;
        $wallet["all_balance"] = $wallet['balance'] + FundsChild::where('pid',$wallet['id'])->sum('balance');
        $dml = new \Logic\Wallet\Dml($this->ci);
        $dmlData = $dml->getUserDmlData($userId);
        $wallet["take_balance"] = $dmlData->free_money > $wallet["all_balance"] ? $wallet["all_balance"] : $dmlData->free_money;
        $wallet["require_bet"] = $dmlData->total_require_bet > $dmlData->total_bet ? $dmlData->total_require_bet - $dmlData->total_bet : $dmlData->total_require_bet;
        //今日盈亏
        $wallet['today_profit'] = $this->getUserTodayProfit($userId);
        return $wallet;
    }

    public function getUserTodayProfit($uid) {
        $today_profit = $this->redis->get(\Logic\Define\CacheKey::$perfix['userTodayProfit'].$uid);
        if(!$today_profit) {
            $day = date('Y-m-d');
            $types = [
                \Model\FundsDealLog::TYPE_ACTIVITY,
                \Model\FundsDealLog::TYPE_REBET,
                \Model\FundsDealLog::TYPE_AGENT_CHARGES,
                \Model\FundsDealLog::TYPE_REBET_MANUAL,
                \Model\FundsDealLog::TYPE_ACTIVITY_MANUAL,
                \Model\FundsDealLog::TYPE_LEVEL_MANUAL1,
                \Model\FundsDealLog::TYPE_LEVEL_MANUAL2,
                \Model\FundsDealLog::TYPE_LEVEL_MONTHLY,
            ];
            $profit = \DB::table('orders')->where('user_id',$uid)->where('date',$day)->sum('profit');
            $send_money = \Model\FundsDealLog::where('user_id',$uid)->whereIn('deal_type',$types)->where('created','>=',$day)->sum('deal_money');
            $today_profit = intval($profit) + intval($send_money);
            $this->redis->setex(\Logic\Define\CacheKey::$perfix['userTodayProfit'].$uid,30,$today_profit);
        }
        return $today_profit;
    }

    /**
     * 中奖后添加余额，资金流水，结算状态
     *
     * @param int $id ID
     * @param string $name 用户name
     * @param string $orderNumber 订单号
     * @param int|array $money 金额
     * @param int $dealType 交易类型
     */
    public function addMoney($user, $orderNumber, $money, $dealType, $memo = null, $dealDMLMoney = 0){
        if ($this->db->getConnection()->transactionLevel() == 0) {
            throw new \Exception("addMoney 需要开启事务支持", 1);
        }
        if ($dealType == 2) {
            $dealType = \Model\FundsDealLog::TYPE_PAYOUT_LOTTERY; //派彩
        } else if ($dealType == 3) {
            $dealType = \Model\FundsDealLog::TYPE_SALES; //销售返点
        } else if ($dealType == 4) {
            $dealType = \Model\FundsDealLog::TYPE_REBET; //反水优惠
        } else {
            $dealType = $dealType;
        }
        //主钱包加钱
        if ($money) {
            $this->crease($user['wallet_id'], $money);
        }
        $balance = Funds::where('id', $user['wallet_id'])->value('balance');
        DealLog::addDealLog($user['id'], $user['name'], $balance, $orderNumber, $money, $dealType,$memo,$dealDMLMoney);
        if (in_array($dealType, [101, 102, 106])) {
            $this->luckycode($user['id']);
        }
    }

    /**
     * 修改钱包金额
     *
     * @param  [type]  $wid  钱包ID
     * @param  [type]  $amount  金额分
     * @param  integer $type 1:主钱包(默认) 2:子钱包
     *
     * @return [type]        [description]
     */
    public function crease($wid, $amount, $type = 1){
        $db = $this->db->getConnection();
        if ($this->db->getConnection()->transactionLevel() == 0) {
            throw new \Exception("crease 需要开启事务支持", 1);
        }
        $where = "balance + $amount >= 0";
        $res = \DB::table('funds')->where('id', $wid)->whereRaw($where)
            ->update([
                'balance_before' => $db->raw('balance'),
                'balance' => $db->raw("balance + $amount"),
            ]);
        return $res;
    }
}