<?php
use Utils\Work\Action;
use Lib\Validate\BaseValidate;

/**
 * 提交工单
 */
return new class extends Action{

    public function run(){
        $validate = new BaseValidate([
            'title'   => 'require',
            'desc'    => 'require',
            'project_label' => 'require',
//            'customer_label'  => 'require',
            'level'   => 'require|in:1,2,3,4',
            'job_status'   => 'require|in:1,2,3,4',
            'rec_dep_label'   => 'require',
//            'rec_label'   => 'require',
            'sen_dep_label'   => 'require',
            'sen_label'   => 'require',
        ], [
            'title'    => '工单标题不能为空',
            'desc'    => '工单描述不能为空',
            'project_label' => '所属项目标识不能为空',
//            'customer_label'  => '所属客户标识不能为空',
            'level'  => '优先级不存在',
            'job_status'  => '工单状态不存在',
            'rec_dep_label'   => '受理单位标识不能为空',
//            'rec_label'   => '受理人标识不能为空',
            'sen_dep_label'   => '发送单位标识不能为空',
            'sen_label'   => '发送人标识不能为空',
        ]);
        $validate->paramsCheck('', $this->request, $this->response);

        $data = $this->request->getParams();
        $data['task_no'] = date('His').random_int(100, 999);//工单编号

        \DB::table('worksheet')->insertGetId($data);
        $this->lang->set(0);
    }

};