<?php
namespace Logic\Shop\Traits;

use Logic\User\User;
//use Model\FundsDeposit;
use Logic\Admin\Message;
//use Logic\Activity\Activity;
use Logic\Shop\Wallet;
//use Model\FundsDealLog;
use Logic\Shop\FundsDealLog;
use lib\exception\BaseException;
trait RechargeDeposit{
    /**
     * 更改线下存款信息
     *
     * @param int   $id 存款ID <br />
     * @param array $offline <br />
     * send_coupon：是否发放优惠，1: 是, 0: 否 <br />
     * send_memo：是否发送备注，1: 是, 0: 否<br />
     * memo：备注<br />
     * process_uid：处理人<br />
     * status：状态，1: 通过， 2: 拒绝<br />
     * @return bool
     */
    public function updateOffline($id, $offline){
        if (isset($offline['status'])) {
            // 无效存款无法通过
            $deposit = \DB::table('funds_deposit')->where('status','pending')->find($id);//这里查询需要加where status = pending,防止通过或拒绝的订单再次修改
//            $user  = (new \Logic\User\User($this->ci))->getInfo($deposit->user_id);
            $user  = (array)\DB::table('user')->find($deposit->user_id);
            $userName = $user['name'];
            if (!$deposit) {
                $newResponse = createRsponse($this->response, 400, 10550, '无效存款或已处理！');
                throw new BaseException($this->request,$newResponse);
            }
//            $message = new Message($this->ci);
            // 通过
            if ($offline['status'] == 1) {
                $send_coupon = $offline['send_coupon'] == 1;
                //判断是否禁止优惠
//                $auth_status = \DB::table('user')->where('id',$deposit->user_id)->value('auth_status');
//                if(strpos($auth_status,'refuse_sale'))
//                    $send_coupon = false;
                //通过
                $rs = $this->passDeposit($offline['process_uid'], $send_coupon, $offline['memo'] ?? '', $offline['send_memo'], $deposit);//存入

//                if($rs){
//                    $content="尊敬的 ".$userName." ，您好！你于{$deposit->created}充值{$rs['money']}元已到账";
//                    $insertId = $this->messageAddByMan('充值到账',$userName,$content);
//                    $message->messagePublish($insertId);
//
//                    $actives = [];
//
//                    $active_apply = !empty($deposit->active_apply) ? $deposit->active_apply : '';
//                    $actives = explode(',',$active_apply);
//                    if($send_coupon && $rs['coupon'] > 0) {
//                        FundsDeposit::where('user_id',$deposit->user_id)->where('status','pending')->update(['active_apply'=>'','coupon_money'=>0]);
//                        $content="尊敬的 ".$userName." ，您好！你于{$deposit->created}充值参与充值活动赠送{$rs['coupon']}元已到账";
//                        $insertId = $this->messageAddByMan('充值赠送', $userName, $content);
//                        $message->messagePublish($insertId);
//                    }else{
//                        if(count($actives))
//                            \DB::table('active_apply')->where('trade_id',$id)->where('user_id',$deposit->user_id)
//                                ->whereIn('active_id',$actives)->update(['status'=>'rejected']);
//                    }
//                }
            } elseif ($offline['status'] == 2) {
                // 拒绝
                $rs = $this->refuseDeposit($offline['process_uid'], $offline['memo'] ?? '', $offline['send_memo'],$deposit);
//                $reson = $offline['memo'];
//                $deposit->money = $deposit->money /100;
//                $content="尊敬的用户，你于{$deposit->created}充值{$deposit->money}元审核被拒。如有疑问，请联系在线客服";
//                if($reson){
//                    $content="尊敬的用户，你于{$deposit->created}充值{$deposit->money}元审核被拒,拒绝原因：{$reson}。如有疑问，请联系在线客服";
//                }
//                $insertId = $this->messageAddByMan('充值失败', $userName, $content);
//                $message->messagePublish($insertId);
            }
            return $rs;
        }
    }

