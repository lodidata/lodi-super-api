<?php

use Logic\Admin\BaseController;

return new class() extends BaseController
{
    //前置方法
    protected $beforeActionList = [
        'verifyToken',
        'authorize',
    ];

    public function run()
    {
        $custId = $this->request->getParam('cust_id');
        $this->checkID($custId);

        $quota = (array)DB::table('quota')
            ->select('scale')
            ->where('cust_id', '=', $custId)
            ->get()->first();
        $str = explode(':', $quota['scale']);
        $quotaScale = $str[0] / $str[1];

        $res = \DB::table('surper_order_day_rpt')
            ->where('user_id', '=', $custId)
            ->where('clear_status', '=', 0)
            ->whereNotIn('game_type', ['ZYCPSTA', 'ZYCPCHAT'])
            ->selectRaw("count_date, sum(game_bet_amount) as total_bet,sum(game_prize_amount) as total_prize,clear_status,count_date")
            ->groupBy('count_date')
            ->get()
            ->toArray();
        $data = [];
        foreach ($res as $key => &$value) {
            $value = (array)$value;
            $value['count_date'] = $value['count_date'];
            $value['clear_status'] = $value['clear_status'];
            $value['quota'] = $value['total_bet'] - $value['total_prize'];
            $value['money'] = bcmul(($value['total_bet'] - $value['total_prize']), $quotaScale, 4);
            array_push($data, $value);
        }
        array_multisort(array_column($data, 'count_date'), SORT_DESC, $data);
        return $this->lang->set(0, [], $data);
    }

};