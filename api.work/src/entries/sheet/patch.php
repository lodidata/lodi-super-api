<?php
use Utils\Work\Action;

/**
 * 接单
 */
return new class extends Action{

    public function run($id){
        if(empty($id)){
            return $this->lang->set(10);
        }
        $params = $this->request->getParams();
        $data = DB::table('worksheet')->where('id', '=', $id)->value('job_status');
        if(!empty($data) && $data == 1){
            $result = DB::table('worksheet')->where('id', '=', $id)->update(['job_status'=>2, 'rec_label'=>$params['rec_label']]);
            if ($result !== false) {
                return $this->lang->set(0);
            }
            return $this->lang->set(-2);
        }else{
            return $this->lang->set(886, ['未处理的工单才能接单']);
        }
    }

};