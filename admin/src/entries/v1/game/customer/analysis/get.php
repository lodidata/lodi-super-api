<?php

use Logic\Admin\BaseController;
use Lib\Validate\BaseValidate;
/**
 * 同步厅主数据
 */
return new class extends BaseController
{
    protected $beforeActionList = [
//        'verifyToken', 'authorize',
    ];

    public function run()
    {
        (new BaseValidate(
            [
                'game_type'=>'require',
                'start_date'=>'require',
//                'end_date'=>'require',
            ]
        ))->paramsCheck('',$this->request,$this->response);
        $customer = \DB::table('customer as c')
            ->leftJoin('customer_notify as n','c.id','=','n.customer_id')
            ->where('n.status','enabled')
            ->where('c.type','game')
            ->groupBy('n.customer_id')->get([
                'c.id as customer_id',
                'c.customer',
                'n.admin_notify',
            ])->toArray();

        $params = $this->request->getParams();
        foreach ($customer as $val){
            $queryParams = [
                'start_date'=>$params['start_date'],
                'end_date'=>$params['start_date'] . ' 23:59:59',
                'game_type'=>$params['game_type'],
            ];
            $url = $val->admin_notify.'/report/OverPipe?' . http_build_query($queryParams);
            $res = json_decode(\Utils\Curl::get($url),true)['data'] ?? [];
            if(!$res ||!isset($res['game_type'])) continue;
            $tmp = [];
            foreach ($res['list'] as $t) {
                if($t['date'] == $params['start_date']){
                    $tmp = $t;
                }
            }
            $d = [
                'customer_id' => $val->customer_id,
                'customer' => $val->customer,
                'game_type' => $res['game_type'],
                'game_name' => $res['game_name'],
                'count' => $tmp['count'] ?? 0,
                'bet' => $tmp['bet'] ?? 0,
                'valid_bet' => $tmp['valid_bet'] ?? 0,
                'win_loss' => $tmp['win_loss'] ?? 0,
                'date' => $tmp['date'] ?? $params['start_date'],
                'updated' => date('Y-m-d H:i:s'),
            ];
            DB::table('checkdata')->updateOrInsert([
                'game_type' => $d['game_type'],
                'date' => $d['date'],
                'customer_id' => $val->customer_id,
            ] , $d);
        }
        return $this->lang->set(0);
    }

};