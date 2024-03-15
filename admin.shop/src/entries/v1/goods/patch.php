<?php
use Lib\Validate\BaseValidate;
use Logic\Admin\BaseController;

/**
 * 修改商品状态
 */
return new class extends BaseController
{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id = null)
    {
        $this->checkID($id);

        $data = DB::table('goods')->where('id', '=', $id)->first();
        if(!$data){
            return $this->lang->set(886, ['数据不存在']);
        }
        $result = DB::table('goods')->where('id', '=', $id)->update(['status'=>$data->status == 1 ? 2 : 1]);
        if ($result !== false) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }

};