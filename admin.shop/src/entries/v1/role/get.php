<?php
/**
 * @author Taylor 2019-01-23
 */
use Logic\Admin\BaseController;
return new class() extends BaseController
{
    const TITLE       = '获取角色列表';
    const PARAMs      = [];
    const SCHEMAs = [
        200 => [
            'id' => 'integer#菜单id',
            'pid' => 'integer#父id',
            'title' => 'string#菜单名称',
            'children' => 'string#二级菜单',
            'children.leaf' => 'bool#true叶子节点，false非叶子节点',
            'children.checked' => 'bool#true已选中，false未选中',
        ]
    ];
    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run()
    {
        $params = $this->request->getParams();
        $table = DB::table('admin_role')
            ->leftjoin('admin', 'admin.id', '=', 'admin_role.operator')
            ->selectRaw('admin_role.id,
            admin_role.role,
            admin_role.num,
            admin_role.auth,
            admin_role.member_control,
            admin_role.addtime,
            admin.name');

        isset($params['id']) && $table = $table->where('admin_role.id' , $params['id']);
        isset($params['role']) && $table = $table->where('admin_role.role' , $params['role']);
        $attr['total'] = $table->count();
        $params['page_size'] = $params['page_size'];
        $data = $table->orderBy('id','desc')->forPage($params['page'], $params['page_size'])->get()->toArray();

        $attr['num'] = $params['page'];
        $attr['size'] = $params['page_size'];

        return $this->lang->set(0, '', $data, $attr);
    }
};