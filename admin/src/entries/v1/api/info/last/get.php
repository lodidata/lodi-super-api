<?php

use Logic\Admin\BaseController;
use Logic\Define\CacheKey;

/**
 * 最后一期期号查询
 */
return new class extends BaseController
{
    protected $beforeActionList = [
    ];

    public function run()
    {
        $this->verifyLotteryToken();

        $id = $this->request->getParam('id');
        $lastLotteryInfoData = $this->redisCommon->get(CacheKey::$perfix['ApiCommonLotteryInfoLast'] . $id);

        if (empty($lastLotteryInfoData)) {
            $db = $this->ci->db->getConnection('common');

            $lastLotteryInfo = (array)$db->table('lottery_info')
                                         ->select(['lottery_number', 'start_time', 'end_time'])
                                         ->where('lottery_type', $id)
                                         ->orderBy('lottery_number', 'desc')
                                         ->take(1)
                                         ->first();

            if ($lastLotteryInfo) {
                $lastLotteryInfoData = json_encode($lastLotteryInfo, JSON_UNESCAPED_UNICODE);
                $this->redisCommon->setex(CacheKey::$perfix['ApiCommonLotteryInfoLast'] . $id, 5, $lastLotteryInfoData);
            }
        }
        $lotteryInfo = $lastLotteryInfoData ?? '';
        return $lotteryInfo;
//        return $this->encrypt($lotteryInfo);
    }
};