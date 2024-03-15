<?php
use Logic\Admin\BaseController;
use Logic\Admin\Log;

return new class() extends BaseController {
    const TITLE       = '删除轮播广告';
    const DESCRIPTION = 'PC轮播广告/H5轮播广告';
    const QUERY       = [
        'id' => 'int #轮播广告id'
    ];
    const TYPE        = 'text/json';
    const SCHEMAs     = [
        200 => ['bool #是否删除成功']
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run($id = null) {
        $this->checkID($id);
        $info = DB::table('advert')->find($id);
        $result = DB::table('advert')->where('id', '=', $id)->update(['status'=>'deleted']);
        /*============================日志操作代码================================*/
        $sta = $result !== false ? 1 : 0;
        $type = $info->pf=='pc' ? 'PC' : '移动端';
        (new Log($this->ci))->create(null, null, Log::MODULE_WEBSITE, '轮播广告', $type, "删除", $sta, "广告名称：{$info->name}");
        /*============================================================*/
        if ($result !== false) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};