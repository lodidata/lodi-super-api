<?php
use Logic\Admin\BaseController;

/**
 * 修改用户状态
 */
return new class extends BaseController{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id = null){
        $this->checkID($id);

        $data = DB::table('user')->where('id', '=', $id)->first();
        if(!$data){
            return $this->lang->set(886, ['数据不存在']);
        }
        $result = DB::table('user')->where('id', '=', $id)->update(['state'=>$data->state == 1 ? 0 : 1]);
        if ($result !== false) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }

};