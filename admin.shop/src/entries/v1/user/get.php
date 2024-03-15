<?php
use Logic\Admin\BaseController;

/**
 * 客户信息查询
 */
return new class extends BaseController{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run(){
        $params = $this->request->getParams();
        $sql = DB::table('user')->orderBy('id', 'desc')->select(['*']);

        //用户名模糊搜索
        $sql = isset($params['name']) && !empty($params['name']) ? $sql->where('name', 'like', "%{$params['name']}%") : $sql;
        //创建时间搜索
        $sql = isset($params['created_start']) && !empty($params['created_start']) ? $sql->where('created', '>=', $params['created_start']) : $sql;
        $sql = isset($params['created_end']) && !empty($params['created_end']) ? $sql->where('created', '<=', $params['created_end']) : $sql;

        $total = $sql->count();
        $msg = $sql->forPage($params['page'], $params['page_size'])->get()->toArray();
        $data = [];
        foreach ($msg as $key=>$val){
            $val = (array)$val;
            $tmp['id'] = $val['id'];
            $tmp['name'] = $val['name'];
            $tmp['origin'] = $val['origin'];
            $tmp['ip'] = $val['ip'];
            $tmp['login_ip'] = $val['login_ip'];
            $tmp['last_login'] = $val['last_login'];
            $tmp['first_recharge_time'] = $val['first_recharge_time'] ? $val['first_recharge_time'] : '';
            $tmp['state'] = $val['state'];
            $tmp['created'] = $val['created'];
            $tmp['updated'] = $val['updated'];
            $tmp['balance'] = DB::table('funds')->where('id', $val['wallet_id'])->value('balance');
            $data[] = $tmp;
        }
        return $this->lang->set(0, [], $data, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    }
};