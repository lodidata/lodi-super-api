<?php
use Utils\Work\Action;

/**
 * 工单列表
 */
return new class extends Action{

    public function run(){
        $params = $this->request->getParams();
        if(empty($params['id'])){
            return $this->lang->set(10);
        }
        $data = \DB::table('worksheet')->where('id', $params['id'])->get();
        return $this->lang->set(0, [], $data);
    }

};