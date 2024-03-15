<?php
/**
 * 管理员用户列表
 */

use Logic\Admin\BaseController;
use Lib\Validate\Admin\AdminValidate;
use Model\Admin\Admin;

return new class extends BaseController {
    const TITLE = '管理员列表';
    const PARAMS = [
        'name'   => 'string(optional) #用户名',
        'status' => 'integer(optional) #状态 0,停用，1，启用',
    ];
    const SCHEMAs = [
        200 => [
            'name'       => 'string#用户名',
            'status'     => 'integer#状态',
            'created_at' => 'string#创建时间',
        ],
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run() {
        (new AdminValidate())->paramsCheck('get', $this->request, $this->response);
        $params = $this->request->getParams();
        $adminModel = new Admin();
        $query = $adminModel::select(['id', 'name', 'truename', 'creater', 'status', 'created_at'])->where('id', '<>', 1);
        $query = isset($params['name']) && !empty($params['name']) ? $query->where('name', $params['name']) : $query;
        $query = isset($params['status']) && is_numeric($params['status']) ? $query->where('status', $params['status']) : $query;

        $attributes['total'] = $query->count();
        $attributes['number'] = $params['page'];
        $attributes['size'] = $params['page_size'];
        if (!$attributes['total']) {
            return [];
        }
        $result = $query->orderBy('created_at', 'desc')->forpage($params['page'], $params['page_size'])->get()->toArray();
        if(!empty($result)){
            foreach($result as &$val){
                $role_id = DB::table('admin_role_relation')->where('uid', $val['id'])->pluck('rid')->first();
                $val['role_id'] = $role_id ? $role_id : 0;
            }
        }
        return $this->lang->set(0, [], $result, $attributes);
    }
};