    /**
     * 通过线下入款单
     *
     * @param int    $currentUserId 当前用户id
     * @param bool   $sendCoupon 是否发送优惠
     * @param string $memo 备注
     * @param bool   $sendMemo 是否发送备注
     * @param array/object    $deposit 入款单详情
     * @return bool
     */
    public function passDeposit($currentUserId, $sendCoupon = true, $memo = '', $sendMemo = false, $deposit) {
        if ($deposit->status != 'pending') {
            return false;
        }
        $valid_bet      = 0;
        $state = $sendCoupon ? 'send_coupon,' : '';
        $state .= $sendMemo ? 'send_memo' : '';
        $state = "CONCAT(state,',{$state}')";
        $model  = [
//            'valid_bet'     => $valid_bet,
            'process_time'  => date('Y-m-d H:i:s'),
            'recharge_time' => date('Y-m-d H:i:s'),
            'process_uid'   => $currentUserId,
            'updated_uid'   => $currentUserId,
            'status'        => 'paid',
            'state'        => \DB::raw($state),
            'memo'          => $memo ?? '',
        ];
        $model['marks'] = '';
        // 将充值金额转入钱包
//        $deposit->coupon_money = $sendCoupon ? intval($deposit->coupon_money) : 0;
//        $amount = $deposit->money + $deposit->coupon_money;// 充值金额=本金+优惠
//        $user  = (new \Logic\User\User($this->ci))->getInfo($deposit->user_id);
        $user = \DB::table('user')->where('id', $deposit->user_id)
            ->select(['name as user_name', 'name', 'wallet_id','last_login'])->first();
        $user = (array)$user;
        $re = $this->rechargeMoney($model,$deposit,$user,$deposit->money,false, $sendCoupon);
        return $re;
    }

    /**
     * 拒绝线下入款单
     *
     * @param int    $currentUserId 当前用户id
     * @param string $memo 备注
     * @param bool   $sendMemo 是否发送备注
     * @param object   $deposit 是否发送备注
     * @return bool
     */
    public function refuseDeposit($currentUserId, $memo = '', $sendMemo = false, $deposit){
        try {
            $this->db->getConnection()->beginTransaction();
            $deposit = \DB::table('funds_deposit')->where('id', $deposit->id)->lockForUpdate()->first();
            if ($deposit->status != 'pending') {
                $this->db->getConnection()->rollback();
                throw new \Exception("订单已审核:".$deposit->status);
            }
            $state = $sendMemo ? 'send_memo' : '';
            $state = "CONCAT(state,',{$state}')";
            $model          = [
                'process_time'  => date('Y-m-d H:i:s'),
                'recharge_time' => date('Y-m-d H:i:s'),
                'process_uid'   => $currentUserId,
                'updated_uid'   => $currentUserId,
                'status'        => 'rejected',
                'state'     => \DB::raw($state),
                'memo'          => $memo
            ];
            $model['marks'] = '';
            \DB::table('funds_deposit')->where('id',$deposit->id)->update($model);
            $this->db->getConnection()->commit();
        } catch (\Exception $e) {
            $this->db->getConnection()->rollback();
            return false;
        }
        return true;
    }

