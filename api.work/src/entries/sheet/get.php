<?php
use Utils\Work\Action;

/**
 * 工单列表
 */
return new class extends Action{

    public function run(){
        $params = $this->request->getParams();

        $query= \DB::table('worksheet')->orderBy('id','DESC');

        isset($params['level']) && !empty($params['level']) && $query->where('level','=', $params['level']);//优先级
        isset($params['project_label']) && !empty($params['project_label']) && $query->where('project_label','=', $params['project_label']);//项目标识
        isset($params['customer_label']) && !empty($params['customer_label']) && $query->where('customer_label','=', $params['customer_label']);//客户标识
        isset($params['rec_dep_label']) && !empty($params['rec_dep_label']) && $query->where('rec_dep_label','=', $params['rec_dep_label']);//受理部门
        isset($params['rec_label']) && !empty($params['rec_label']) && $query->where('rec_label','=', $params['rec_label']);//受理人
        isset($params['stime']) && !empty($params['stime']) && $query->where('created','>=',$params['stime'] ." 00:00:00");//创建时间
        isset($params['etime']) && !empty($params['etime']) && $query->where('created','<=',$params['etime'] ." 23:59:59");//创建时间
        isset($params['task_no']) && !empty($params['task_no']) && $query->where('task_no','like', '%'.$params['task_no'].'%');//工单编号
        isset($params['title']) && !empty($params['title']) && $query->where('title','like', '%'.$params['title'].'%');//工单标题
        isset($params['desc']) && !empty($params['desc']) && $query->where('desc','like', '%'.$params['desc'].'%');//工单描述
        isset($params['job_status']) && !empty($params['job_status']) && $query->where('job_status','=', $params['job_status']);//工单状态

//        工单状态统计
//        工单状态，1未处理；2处理中；3已解决；4已关闭
        $stat_arr = ['1'=>0, '2'=>0, '3'=>0, '4'=>0];
        $stat_query = clone $query;
        $stat_data = $stat_query->select('job_status', DB::raw('COUNT(id) as total_id'))->groupBy('job_status')->get();
        if($stat_data){
            foreach ($stat_data as $v){
                $stat_arr[$v->job_status] = $v->total_id;
            }
        }

        $total = $query->count();
        $data = $query->forPage($params['page'], $params['page_size'])->get()->toArray();

        $attr = [
            'page' => $params['page'],
            'page_size' => $params['page_size'],
            'total' => $total,
            'stats' => $stat_arr,
        ];
        return $this->lang->set(0, [], $data, $attr);
    }

};