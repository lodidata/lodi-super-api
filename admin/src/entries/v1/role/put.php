<?php
/**
 * @author Taylor 2019-01-24
 */
use Logic\Admin\BaseController;
use Lib\Validate\BaseValidate;
use Logic\Admin\Log;

return new class() extends BaseController {
    const TITLE = '修改角色';
    const PARAMS = [
        'id'=>'角色id',
        'auth'=>'string#权限菜单叶子节点id列表',
        'role'=>'string#菜单名称',
    ];
    const SCHEMAS = [
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id) {
        $this->checkID($id);
        $validate = new BaseValidate([
            'auth' => 'require',
            'role' => 'require|length:2,10',
        ], [
                'role.length' => '角色名称长度为2-10位',
                'auth'        => '请至少勾选一个权限',
            ]
        );
        $validate->paramsCheck('', $this->request, $this->response);

        $data = $this->request->getParams();
        $auth = $data['auth'];
        $role = $data['role'];
        $memberControl = $data['member_control'];
        $memberControl['user_search_switch'] = $data['user_search_switch'];

        $info = DB::table('admin_role')->find($id);
        $res = DB::table('admin_role')->where('id', $id)->update(['auth' => $auth, 'role' => $role, 'member_control' => json_encode($memberControl), 'operator' => $this->playLoad['uid']]);
        if($res !== false){
        $type = "编辑";
        $str = "角色名称：{$info->role}";
        $sta = $res !== false ? 1 : 0;
        (new Log($this->ci))->create(null, null, Log::MODULE_USER, '管理员角色', '管理员角色', $type, $sta, $str);
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};