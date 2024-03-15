<?php
/**
 * 后台操作日志模块列表
 *
 * @auth Jacky.Zhuo<zhuojiejie@funtsui.com>
 * @date 2018-07-18 16:52
 */

use Logic\Admin\Log;
use Utils\Www\Action;
use Model\AdminLog;

return new class() extends Action {
    const TITLE = '获取后台操作日志模块列表';
    const DESCRIPTION = '返回后台操作日志模块列表';
    
    const QUERY = [];

    const PARAMS = [

    ];
    const SCHEMAS = [];

    public function run() {
        return \DB::table('admin_role_auth')->where('pid',0)->get([
            'name AS id',
            'name',
        ])->toArray();
    }
};
