<?php
use Logic\Admin\BaseController;
use lib\validate\admin\AdvertValidate;

return new class() extends BaseController {
    const TITLE       = '修改PC轮播广告/H5轮播广告状态';
    const DESCRIPTION = '申请、停用、启用';
    const HINT        = '状态：审核中、被拒绝、通过，停用、启用';
    const TYPE        = 'text/json';
    const PARAMs      = [
        'type'        => 'string(required) #申请 applying，停用 disabled，启用 enabled',
        'language_id' => 'int(required) #语言id',
        'pf'          => 'string(required) #平台（pc, h5）',
        'position'    => 'string(required) #用于展示哪个位置(pc使用)，可选值，home,egame,live,lottery,sport,agent'
    ];
    const SCHEMAs     = [
        200 => ['bool #是否修改成功']
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run($id = null) {
        $this->checkID($id);

        $data = DB::table('advert')->where('id', '=', $id)->first();
        if(!$data){
            return $this->lang->set(886, ['数据不存在']);
        }
        $result = DB::table('advert')->where('id', '=', $id)->update(['status'=>$data->status == 'enabled' ? 'disabled' : 'enabled']);
        if ($result !== false) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};