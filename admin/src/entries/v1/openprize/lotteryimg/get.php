<?php
use Logic\Admin\BaseController;

return new class() extends BaseController{
    const TITLE       = '彩种图标';
    const DESCRIPTION = '';
    const HINT        = '';
    const QUERY       = [];
    const TYPE        = 'text/json';
    const PARAMs      = [];
    const SCHEMAs     = [];
    //前置方法
    protected $beforeActionList = [
         'verifyToken','authorize'
    ];

    public function run(){
        $params=$this->request->getParams();
        $query = \DB::connection('common')->table('lottery');
        if(isset($params['id']) && $params['id']){
            $query = $query->where('id', $params['id']);
        }
        return $query->get()->toArray();
    }
};
