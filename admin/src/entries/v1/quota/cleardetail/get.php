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
        $count_date = $this->request->getParam('count_date',date('Y-m-d'));
        if(!$count_date){
            return $this->lang->set(886,['日期不能为空！']);
        }

        $res = \DB::table('surper_order_day_rpt')
            ->where('user_id', '=', $custId)
            ->where('count_date', '=', $count_date)
            ->whereNotIn('game_type', ['ZYCPSTA','ZYCPCHAT'])
            ->selectRaw("game_bet_amount-game_prize_amount as quota,clear_status,game_bet_amount,game_prize_amount,game_name,game_type")
            ->get()
            ->toArray();
        $sumQuota=0;
        $status=0;
        foreach ($res as &$value) {
            $value=(array)$value;
            $status=$value['clear_status'];
            if(($value['game_bet_amount'] - $value['game_prize_amount'])>0){
                $sumQuota=$sumQuota+($value['game_bet_amount'] - $value['game_prize_amount']);
            }
        }
        $data=[];
        $data['count_date']=$count_date;
        $data['clear_status']=$status;
        $data['info']=$res;

        return $data;
    }

};