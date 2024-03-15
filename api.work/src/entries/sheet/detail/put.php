<?php
use Utils\Work\Action;

/**
 * 工单修改
 */
return new class extends Action{

    public function run($id){
        if(empty($id)){
            return $this->lang->set(10);
        }

        $w_data = (array)\DB::table('worksheet')->where('id', $id)->first();
        if(empty($w_data)){
            return $this->lang->set(886, ['工单不存在']);
        }
        $params = $this->request->getParams();
        $data['title'] = $params['title'];
        $data['desc'] = $params['desc'];
        $data['file_list'] = $params['file_list'];
        $data['project_label'] = $params['project_label'];
        $data['customer_label'] = $params['customer_label'];
        $data['level'] = $params['level'];
        if(!in_array($w_data['job_status'], [4])){
            $data['job_status'] = $params['job_status'];
        }
        $data['rec_dep_label'] = $params['rec_dep_label'];
        $data['rec_label'] = $params['rec_label'];
        $result = \DB::table('worksheet')->where('id', '=', $id)->update($data);
        if ($result !== false) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};