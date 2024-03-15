<?php

namespace Logic\Admin;

use Logic\Logic;
use Model\Admin\AdminLog as LogModel;
use Utils\Client;

class Log extends Logic {

    const METHOD_DESCRIPTION = [
        'GET'    => '获取',
        'POST'   => '创建',
        'PUT'    => '修改',
        'PATCH'  => '修改',
        'DELETE' => '删除'
    ];


    const MODULE_NAME = [
        '账号',
        '游戏',
        '客户',
    ];

    const MODULE_USER = 0;

    const MODULE_GAME = 1;

    const MODULE_CUSTOMER = 2;

    /**
     * 新增操作记录
     *
     * @param int|null $uid2 被操作用户ID
     * @param string|null $uname2 被操作用户名
     * @param int $module 模块
     * @param string|null $module_child 子模块
     * @param string|null $fun_name 功能名称
     * @param string|null $type 操作类型
     * @param int $status 操作状态 0：失败 1：成功
     * @param string|null $remark 详情记录
     *
     * @return mixed
     */
    public function create($uid2 = null, $uname2 = null, $module, $module_child = null, $fun_name = null, $type = null, $status = 1, $remark = null) {
        global $playLoad;
        $data = [
            'ip'           => Client::getIp(),
            'uid'          => $playLoad['uid'] ?? 0,
            'uname'        => $playLoad['nick'] ?? '',
            'uid2'         => $uid2,
            'uname2'       => $uname2,
            'module'       => self::MODULE_NAME[$module],
            'module_child' => $module_child,
            'fun_name'     => $fun_name,
            'type'         => $type,
            'status'       => $status,
            'remark'       => $remark,
        ];

//        $log = new LogModel();
//        return $log->insertGetId($data);
        return \DB::table('admin_logs')->insertGetId($data);
    }

    /**
     * 写入log
     * @param string $method
     * @param int|null $target_uid 操作会员 可为空
     * @param string|null $target_nick 操作目标 可为空
     * @param int $module_type     子模块类型
     * @param string $module_child 子模块  如:系统设置
     * @param string $fun_name 调用方法 如：登录注册
     * @param string $remark 详细记录 如：修改xxx
     * @return int
     */
    public function log(string $method,int $target_uid = null,string  $target_nick = null,int $module_type, string $module_child, string $fun_name , string $remark){
        global $playLoad;
        $data = [
            'ip'           => Client::getIp(),
//            'uid'          => $playLoad['uid'],
//            'uname'        => $playLoad['nick'],
            'uid'          => 104,
            'uname'        => '测试',
            'uid2'         => $target_uid,
            'uname2'       => $target_nick,
            'module'       => $module_type,
            'module_child' => $module_child,
            'fun_name'     => $fun_name,
            'type'         => self::METHOD_DESCRIPTION[$method],// 根据不同方法判断
            'status'       => 1,
            'remark'       => $remark,
        ];

//        $log = new LogModel();
//        return $log->insertGetId($data);
        return \DB::table('admin_logs')->insertGetId($data);
    }
}