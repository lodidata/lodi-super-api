<?php
use Logic\Admin\BaseController;

return new class() extends BaseController{
    const TITLE       = '获取支付渠道描述列表';
    const QUERY       = [
        'id' => 'int(, 30)',
        'type_id' => 'int(, 30)',
        'title' => 'string(require, 50)',#,
        'desc' => 'string(require, 50)',#,
        'status' => 'int()#停用，启用1',
    ];
    const TYPE        = 'text/json';
    public $channel = [1=>'网银',2=>'支付宝',3=>'微信',4=>'QQ',5=>'京东'];
    public $show = ['online'=>'线上','offline'=>'线下'];

    public function run(){
        $data = \DB::table('funds_channel')->orderBy('sort')->get()->toArray();
        foreach ($data as &$val){
            $val->type_name = $this->channel[$val->type_id] ?? '';
            $val->show_name = $this->show[$val->show] ?? '';
            $val->status_name = $val->status ? '启用' : '停用';
        }
        return $data;
    }
};
