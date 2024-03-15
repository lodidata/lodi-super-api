<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 16:31
 */

return new class extends Logic\Admin\BaseController
{
    const TITLE = '彩种列表';
    const DESCRIPTION = '彩种列表';
    const HINT = '';
    const QUERY = [

    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [

        ]
    ];
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];
    public function run()
    {
        (new \Lib\Validate\Admin\PayValidate())->paramsCheck('get',$this->request,$this->response);
            $sql = DB::connection('common')->table('lottery')->select(DB::raw('id,name'));
            $values = '';
            $sql=$sql->where('switch','!=',$values);
            $data = $sql->get()->toArray();
            return $this->lang->set(0,[],$data);

    }
};