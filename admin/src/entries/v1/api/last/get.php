<?php

use Logic\Admin\BaseController;
use Logic\Define\CacheKey;

/**
 * 已开奖的彩期列表
 */
return new class extends BaseController
{
    protected $beforeActionList = [
    ];

    public function run()
    {
        $this->verifyLotteryToken();

        $id = $this->request->getParam('id');
        $lotteryInfoData = $this->redisCommon->get(CacheKey::$perfix['ApiCommonLotteryOpenList'] . $id);
        if(empty($lotteryInfoData)){
            $db = $this->ci->db->getConnection("common");
            $lotteryInfo = $db->table('lottery_info')->where('period_code', '!=', '')->where('lottery_type', $id)->orderBy('lottery_number', 'desc')->take(10)->get([
                'period_code',
                'lottery_number',
                'lottery_name',
                'period_result',
                'start_time',
                'end_time',
                'catch_time',
                'official_time',
                'lottery_type', 'n1', 'n2', 'n3', 'n4', 'n5', 'n6', 'n7', 'n8', 'n9', 'n10'
            ])->toArray();

            if($lotteryInfo){
                $lotteryInfoData = json_encode($lotteryInfo, JSON_UNESCAPED_UNICODE);
                $this->redisCommon->setex(CacheKey::$perfix['ApiCommonLotteryOpenList'] . $id, 5, $lotteryInfoData);
            }
        }

        $lotteryInfo = $lotteryInfoData ?? '';
        return $lotteryInfo;
//        return $this->encrypt($lotteryInfo);
    }

};