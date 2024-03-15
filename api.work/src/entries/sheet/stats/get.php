<?php
use Utils\Work\Action;

/**
 * 工单列表
 */
return new class extends Action{

    public function run(){
        $params = $this->request->getParams();
        if(empty($params['login_label']) || empty($params['login_dep_label'])){
            return $this->lang->set(886, ['登录用户标识和登录用户部门标识不能为空']);
        }

//        工单状态统计
//        工单状态，1未处理；2处理中；3已解决；4已关闭
        $data['all'] = \DB::table('worksheet')->count();//全部
        $data['my']['untreated'] = \DB::table('worksheet')->where('rec_label', $params['login_label'])
            ->whereIn('job_status', [1, 2])->count();//我的未处理
        $data['my']['processed'] = \DB::table('worksheet')->where('rec_label', $params['login_label'])
            ->whereIn('job_status', [3, 4])->count();//我的已处理
        $data['my']['created'] = \DB::table('worksheet')->where('sen_label', $params['login_label'])->count();//我创建的

//        组内
        $data['group']['untreated'] = \DB::table('worksheet')->where('rec_dep_label', $params['login_dep_label'])
            ->whereIn('job_status', [1, 2])->count();//部门的未处理
        $data['group']['processed'] = \DB::table('worksheet')->where('rec_dep_label', $params['login_dep_label'])
            ->whereIn('job_status', [3, 4])->count();//部门的已处理

        return $this->lang->set(0, [], $data);
    }

};