<?php
/**
 * @author Taylor 2019-01-23
 */
use Logic\Admin\BaseController;
return new class() extends BaseController
{
    const TITLE       = '获取一级/二级菜单列表';
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

    public function run($id = null)
    {
        //菜单列表
        $memu = \DB::table('admin_role_auth')->orderBy('sort','ASC')->get([
            'id', 'pid', 'name AS title',
        ])->toArray();
        //角色表
        $auth_role = $id ? (array)\DB::table('admin_role')->where('id',$id)->first(['auth','member_control']) : ['auth'=>'','member_control'=>''];
        //会员真实姓名，会员银行信息，会员联系信息
        $memberControl = ["true_name"=> false, "bank_card"=> false, "address_book"=> false, "user_search_switch"=> false];

        $memu = \Utils\PHPTree::makeTree($memu, [], explode(',', $auth_role['auth']));
        $tmp = json_decode($auth_role['member_control'],true) ?? [];
        $memberControl = array_merge($memberControl, $tmp);
        return ['auth'=>$memu, 'user'=>$memberControl, 'user_search_switch'=>$memberControl['user_search_switch'] ?? false];
    }
};