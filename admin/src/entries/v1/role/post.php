<?php
/**
 * @author Taylor 2019-01-25
 */
use Logic\Admin\BaseController;
use Lib\Validate\BaseValidate;
use Logic\Admin\Log;

return new class() extends BaseController {
    const TITLE = '新增角色';
//    {"auth":"112,121","role":"测试权限","member_control":{"true_name":false,"bank_card":false,"address_book":false,"user_search_switch":false},"user_search_switch":false}
    const PARAMs = [
        'auth'=>'string#权限菜单叶子节点id列表',
        'role'=>'string#菜单名称',
    ];
    const SCHEMAs = [
        "200" => '操作成功'
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run() {
        $validate = new BaseValidate([
            'role' => 'require|length:2,10',
            'auth' => 'require',
        ], [
                'auth'        => '请至少勾选一个权限',
                'role.length' => '角色名称长度为2-10位',
            ]
        );
        $validate->paramsCheck('', $this->request, $this->response);

        $data = $this->request->getParams();
        $auth = $data['auth'];//叶子节点列表
        $role = $data['role'];//菜单名称
        $memberControl = $data['member_control'];
        $memberControl['user_search_switch'] = $data['user_search_switch'];

        //新增
        $res = DB::table('admin_role')->insert(['auth' => $auth, 'role' => $role, 'member_control' => json_encode($memberControl), 'operator' => $this->playLoad['uid']]);
        if($res !== false){
        $type = "新增角色";
        $str = "角色名称：$role";
        $sta = $res !== false ? 1 : 0;
        (new Log($this->ci))->create(null, null, Log::MODULE_USER, '管理员角色', '管理员角色', $type, $sta, $str);
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};