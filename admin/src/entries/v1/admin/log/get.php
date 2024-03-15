<?php
/**
 * 后台操作日志列表
 *
 * @auth Jacky.Zhuo<zhuojiejie@funtsui.com>
 * @date 2018-07-18 16:52
 */

use Utils\Www\Action;
use Model\AdminLog;

return new class() extends Action
{
    const TITLE = '获取后台操作日志列表';
    const DESCRIPTION = '返回后台操作日志列表';
    
    const QUERY = [];

    const PARAMS = [
        'uname' => 'string() #操作者',
        'uname2' => 'string() #被操作者',
        'module' => 'enum[1,2,3,4,5,6,7,8]() #操作模块 0:彩票 1:注单 2:现金 3:用户 4:系统 5:APP 6:游戏 7:网站 8:活动',
        'status' => 'enum[0,1]() #状态 0:失败 1:成功',
        'page' => 'int() #当前页',
        'page_size' => 'int() #每页显示多少条',
        'date_from' => 'datetime() #查询起始日期',
        'date_to' => 'datetime() #查询失败日期',
    ];
    const SCHEMAS = [
        'ip' => 'string #操作者ip地址',
        'uid' => 'int #操作者id',
        'uname' => 'string #操作者账号',
        'uid2' => 'int #被操作者id',
        'uname2' => 'string #被操作者账号',
        'module' => 'int #模块',
        'module_child' => 'string #子模块',
        'fun_name' => 'string #功能名称',
        'type' => 'string #操作类型',
        'status' => 'enum[0,1] #状态 0:失败 1:成功',
        'remark' => 'string #详细信息',
    ];

    public function run()
    {
        $params = $this->request->getParams();

        $page = $params['page'] ?? 1;
        $page_size = $params['page_size'] ?? 20;

        $fields = [
            'uname', 'uname2', 'module', 'status','ip'
        ];

        $query = DB::table('admin_logs');
        foreach ($fields as $field) {
            if (!isset($params[$field])) {
                continue;
            }
            $query->where($field, $params[$field]);
        }

        if (isset($params['date_from'])) {
            $query->where('created', '>=', $params['date_from'] . ' 00:00:00');
        }

        if (isset($params['date_to'])) {
            $query->where('created', '<=', $params['date_to'] . ' 23:59:59');
        }

        $total = $query->count();
        $query->forPage($page, $page_size)
            ->orderBy('created', 'desc');

        $result = $query->get()
            ->toArray();

        $attributes['total'] = $total;
        $attributes['number'] = $page;
        $attributes['size'] = $page_size;

        return $this->lang->set(0, [], $result, $attributes);
    }
};
