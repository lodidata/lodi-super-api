<?php

use Lib\Validate\BaseValidate;
use Logic\Admin\BaseController;

return new class() extends BaseController
{
    //前置方法
    protected $beforeActionList = [
        'verifyToken',
        'authorize',
    ];

    public function run($id = null)
    {
        $this->checkID($id);
        $validate = new BaseValidate([
            'clear_start_date' => 'require',
            'clear_end_date' => 'require',
            'quota' => 'require',
            'money' => 'require',
        ], [
                'clear_start_date' => '开始清算时间',
                'clear_end_date' => '清算结束时间',
                'quota' => '清算额度',
                'money' => '收费金额',
            ]
        );
        $validate->paramsCheck('', $this->request, $this->response);
        $params = $this->request->getParams();
//        DB::connection()->enableQueryLog();  // 开启QueryLog
        if ($params['clear_start_date'] > $params['clear_end_date']) {
            return $this->lang->set(886, ['开始时间不能大于结束时间！']);
        }

        $data = DB::table('surper_order_day_rpt')
            ->select('game_bet_amount', 'game_prize_amount')
            ->where('user_id', '=', $id)
            ->where('clear_status', '=', 0)
            ->whereNotIn('game_type', ['ZYCPSTA', 'ZYCPCHAT'])
            ->whereBetween('count_date', [$params['clear_start_date'], $params['clear_end_date']])
            ->get()
            ->toArray();

        $sumQuota = 0;
        foreach ($data as $datum) {
            $datum = (array)$datum;
            $sumQuota = $sumQuota + bcsub($datum['game_bet_amount'], $datum['game_prize_amount'], 4);
        }

        $scaleInfo = (array)DB::table('quota')
            ->selectRaw("scale,use_quota,surplus_quota,total_quota")
            ->where('cust_id', '=', $id)
            ->get()
            ->first();

        $scale = explode(':', $scaleInfo['scale']);
//        $sumMoney = ($scale[0] / $scale[1]) * $sumQuota;
        $sumMoney = bcmul(bcdiv($scale[0], $scale[1], 4), $sumQuota, 4);

//
//        var_dump(DB::getQueryLog());

        if (bccomp($params['quota'], $sumQuota) != 0) {
            return $this->lang->set(886, ['清算额度计算有误！']);
        }

        if (bccomp($params['money'], $sumMoney) != 0) {
            return $this->lang->set(886, ['清算费用计算有误！']);
        }

//        if ($scaleInfo['surplus_quota'] < 0) {
//            return $this->lang->set(886, ['额度不足！']);
//        }

        //清算了多少额度就给使用额度减少多少额度，并且把剩余额度加回去
        $use_quota = bcsub($scaleInfo['use_quota'], $params['quota'], 4);

        $surplus_quota=$scaleInfo['surplus_quota'];

        if($params['quota']>0){
            $surplus_quota = bcadd($scaleInfo['surplus_quota'], $params['quota'], 4);
        }


        $res = DB::table('quota')
            ->where('cust_id', '=', $id)
            ->update(['use_quota' => $use_quota, 'surplus_quota' => $surplus_quota, 'admin_user' => $this->getAdminUserName(),]);

        $result = DB::table('surper_order_day_rpt')
            ->where('user_id', '=', $id)
            ->whereNotIn('game_type', ['ZYCPSTA', 'ZYCPCHAT'])
            ->whereBetween('count_date', [$params['clear_start_date'], $params['clear_end_date']])
            ->update(['clear_status' => 1]);

        if ($res !== false && $result != false) {

            DB::table('quota_log')
                ->insert(['cust_id' => $id, 'use_quota' => $use_quota, 'clear_quota' => $params['quota'], 'scale' => $scaleInfo['scale'], 'clear_user' => $this->getAdminUserName(), 'clear_start_date' => $params['clear_start_date'], 'clear_end_date' => $params['clear_end_date'], 'updated' => date('Y-m-d H:i:s'), 'money' => $sumMoney]);

            $arr = [
                'clear_start_date' => $params['clear_start_date'],
                'clear_end_date' => $params['clear_end_date'],
                'cust_id' => $id,
                'admin_user' => $this->getAdminUserName(),
                'use_quota' => $use_quota,
                'surplus_quota' => $surplus_quota,
            ];

            $res = \Logic\Recharge\Recharge::requestPaySit("putClearStatus", '', $arr);

            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }

};