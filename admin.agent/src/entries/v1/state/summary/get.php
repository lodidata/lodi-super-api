<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:31
 */

return new class extends Logic\Admin\BaseController
{
    const TITLE = '总报表';
    const DESCRIPTION = '总报表';
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

    public function run()
    {
        $params=$this->request->getParams();
        (new \Lib\Validate\Admin\PayValidate())->paramsCheck('get',$this->request,$this->response);
        if(isset($params['menu'])){
            $sql = DB::connection('state')->table('super_total_earnlose_rpt')->select(DB::raw('user_name,user_id'));
            $data = $sql->groupBy('user_id')->get()->toArray();
            return $this->lang->set(0,[],$data);
        }
        $sql = DB::connection('state')->table('super_total_earnlose_rpt')
            ->select(DB::raw('count_date,user_id,user_name,plat_id,plat_name,sum(lose_earn_money) as lose_earn_money,sum(send_money) as send_money,
            sum(bet_money) as bet_money,sum(bet_num) as bet_num,sum(bet_user_num) as bet_user_num,
            sum(bet_num_avg) as bet_num_avg,sum(bet_money_num_avg) as bet_money_num_avg,sum(in_money) as in_money,
            sum(in_money_avg) as in_money_avg,sum(out_money) as out_money,sum(out_money_avg) as out_money_avg,create_time'));
        $sql=isset($params['user_id'])&&!empty($params['user_id'])?$sql->where('user_id', $params['user_id']):$sql;
        if($params['time_status'] =='day'){
            $sql=isset($params['count_date'])&&!empty($params['count_date'])?$sql->where('count_date', $params['count_date']):$sql->where('count_date', date('Y-m-d', time()));
        }elseif($params['time_status'] =='month'){
            $firstday = date("Y-m-01",strtotime($params['count_date']));
            $lastday = date("Y-m-d",strtotime("$firstday +1 month -1 day"));
            $sql=$sql->where('count_date','>=',$firstday);
            $sql=$sql->where('count_date','<=',$lastday);
        }
        //$total = $sql->count();
        $data = $sql->groupBy('user_id','plat_id')->forPage($params['page'],$params['page_size'])->get()->toArray();
        //取出最新更新时间
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