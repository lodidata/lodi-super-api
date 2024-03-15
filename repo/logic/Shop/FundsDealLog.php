<?php
namespace Logic\Shop;

/**
 * 钱包模块
 */
class FundsDealLog extends \Logic\Logic{
    const WALLET_TYPE_PRIMARY = 1;  //主钱包
    const WALLET_TYPE_SUB = 2; //子钱包
    const CATEGORY_INCOME = 1;  //交易类别：收入
    const CATEGORY_COST = 2;  //交易类别： 支出
    const CATEGORY_TRANS = 3; //交易类别： 额度转换
    const CATEGORY_FREEMONEY = 4;  //交易类别： 修改可提余
    const TYPE_INCOME_ONLINE = 101; //线上入款
    const TYPE_INCOME_OFFLINE = 102;  //线下入款
    const TYPE_PAYOUT_LOTTERY = 104;  //彩票派彩
    const TYPE_ACTIVITY = 105; //优惠活动
    const TYPE_INCOME_MANUAL = 106;  //手动存款
    const TYPE_REBET = 107; //返水优惠
    const TYPE_AGENT_CHARGES = 108; //代理退佣
    const TYPE_SALES = 109;  //销售返点
    const TYPE_CANCEL_ORDER = 110; //彩票撤单
    const TYPE_ADDMONEY_MANUAL = 112;  //手动增加余额
    const TYPE_REBET_MANUAL = 113; //手动发放返水
    const TYPE_ACTIVITY_MANUAL = 114; //手动发放优惠
    const TYPE_WIRTDRAW_REFUSE   = 118; //拒绝出款
    const TYPE_INCREASE_FREEMONEY_MANUAL = 120; //手动增加可提余额
    const TYPE_DECREASE_FREEMONEY_MANUAL = 119; //手动减少可提余额
    const TYPE_WITHDRAW = 201;  //会员提款
    const TYPE_GOODS_BUY = 202; //商品购买
    const TYPE_WITHDRAW_MANUAL = 204; //手动提款
    const TYPE_REDUCE_MANUAL = 207;  //手动减少余额
    const TYPE_CHASE_MANUAL = 209;  //追号冻结
    const TYPE_WITHDRAW_ONFREEZE = 208;  //提款审核中
    const TYPE_WIRTDRAW_CUT = 210;  //提现扣款
    const TYPE_REDUCE_MANUAL_OTHER = 211;  //减少余额 其它
    const TYPE_ADDMONEY_MANUAL_OTHER = 212;  //减少余额 其它
    const TYPE_CTOM = 301;  //子转主钱包
    const TYPE_MTOC = 302; //主转子钱包
    const TYPE_CTOM_MANUAL = 303;  //手动子转钱包
    const TYPE_MTOC_MANUAL = 304;  //手动主转子钱包
    const TYPE_MFOC_MANUAL = 305;  //手动主转保险箱
    const TYPE_FMOC_MANUAL = 306;  //手动保险箱转主
    const TYPE_LEVEL_MANUAL1 = 308;//等级赠送  晋升彩金
    const TYPE_LEVEL_MANUAL2 = 309;//等级赠送  转卡彩金
    const TYPE_LEVEL_MONTHLY = 310;//不同等级对应的月俸禄奖金
    const TYPE_LOTTERY_SETTLE = 400;//彩票结算未中奖
    const TYPE_HAND_DML_ADD = 405;//手动增加打码量
    const TYPE_HAND_DML_PLUS = 406;//手动减少打码量
    const TYPE_THIRD_SETTLE = 408;//第三方结算
    const TYPE_TRANSFER_XIMA = 501;  //洗码活动

    /**
     * 生成流水号
     *
     * @param int $rand
     *
     * @return string
     */
    public static function generateDealNumber($rand = 999999999, $length = 9) {
        return date('mdhis') . str_pad(mt_rand(1, $rand), $length, '0', STR_PAD_LEFT);
    }

    /**
     * 插入记录
     */
    public static function create($data){
        global $playLoad;
        $data['created'] = date('Y-m-d H:i:s');
        $data['deal_number'] = FundsDealLog::generateDealNumber();
        $data['coupon_money'] = $data['coupon_money'] ?? 0;
        $data['status'] = 1;
//        $obj->user_type = empty($obj->user_type) ? 1 : $obj->user_type; // 代理改人人代理默认1
        $data['admin_id'] = $playLoad['uid'] ?? 0;
        $data['admin_user'] = $playLoad['nick'] ?? '';
        return \DB::table('funds_deal_log')->insertGetId($data);
    }

