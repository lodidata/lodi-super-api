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
        if(empty($params['lottery_id'])){
            return $this->lang->set(886, ['彩种id不能为空']);
        }
        $query = \DB::connection('common')->table('lottery_info')->orderBy('lottery_number','desc');
        $query = $query->where('lottery_type', $params['lottery_id']);
        if(isset($params['lottery_number']) && $params['lottery_number']){
            $query = $query->where('lottery_number', $params['lottery_number']);
        }
        $query = $query->where('end_time', '<', time());
//        $status = "FIND_IN_SET('open',state)";
//        $query = $query->whereRaw($status);
        $total = $query->count();
        $data = $query->forPage($params['page'], $params['page_size'])->get()->toArray();
        foreach ($data as &$datum) {
            $datum = (array)$datum;
            $datum['official_time'] = $datum['official_time'] ? date('Y-m-d H:i:s', $datum['official_time']) : '';
            $datum['catch_time']    = $datum['catch_time']  ? date('Y-m-d H:i:s', $datum['catch_time']) : '';
            $datum['start_time']    = $datum['start_time']  ? date('Y-m-d H:i:s', $datum['start_time']) : '';
            $datum['end_time']    = $datum['end_time']  ? date('Y-m-d H:i:s', $datum['end_time']) : '';
        }
        $attr = [
            'page' => $params['page'],
            'page_size' => $params['page_size'],
            'total' => $total,
        ];
        return $this->lang->set(0, [], $data, $attr);
    }
};
