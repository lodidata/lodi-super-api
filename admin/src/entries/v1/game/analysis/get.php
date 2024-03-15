<?php

use Logic\Admin\BaseController;
use Lib\Validate\BaseValidate;
/**
 * 客户信息查询
 */
return new class extends BaseController
{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        (new BaseValidate(
            [
//                'customer_id'=>'integer',
                'game_type'=>'require',
                'start_date'=>'require',
                'end_date'=>'require',
            ]
        ))->paramsCheck('',$this->request,$this->response);

       $params = $this->request->getParams();
        $query = DB::table('checkdata')
            ->where('date','>=',$params['start_date'])
            ->where('date','<=' , $params['end_date'])
            ->where('game_type',$params['game_type']);
        isset($params['customer_id']) && $params['customer_id'] && $query->where('customer_id',$params['customer_id']);
        $exits = clone $query;
        if(!$exits->where('date',$params['end_date'])->first()){
            \Logic\Game\GameApi::sCurDay($params['end_date'],$params['game_type']);
        }
        $data =  $query->groupBy('date')->orderBy('date','DESC')->get([
            'date',
            \DB::raw('SUM(count) as count'),
            \DB::raw('SUM(bet) as bet'),
            \DB::raw('SUM(valid_bet) as valid_bet'),
            \DB::raw('SUM(win_loss) as win_loss'),
            \DB::raw('SUM(third_bet) as third_bet'),
            \DB::raw('SUM(third_valid_bet) as third_valid_bet'),
        ])->toArray();
        return [
            'list' => $data,
            'game_name' => '',
            'game_type' => $params['game_type'],
            'user_prefix' => '',
        ];
    }



};