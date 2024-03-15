<?php
use Utils\Work\Action;

/**
 * 启用和禁用
 */
return new class extends Action{

    public function run($id){
        if(empty($id)){
            return $this->lang->set(10);
        }
        $data = \DB::table('wechat')->where('id', '=', $id)->value('account_status');
        if(!empty($data)){
            $account_status = $data == 1 ? 2 : 1;
            $result = \DB::table('wechat')->where('id', '=', $id)->update(['account_status'=>$account_status]);
            if ($result !== false) {
                return $this->lang->set(0);
            }
            return $this->lang->set(-2);
        }
    }

};