<?php
use Logic\Admin\BaseController;
use Lib\Validate\Admin\AdvertValidate;
use Logic\Admin\Log;

return new class() extends BaseController {
    const TITLE       = '修改PC轮播广告/H5轮播广告状态';
    const DESCRIPTION = '申请、停用、启用';
    const HINT        = '状态：审核中、被拒绝、通过，停用、启用';
    const QUERY       = [];
    const TYPE        = 'text/json';
    const PARAMs      = [
        'type'        => 'string(required) #申请 applying，停用 disabled，启用 enabled',
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

    public function run() {
        (new AdvertValidate())->paramsCheck('post', $this->request, $this->response);//参数校验,新增
        $params = $this->request->getParams();
        $data = [
            'name' => $params['name'],//广告标题
            'pf' => $params['pf'] ? $params['pf'] : 'h5',//h5或pc
            'status' => $params['status'],//enabled 启用, disabled 禁用, deleted 删除
            'approve' => 'pass',
            'type' => 'banner',
            'position' => $params['position'] ? $params['position'] : 'home',//位置
            'picture' => $params['picture'],//图片链接
            'link_type' => $params['link_type'],//1 外部链接 2 站内活动
            'link' => $params['link'],//外链
            'sort' => $params['sort'],//排序
        ];
        $result = DB::table('advert')->insertGetId($data);
        if ($result) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};