    /**
     * 充值到账，操作钱包
     *
     * @param array    $model 充值更新数据
     * @param object    $deposit    当前充值信息
     * @param array    $user  用户信息
     * @param int  $amount  充值金额+优惠金额
     * @return bool
     */
    public function rechargeMoney($model, $deposit, $user, $amount, $online = true, $sendCoupon = true){
        try {
            $this->db->getConnection()->beginTransaction();
            $deposit = \DB::table('funds_deposit')->where('id', $deposit->id)->lockForUpdate()->first();
            if ($deposit->status != 'pending') {
                $this->db->getConnection()->rollback();
                throw new \Exception("订单已审核:".$deposit->status);
            }

            $deposit->coupon_money = 0;
            //更新该用户当天其他未支付充值订单的活动金额和ID  以防实际支付金额与订单金额有出入，所以重新计算对应的
//            if($sendCoupon && $deposit->active_apply){
//                $user['id'] = $deposit->user_id;
//                $active = new Activity($this->ci);
//                $pay_way = $online ? 'online' : 'offline';
//                $tmp = explode(',',$deposit->active_apply);
//                $needActiveIds = implode(',',\DB::table('active_apply')->whereIn('id',$tmp)->pluck('active_id')->toArray());
//                $activeData = $active->rechargeActive($deposit->user_id, $user, $deposit->money, $needActiveIds, $pay_way, $deposit->id);
//                $deposit->active_apply = $model['active_apply'] = $activeData['activeApply'];
//                $deposit->coupon_money = $model['coupon_money'] = $activeData['coupon_money'] ?? 0;
//                $deposit->withdraw_bet = $model['withdraw_bet'] = $activeData['withdraw_bet'] ?? 0;
//                $deposit->coupon_withdraw_bet = $model['coupon_withdraw_bet'] = $activeData['coupon_withdraw_bet'] ?? 0;
//                if (isset($activeData['state']) && $activeData['state'])
//                    $model['state'] = $pay_way.',' . $activeData['state'];
//                $this->updateDepositActives($deposit,$activeData['state'],$activeData['today_state']);
//            }

            //对应充值通道活动优惠
            //赠送活动规则switch（1开，0关）type（1首次，0不限即每次）
            //{"switch":"1","type":"1","","recharge":"10000","send":20","max_send":"50000","send_dml":"200"}
//            $pass_active_rule = json_decode($deposit->passageway_active,true);
//            $pass_active_money = 0;
//            $pass_active_money_dml = 0;
//            if(is_array($pass_active_rule) && $pass_active_rule['switch'] == 1) {
//                switch ($pass_active_rule['type']) {
//                    case 1:
//                        $first = FundsDeposit::where('user_id',$deposit->user_id)
//                            ->where('status','paid')
//                            ->where('receive_bank_account_id',$deposit->receive_bank_account_id)
//                            ->where('created','>=',date('Y-m-d'))
//                            ->where('created','<=',date('Y-m-d 23:59:59'))->value('id');
//                        if(!$first && $deposit->money >= $pass_active_rule['recharge']) {
//                            $pass_active_money = $deposit->money * $pass_active_rule['send']/100;
//                        }
//                        break;
//                    case 0:
//                        if($deposit->money >= $pass_active_rule['recharge']) {
//                            $pass_active_money = $deposit->money * $pass_active_rule['send']/100;
//                        }
//                        break;
//                }
//                $pass_active_money = $pass_active_money > $pass_active_rule['max_send'] ? $pass_active_rule['max_send'] : $pass_active_money ;
//                $pass_active_rule['send_dml'] = isset($pass_active_rule['send_dml']) ? $pass_active_rule['send_dml'] : 0;
//                $pass_active_money_dml = $pass_active_money * $pass_active_rule['send_dml'] / 100;
//            }

            //添加打码量记录
//            $dmllog = new \Model\Dml();
//            $dmllog->addDml($deposit->user_id,$deposit->withdraw_bet,$deposit->money,'充值添加打码量');
//            //添加打码量可提余额等信息  打码量信息必须 在  增加金额之前
//            $dml = new \Logic\Wallet\Dml($this->ci);
//            $dmlData =$dml->getUserDmlData((int)$deposit->user_id,(int)$deposit->withdraw_bet,2);

            // 锁定钱包
            \DB::table('funds')->where('id', $user['wallet_id'])->lockForUpdate()->first();
//            $amount = $amount + $deposit->coupon_money + $pass_active_money;
            $amount = $amount + $deposit->coupon_money;
            (new Wallet($this->ci))->crease($user['wallet_id'], $amount);

            $money = \DB::table('funds')->where('id','=', $user['wallet_id'])->value('balance');

            //修改用户  首充时间
            \DB::table('user')->where('id',$deposit->user_id)->whereRaw('first_recharge_time is NULL')->update(['first_recharge_time'=>date('Y-m-d H:i:s')]);
            // 修改入款单状态
            \DB::table('funds_deposit')->where('id',$deposit->id)->update($model);
            $funGateway = array(
                'pay_no' => $deposit->pay_no,
                'status' => 'finished',
                'inner_status' => 'finished',
                'notify_time' => date('Y-m-d H:i:s')
            );
            \DB::table('funds_gateway')->where('pay_no','=', $deposit->pay_no)->update($funGateway);

            //添加存款总笔数，总金额
            \DB::table('user_data')->where('user_id',$deposit->user_id)->increment('deposit_amount', $deposit->money, ['deposit_num'=>\DB::raw('deposit_num + 1')]);
            if(isset($GLOBALS['playLoad'])) {
                $admin_id = $GLOBALS['playLoad']['uid'];
                $admin_name = $GLOBALS['playLoad']['nick'];
            }else {
                $admin_id = 0;
                $admin_name = '';
            }

            // 增加资金流水
            $dealData = array(
                "user_id" => $deposit->user_id,
                "username" => $user['user_name'],
                "order_number" => $deposit->trade_no,
                "deal_type" => $online ? FundsDealLog::TYPE_INCOME_ONLINE : FundsDealLog::TYPE_INCOME_OFFLINE,
                "deal_category" => FundsDealLog::CATEGORY_INCOME,
                "deal_money" => $deposit->money,
                "balance" => $money - $deposit->coupon_money,   //该条交易流水  操作后余额应是在优惠金额之后
                "memo" => $online ? "在线充值" : "线下入款充值",
//                "wallet_type" => 1,
//                'total_bet'=>$dmlData->total_bet,
//                'withdraw_bet'=> $deposit->withdraw_bet,
//                'total_require_bet'=>$dmlData->total_require_bet,
//                'free_money'=>$dmlData->free_money,
                'admin_user'=>$admin_name,
                'admin_id'=>$admin_id,
            );

            $dealLogId = FundsDealLog::create($dealData);
//            if ($deposit->coupon_money > 0) {
//                $dmllog->addDml($deposit->user_id,$deposit->coupon_withdraw_bet,$deposit->coupon_money,'充值活动赠送添加打码量');
//                $dmlData =$dml->getUserDmlData((int)$deposit->user_id,(int)$deposit->coupon_withdraw_bet,2);
//                $dealData['deal_type'] = FundsDealLog::TYPE_ACTIVITY;
//                $dealData['deal_category'] = FundsDealLog::CATEGORY_INCOME;
//                $dealData['balance'] = $money;
//                $dealData['deal_money'] = $deposit->coupon_money;
//                $dealData['withdraw_bet'] = $deposit->coupon_withdraw_bet;
//                $dealData['total_require_bet'] = $dmlData->total_require_bet;
//                $dealData['free_money'] = $dmlData->free_money;
//                $dealData['total_bet'] = $dmlData->total_bet;
//                $dealData['admin_user'] = $admin_name;
//                $dealData['admin_id'] = $admin_id;
//                $dealData['memo'] = '充值活动赠送';
//                FundsDealLog::create($dealData);
//            }

//            if ($pass_active_money > 0) {
//                if($pass_active_money_dml) {
//                    $dmllog->addDml($deposit->user_id, $pass_active_money_dml, $deposit->money, '充值渠道赠送金额添加打码量');
//                    $dmlData =$dml->getUserDmlData((int)$deposit->user_id,(int)$pass_active_money_dml,2);
//                }
//                $dealData['deal_type'] = FundsDealLog::TYPE_ACTIVITY;
//                $dealData['deal_category'] = FundsDealLog::CATEGORY_INCOME;
//                $dealData['balance'] = $money;
//                $dealData['deal_money'] = $pass_active_money;
//                $dealData['withdraw_bet'] = $pass_active_money_dml;
//                $dealData['total_require_bet'] = $dmlData->total_require_bet;
//                $dealData['free_money'] = $dmlData->free_money;
//                $dealData['total_bet'] = $dmlData->total_bet;
//                $dealData['admin_user'] = $admin_name;
//                $dealData['admin_id'] = $admin_id;
//                $dealData['memo'] = '通道充值活动赠送'.$pass_active_rule['send'].'%';
//                FundsDealLog::create($dealData);
//            }

            // 修改入款单状态
//            $model['coupon_money'] = $deposit->coupon_money;
//            \DB::table('funds_deposit')->where('id',$deposit->id)->update($model);
            //更改其他订单优惠
//            $date = date('Y-m-d');
//            \DB::table('funds_deposit')->where('user_id',$deposit->user_id)->whereRaw("status != 'paid'")->whereRaw("created >='$date'")->whereRaw("created <= '$date 23:59:59'")->update(['coupon_money'=>0,'active_apply'=>'']);
            $funGateway = array(
                'pay_no' => $deposit->pay_no,
                'status' => 'finished',
                'inner_status' => 'finished',
                'notify_time' => date('Y-m-d H:i:s')
            );
            \DB::table('funds_gateway')->where('pay_no','=',$deposit->pay_no)->update($funGateway);

            $resData = [
                'log_id' => $dealLogId,
                'user_id' => $deposit->user_id,
                'name' => $user['user_name'],
                'money' => $deposit->money / 100,
                'coupon' => $deposit->coupon_money / 100
            ];
//            //赠送转卡彩金
//            if(!$online){
//                $user['id'] = $deposit->user_id;
//                (new User($this->ci))->sendTransferHandsel($user,$deposit->trade_no,$deposit->money);
//            }
//
//            //幸运轮盘充值赠送免费抽奖次数
//            if($resData){
//                $wallet = new \Logic\Wallet\Wallet($this->ci);
//                $wallet->luckycode($deposit->user_id);
//            }

            $this->db->getConnection()->commit();
            //用户层级分层
//            \Utils\MQServer::send('user_level_upgrade', ['user_id' => $deposit->user_id]);
            return $resData;
        }catch (\Exception $e) {
            $this->db->getConnection()->rollback();
            //throw $e;
            return false;
        }
    }

