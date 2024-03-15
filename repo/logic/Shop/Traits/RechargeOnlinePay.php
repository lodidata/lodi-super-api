<?php
namespace Logic\Shop\Traits;
use Logic\Shop\Pay;
use Model\Bank;
use Model\FundsDeposit;
use Model\FundsVender;
use Logic\Activity\Activity;
use Psr\Log\NullLogger;

trait RechargeOnlinePay {
    /**
     * 调用支付平台
     *
     * @param int    $venderType 支付使用场景 wx 微信 alipay 支付宝 unionpay 网银
     * @param int    $money 金额
     * @param int    $userId 用户id
     * @param string $ip ip
     * @param bool   $needPre 是否需要优惠
     */
    public function onlinePayWebSite(int $money, int $userId, string $ip, $needPre, int $payid,string $pay_code = null){
        //   充值相关（仅充值）任何数据验证都放verify这里面验证  验证过返回true;
        $gateway = [
            'user_id' => $userId,
            'pay_no' => date("YmdHis") . rand(pow(10, 3), pow(10, 4) - 1),
            'amount' => $money,
            'request_time' => date("Y-m-d H:i:s"),
            'status' => 'pending',
            'inner_status' => 'waiting'
        ];
        // 2. 生成入款单据
        $model = [
            'trade_no' => date("YmdHis") . rand(pow(10, 3), pow(10, 4) - 1),
            'user_id' => $userId,
            'money' => $money,
            'pay_no' => $gateway['pay_no'],
            'over_time' => date("Y-m-d H:i:s", time() + 8*60 * 60),
            'ip' => $ip
        ];
        // 获取用户信息
        $user = \DB::table('user')->find($userId);
        $user = (array)$user;

        $model['name'] = $user['name'];
        // 添加入款单成功，调用平台支付接口
        $result = array(
            'code' => 886,
            'msg' => [],
            'way' => '',
            'str' => '',
            'money' => $money,
        );
        //获取来源
        $origins = ['pc' => 1, 'h5' => 2, 'ios' => 3, 'android' => 4];
        $origin = isset($this->request->getHeaders()['HTTP_PL']) && is_array($this->request->getHeaders()['HTTP_PL']) ? current($this->request->getHeaders()['HTTP_PL']) : '';
        //请求支付平台
        $return_url = $this->getReturnUrl();
        $res = $this->rechargePaySite($model['trade_no'], $money, $payid, $return_url, $pay_code);
//        $res = [
//            "code"=> 0,
//            "msg"=> "SUCCESS",
//            "way"=> "jump",
//            "str"=> "http://www.jinyuanjin.com/paygateway/mbpay/order/v2?orderid=14211571753359862ecq",
//            "money"=> 100000,
//            "id"=> 1278,
//            "pay_id"=> 10038,
//            "active_rule"=> NULL,
//            "payname"=> "万通支付",
//            "scene"=> "alipay",
//            "vendername"=> "万通支付支付宝WAP(棋牌辅助软件)",
//        ];
        if(!$res){
            $res['msg']='该通道代码有误，请联系技术人员';
        }elseif($res['code'] == 0) {
            try {
                $this->db->getConnection()->beginTransaction();
                $types = Pay::getPayType('type');
                $bankInfo = json_encode(['id' => $res['pay_id'], "pay" => $res['payname'], "vender" => $res['vendername']],JSON_UNESCAPED_UNICODE);
                $model['deposit_type'] = $res['pay_id'];//pay_channel对应的id
                $model['pay_bank_id'] = $res['pay_id'];//pay_channel对应的id
                $model['pay_type'] = $types[$res['scene']] ?? 0;//支付场景
                $model['money'] = $res['money'];//支付金额，以分为单位
                $model['receive_bank_account_id'] = $res['id'];//第三方支付id
                $model['receive_bank_info'] = $bankInfo;
                $model['state'] = 'online';
                $model['origin'] = isset($origins[$origin]) ? $origins[$origin] : 0;//来源
//                $model['passageway_active'] = is_array($res['active_rule']) ? json_encode($res['active_rule']) : '';
                $re_deposit = \DB::table('funds_deposit')->insertGetId($model);
                if($re_deposit) {
                    \DB::table('funds_gateway')->insertGetId($gateway);
//                    $active = new Activity($this->ci);
//                    $activeData = $active->rechargeActive($userId, $user, $money, $needPre, 'online', $re_deposit->id);
//                    $update['state'] = 'online';
//                    $update['active_apply'] = $activeData['activeApply'];
//                    $update['withdraw_bet'] = $activeData['withdraw_bet'] ?? 0;
//                    $update['coupon_withdraw_bet'] = $activeData['coupon_withdraw_bet'] ?? 0;
//                    if (isset($activeData['state']) && $activeData['state'])
//                        $update['state'] .= ',' . $activeData['state'];
//                    $update['coupon_money'] = $activeData['coupon_money'] ?? 0;
//                    FundsDeposit::where('id', $re_deposit->id)->update($update);
                    $this->db->getConnection()->commit();
                }else {
                    $this->db->getConnection()->rollback();
                    $res['code'] = 886;
                    $res['msg']='业务繁忙，请稍会重试';
                }
            }catch (\Exception $e){
                $this->db->getConnection()->rollback();
                $res['code'] = 886;
                $res['msg']='业务繁忙，请稍会重试';
            }
        }else{
            $result['code'] = 886;
            $result['msg'] = [$res['msg']];
        }
        $result = array_merge($result,$res);
        return $result;
    }

    /**
     * 兼容以前，若异步，同步回调数据库没填写的情况
     * @param string $platform
     * @return 返回结果
     */
    public function getReturnUrl() {
        $website = 'https://'.$_SERVER['HTTP_HOST'];
        $weburl = explode('.',$_SERVER['HTTP_HOST']);
        if(isset($weburl[1])&&isset($weburl[2]))
            $weburl = 'https://m.'.$weburl[1].'.'.$weburl[2];
        else
            $weburl = $website;
        return $weburl.'/user';
    }
}