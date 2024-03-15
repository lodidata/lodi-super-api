<?php

use Logic\Admin\BaseController;
use Logic\Define\CacheKey;

/**
 * 根据lottery_number(来自core db)从common db获取彩期列表
 */
return new class extends BaseController
{
    protected $beforeActionList = [
    ];

    public function run()
    {
        $this->verifyLotteryToken();

        $id = $this->request->getParam('id');
        $lotteryNumber = $this->request->getParam('lottery_number');
        $lotteryInfoData = $this->redisCommon->get(CacheKey::$perfix['ApiCommonLotteryInfoList'] . $id . "_" . $lotteryNumber);
        if (empty($lotteryInfoData)) {
            $db = $this->ci->db->getConnection("common");
            $lotteryInfo = $db->table('lottery_info')
                ->where('lottery_type', '=', $id)
                ->where('lottery_number', '>', $lotteryNumber)
                ->orderBy('lottery_number', 'asc')
                ->select(['lottery_name', 'pid', 'lottery_type', 'start_time', 'end_time', 'lottery_number', 'period_code', 'catch_time', 'official_time', 'period_result', 'n1', 'n2', 'n3', 'n4', 'n5', 'n6', 'n7', 'n8', 'n9', 'n10']);

            // 非低频彩
            if ($id != 52) {
                $lotteryInfo->where('start_time', '>', strtotime('-1 days'));
            }
            $lotteryInfo->limit(3000);
            $lotteryInfo = $lotteryInfo->get()->toArray();
            if($lotteryInfo){
                $lotteryInfoData = json_encode($lotteryInfo, JSON_UNESCAPED_UNICODE);
                $this->redisCommon->setex(CacheKey::$perfix['ApiCommonLotteryInfoList'] . $id . "_" . $lotteryNumber, 5, $lotteryInfoData);
            }
        }

        $lotteryInfo = $lotteryInfoData ?? '';
        return $lotteryInfo;
//        return  $this->encrypt($lotteryInfo);
    }

};