<?php

use Logic\Admin\BaseController;
use Logic\Define\CacheKey;

/**
 * 获取某天已开奖的彩期列表
 */
return new class extends BaseController
{
    protected $beforeActionList = [
    ];

    public function run()
    {
        $this->verifyLotteryToken();

        $id = $this->request->getParam('id');
        $date = $this->request->getParam('date');
        $lotteryInfoData = $this->redisCommon->get(CacheKey::$perfix['ApiCommonLotteryOpenList'] . $id . '_' . $date);
        if (empty($lotteryInfoData)) {
            $db = $this->ci->db->getConnection("common");
            $lotteryInfo = $db->table('lottery_info')
                ->whereRaw('period_code != ""')
                ->where('lottery_type', $id)
                ->where('end_time', '<', time() - 120 > strtotime($date . ' 23:59:59') ? strtotime($date . ' 23:59:59') : time() - 120)
                ->where('start_time', '>', strtotime($date))
                ->orderBy('end_time', 'desc')
                ->get([
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
                $this->redisCommon->setex(CacheKey::$perfix['ApiCommonLotteryOpenList'] . $id . '_' . $date, 5, $lotteryInfoData);
            }
        }

        $lotteryInfo = $lotteryInfoData ?? '';
        return $lotteryInfo;
//        return $this->encrypt($lotteryInfo);
    }

};