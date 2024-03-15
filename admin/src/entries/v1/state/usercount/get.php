<?php

return new class extends Logic\Admin\BaseController {
    const TITLE = '用户报表';
    const DESCRIPTION = '用户报表';
    const HINT = '';
    const QUERY = [
        'customer_id' => '客户ID',
        'create_time' => '创建时间',
    ];
    const TYPE = 'ap/json';
    const PARAMs = [];
    const SCHEMAs = [
        'data' => [

        ],
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run() {
        $params = $this->request->getParams();
        (new \Lib\Validate\Admin\PayValidate())->paramsCheck('get', $this->request, $this->response);
        if(isset($params['menu'])){
            $sql = DB::connection('state')->table('super_user_rpt')->select(DB::raw('user_name,user_id'));
            $data = $sql->groupBy('user_id')->get()->toArray();
            return $this->lang->set(0,[],$data);
        }
        if(!isset($params['time_status'])){
            return false;
        }
        if ($params['time_status'] == 'day') {
            $sql = DB::connection('state')
                     ->table('super_userdata_day_rpt')
                     ->select(DB::raw('count_date,user_id,user_name,sum(inc_user_num) as inc_user_num,sum(cumul_user_num) as cumul_user_num,
            sum(bet_user_num) as bet_user_num,sum(bet_user_num_pc) as bet_user_num_pc,
            sum(bet_user_num_h5) as bet_user_num_h5,sum(bet_user_num_app) as bet_user_num_app,sum(active_user_num) as active_user_num,
            sum(first_deposit_num) as first_deposit_num,sum(recharge_num) as recharge_num,sum(withdraw_deposit_num) as withdraw_deposit_num,create_time'));

            $sql = isset($params['user_id']) && !empty($params['user_id']) ? $sql->where('user_id', $params['user_id']) : $sql;

            if (isset($params['sort']) && $params['sort'] == 'desc' && isset($params['type_name'])) {
                $sql = $sql->orderByDesc($params['type_name']);
            } else if (isset($params['sort']) && $params['sort'] == 'asc' && isset($params['type_name'])) {
                $sql = $sql->orderBy($params['type_name']);
            } else {
                $sql = $sql->orderBy('id');
            }

            $sql = isset($params['count_date']) && !empty($params['count_date']) ? $sql->where('count_date', $params['count_date']) : $sql->where('count_date', date('Y-m-d', time()));

            $data = $sql->groupBy('user_id')
                        ->forPage($params['page'], $params['page_size'])
                        ->get()
                        ->toArray();

        } else if ($params['time_status'] == 'week') {

            $sql = DB::connection('state')
                     ->table('super_userdata_week_rpt')
                     ->select(DB::raw('count_date,user_id,user_name,sum(inc_user_num) as inc_user_num,sum(cumul_user_num) as cumul_user_num,
            sum(bet_user_num) as bet_user_num,sum(bet_user_num_pc) as bet_user_num_pc,
            sum(bet_user_num_h5) as bet_user_num_h5,sum(bet_user_num_app) as bet_user_num_app,sum(active_user_num) as active_user_num,
            sum(first_deposit_num) as first_deposit_num,sum(recharge_num) as recharge_num,sum(withdraw_deposit_num) as withdraw_deposit_num,create_time'));

            $sql = isset($params['user_id']) && !empty($params['user_id']) ? $sql->where('user_id', $params['user_id']) : $sql;

            if (isset($params['sort']) && $params['sort'] == 'desc' && isset($params['type_name'])) {
                $sql = $sql->orderByDesc($params['type_name']);
            } else if (isset($params['sort']) && $params['sort'] == 'asc' && isset($params['type_name'])) {
                $sql = $sql->orderBy($params['type_name']);
            } else {
                $sql = $sql->orderBy('id');
            }

            $data = $sql->groupBy('user_id')
                        ->forPage($params['page'], $params['page_size'])
                        ->get()
                        ->toArray();

        } else if ($params['time_status'] == 'month') {

            $sql = DB::connection('state')
                     ->table('super_userdata_month_rpt')
                     ->select(DB::raw('count_date,user_id,user_name,sum(inc_user_num) as inc_user_num,sum(cumul_user_num) as cumul_user_num,
            sum(bet_user_num) as bet_user_num,sum(bet_user_num_pc) as bet_user_num_pc,
            sum(bet_user_num_h5) as bet_user_num_h5,sum(bet_user_num_app) as bet_user_num_app,sum(active_user_num) as active_user_num,
            sum(first_deposit_num) as first_deposit_num,sum(recharge_num) as recharge_num,sum(withdraw_deposit_num) as withdraw_deposit_num,create_time'));

            $sql = isset($params['user_id']) && !empty($params['user_id']) ? $sql->where('user_id', $params['user_id']) : $sql;

            if (isset($params['sort']) && $params['sort'] == 'desc' && isset($params['type_name'])) {
                $sql = $sql->orderByDesc($params['type_name']);
            } else if (isset($params['sort']) && $params['sort'] == 'asc' && isset($params['type_name'])) {
                $sql = $sql->orderBy($params['type_name']);
            } else {
                $sql = $sql->orderBy('id');
            }

            $data = $sql->groupBy('user_id')
                        ->forPage($params['page'], $params['page_size'])
                        ->get()
                        ->toArray();
        }

        //$total = $sql->count();
        $l_time = [];
        foreach ($data as $k => $v) {
            $l_time[] = strtotime($v->create_time);
        }

        $total = count($l_time);

        rsort($l_time);

        $arrlength = count($l_time);

        for ($x = 0; $x < $arrlength; $x++) {
            $ls_time[] = $l_time[$x];
        }

        if (isset($ls_time[0])) {
            $latest_time = date("Y-m-d H:i:s", $ls_time[0]);
        }

        return $this->lang->set(0, [], $data, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total, 'latest_time' => isset($latest_time) ? $latest_time : '']);
    }
};