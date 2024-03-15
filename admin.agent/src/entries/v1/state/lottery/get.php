<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:31
 */

return new class extends Logic\Admin\BaseController
{
    const TITLE = '彩种报表';
    const DESCRIPTION = '彩种报表';
    const HINT = '';
    const QUERY = [
        'customer_id' => '客户ID',
        'create_time' => '创建时间'
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [

        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];
    public function run()
    {
        $params=$this->request->getParams();
        (new \Lib\Validate\Admin\PayValidate())->paramsCheck('get',$this->request,$this->response);
        if(isset($params['menu'])){
            $sql = DB::connection('state')->table('super_lottery_earnlose_rpt')->select(DB::raw('user_name,user_id'));
            $data = $sql->groupBy('user_id')->get()->toArray();
            return $this->lang->set(0,[],$data);
        }
        $sql = DB::connection('state')->table('super_lottery_earnlose_rpt')
            ->select(DB::raw('count_date,user_id,user_name,lottery_id,lottery_name,sum(lottery_bet_num) as lottery_bet_num,
            sum(lottery_bet_money) as lottery_bet_money,sum(lottery_send_money) as lottery_send_money,
            sum(lottery_lose_earn_money) as lottery_lose_earn_money,create_time'));
        $sql=isset($params['user_id'])&&!empty($params['user_id'])?$sql->where('user_id', $params['user_id']):$sql;
        $sql=isset($params['lottery_id'])&&!empty($params['lottery_id'])?$sql->where('lottery_id', $params['lottery_id']):$sql;
        if((isset($params['start_time']) && !empty($params['start_time'])) && (isset($params['end_time']) && !empty($params['end_time']))){
            $sql = $sql->where('count_date','>=',$params['start_time']);
            $sql = $sql->where('count_date','<=',$params['end_time']);
        }
        if(isset($params['sort']) && $params['sort']=='desc' && isset($params['type_name'])){
            $sql = $sql->orderByDesc($params['type_name']);
        }elseif(isset($params['sort']) && $params['sort']=='asc' && isset($params['type_name'])){
            $sql = $sql->orderBy($params['type_name']);
        }else{
            $sql = $sql->orderBy('id');
        }
        $data = $sql->groupBy('user_id')->forPage($params['page'],$params['page_size'])->get()->toArray();
        $l_time=[];
        foreach($data as $k=>$v){
            $l_time[] = strtotime($v->create_time);
        }
        $total = count($l_time);
        rsort($l_time);
        $arrlength=count($l_time);
        for($x=0;$x<$arrlength;$x++)
        {
            $ls_time[] = $l_time[$x];
        }
        if(isset($ls_time[0])){
            $latest_time = date("Y-m-d H:i:s",$ls_time[0]);
        }
        return $this->lang->set(0, [], $data, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total,'latest_time'=>isset($latest_time)?$latest_time:'']);
    }
};