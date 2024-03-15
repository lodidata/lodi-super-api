<?php
use Logic\Admin\BaseController;
use Lib\Validate\BaseValidate;
use Logic\Admin\Log;

return new class() extends BaseController{
    const TITLE = '修改PC轮播广告/H5轮播广告状态';
    const DESCRIPTION = '申请、停用、启用';
    const HINT = '状态：审核中、被拒绝、通过，停用、启用';
    const QUERY = [];
    const TYPE = 'text/json';
    const PARAMs = [
        'type' => 'string(required) #申请 applying，停用 disabled，启用 enabled',
        'language_id' => 'int(required) #语言id',
        'pf' => 'string(required) #平台（pc, h5）',
        'position' => 'string(required) #用于展示哪个位置(pc使用)，可选值，home,egame,live,lottery,sport,agent'
    ];
    const SCHEMAs = [
        200 => ['bool #是否修改成功']
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken', 'authorize'
    ];

    public function run($id = null){
        $this->checkID($id);
        (new BaseValidate([
            "name" => "require",
            "pf" => "require|in:pc,h5",
            "status" => "require|in:enabled,disabled,deleted",
            "position" => "require|in:home,egame,live,lottery,sport,agent",
            "picture" => "require|url",
            "link_type" => "require|in:1,2",
//            "link"=>"url",
            "sort" => "require|integer",
        ]))->paramsCheck('', $this->request, $this->response);

        $params = $this->request->getParams();
        $data = [
            'name' => $params['name'],//广告标题
            'pf' => $params['pf'] ? $params['pf'] : 'h5',//h5或pc
            'status' => $params['status'],//enabled 启用, disabled 禁用, deleted 删除
            'position' => $params['position'],//位置
            'picture' => $params['picture'],//图片链接
            'link_type' => $params['link_type'],//1 外部链接 2 站内活动
            'link' => $params['link'],//外链
            'sort' => $params['sort'],//排序
        ];
        $result = DB::table('advert')->where('id', '=', $id)->update($data);
        if ($result !== false) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};