    public static function getDealLogTypes() {
        return [
            [
                'id'       => FundsDealLog::CATEGORY_INCOME,
                'name'     => '收入',
                'children' => [
                    ['id' => FundsDealLog::TYPE_INCOME_ONLINE, 'name' => '线上支付'],
                    ['id' => FundsDealLog::TYPE_INCOME_OFFLINE, 'name' => '线下入款'],
//                    ['id' => FundsDealLog::TYPE_PAYOUT_LOTTERY, 'name' => '彩票派彩'],
//                    ['id' => FundsDealLog::TYPE_ACTIVITY, 'name' => '优惠活动'],
//                    ['id' => FundsDealLog::TYPE_INCOME_MANUAL, 'name' => '手动存款'],
//                    ['id' => FundsDealLog::TYPE_REBET, 'name' => '回水金额'],
//                    ['id' => FundsDealLog::TYPE_AGENT_CHARGES, 'name' => '代理退佣'],
//                    ['id' => FundsDealLog::TYPE_CANCEL_ORDER, 'name' => '撤单退款'],
//                    ['id' => FundsDealLog::TYPE_ADDMONEY_MANUAL, 'name' => '手动增加余额'],
//                    ['id' => FundsDealLog::TYPE_REBET_MANUAL, 'name' => '手动发放返水'],
//                    ['id' => FundsDealLog::TYPE_ACTIVITY_MANUAL, 'name' => '手动发放优惠'],
//                    ['id' => FundsDealLog::TYPE_WIRTDRAW_REFUSE, 'name' => '提款失败退款'],
//                    ['id' => FundsDealLog::TYPE_DECREASE_FREEMONEY_MANUAL, 'name' => '手动减少可提余额'],
//                    ['id' => FundsDealLog::TYPE_INCREASE_FREEMONEY_MANUAL, 'name' => '手动增加可提余额'],
//                    ['id' => FundsDealLog::TYPE_LOTTERY_SETTLE, 'name' => '彩票未中奖'],
//                    ['id' => FundsDealLog::TYPE_THIRD_SETTLE, 'name' => '第三方游戏结算'],
//                    ['id' => FundsDealLog::TYPE_HAND_DML_ADD, 'name' => '手动增加打码量'],
//                    ['id' => FundsDealLog::TYPE_HAND_DML_PLUS, 'name' => '手动减少打码量'],
//                    ['id' => FundsDealLog::TYPE_LEVEL_MANUAL1, 'name' => '晋升彩金'],
//                    ['id' => FundsDealLog::TYPE_LEVEL_MANUAL2, 'name' => '转卡彩金'],
//                    ['id' => FundsDealLog::TYPE_LEVEL_MONTHLY, 'name' => '月俸禄'],
//                    ['id' => FundsDealLog::TYPE_TRANSFER_XIMA, 'name' => '洗码活动'],
                ],
            ],
            [
                'id'       => FundsDealLog::CATEGORY_COST,
                'name'     => '支出',
                'children' => [
//                    ['id' => 201, 'name' => '提款成功'],
                    ['id' => 202, 'name' => '商品购买'],
//                    ['id' => 204, 'name' => '手动扣款'],
//                    ['id' => 207, 'name' => '手动减少余额'],
//                    ['id' => 208, 'name' => '提现审核中'],
//                    ['id' => 209, 'name' => '追号冻结'],
//                    ['id' => FundsDealLog::TYPE_REDUCE_MANUAL_OTHER, 'name' => '余额减少-其它'],
                ],
            ],
//            [
//                'id'       => FundsDealLog::CATEGORY_TRANS,
//                'name'     => '额度转换',
//                'children' => [
//                    ['id' => 301, 'name' => '子转主钱包'],
//                    ['id' => 302, 'name' => '主转子钱包'],
//                    ['id' => 303, 'name' => '手动子转主钱包'],
//                    ['id' => 304, 'name' => '手动主转子钱包'],
//                    ['id' => 305, 'name' => '主钱包转保险箱'],
//                    ['id' => 306, 'name' => '保险箱转主钱包'],
//                ],
//            ],
//            [
//                'id'       => FundsDealLog::CATEGORY_FREEMONEY,
//                'name'     => '可提余额',
//                'children' => [
//                    ['id' => 119, 'name' => '手动减少可提余额'],
//                    ['id' => 120, 'name' => '手动增加可提余额'],
//                ],
//            ],
        ];
    }

    public static function getDealLogTypeFlat() {
        $_ = [];
        foreach (self::getDealLogTypes() as $types) {
            $_ = array_merge($_, $types['children']);
        }

        return array_column($_, 'name', 'id');
    }
}
