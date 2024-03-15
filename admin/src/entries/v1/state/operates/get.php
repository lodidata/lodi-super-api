<?php

use Logic\Admin\BaseController;

return new class() extends BaseController
{
    const TITLE = '厅主统计';
    const QUERY = [
        'start_date' => 'string(optional) #开始日期',
        'end_date' => 'string(optional) #结束日期',
    ];
    const TYPE = 'application/json';
    const PARAMs = [];
    const SCHEMAs = [
        [
            [
                'account_id' => 'int #厅主ID',
                'account_name' => 'int #厅主名称',
                'game_user_cnt' => 'int #有效用户数',
                'game_order_cnt' => 'int #注单数',
                'game_bet_amount' => 'int #注单金额',
                'game_prize_amount' => 'int #派奖金额',
                'game_code_amount' => 'int #总打码量',
                'game_deposit_amount' => 'int #总转入',
                'game_withdrawal_amount' => 'int #总转出',
                'game_order_profit' => 'int #盈亏',
            ],
        ],
    ];
    //前置方法
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $start_date = $this->request->getParam('start_date');
        $end_date = $this->request->getParam('end_date');
        if (empty($start_date)) {
            if (empty($end_date)) {
                $end_date = date('Y-m-d');
            }
            $start_date = $end_date;
        } elseif (empty($end_date)) {
            $end_date = $start_date;
        }

        $result = \DB::table('rpt_orders_middle_day')
            ->selectRaw("tid,sum(game_user_cnt) as game_user_cnt, sum(game_order_cnt) as game_order_cnt, sum(game_bet_amount) as game_bet_amount,sum(game_prize_amount) as game_prize_amount, sum(game_order_profit) as game_order_profit")
            ->where('count_date', '>=', $start_date)
            ->where('count_date', '<=', $end_date)
            ->groupBy('tid')
            ->get()
            ->toArray();

        if (!$result) return $this->lang->set(0);

        //厅主
        $customer = \DB::table('customer')->get(['id', 'name'])->keyBy('id')->toArray();
        foreach ($result as $key => &$val) {
            $val = (array)$val;
            if (!isset($customer[$val['tid']])) {
                unset($result[$key]);
                continue;
            }
            $val['account_id'] = $val['tid'];
            $val['account_name'] = $customer[$val['tid']]->name;
            $val['RTP'] = $val['game_bet_amount'] == 0 ? 0 : bcmul($val['game_prize_amount'] / $val['game_bet_amount'], 100, 2);
        }
        unset($val);
        return array_values($result);
    }

};