    public function updateDepositActives($deposit,$first_deposited,$today_first_deposited){

        if (empty($deposit->active_apply))
            return ;

        foreach(explode(',',$deposit->active_apply) ?? [] as $active_id){

            $active = \DB::table('active_apply as apply')
                ->leftJoin('active as a','apply.active_id','=','a.id')
                ->selectRaw('apply.`user_id`,apply.`active_id`,apply.`state`,a.type_id,apply.coupon_money')
                ->where('apply.id',$active_id)
                ->first();
            $active = (array) $active;
            //新人首充加钱
            if(isset($active['type_id']) && $active['type_id'] == 2 && !$first_deposited){

                if($active['state'] == 'auto'){
                    \DB::table('active_apply')->where('id',$active_id)->update(['status'=>'pass']);
                }else if($active['state'] == 'manual'){
                    \DB::table('active_apply')->where('id',$active_id)->update(['status'=>'pending']);
                }
            }
            //每日首充加钱
            if(isset($active['type_id']) && $active['type_id'] == 3 && !$today_first_deposited){

                if($active['state'] == 'auto'){
                    \DB::table('active_apply')->where('id',$active_id)->update(['status'=>'pass']);
                }else if($active['state'] == 'manual'){
                    \DB::table('active_apply')->where('id',$active_id)->update(['status'=>'pending']);
                }
            }
        }
    }

}