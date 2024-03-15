<?php
/**
 * 滚动的公告列表
 * @author Taylor 2019-01-19
 */
use Logic\Admin\BaseController;

return new class extends BaseController
{
    const TITLE = 'GET 公告列表';
    const TYPE = 'text/json';
    const PARAMs = [
        'customer' => 'string(required)#厅主id',
    ];
    const SCHEMAs = [
        200 => [
            'id' => 'integer#消息id',
            'admin_uid' => 'integer#管理员id',
            'admin_name' => 'integer#管理员用户名',
            'customer_id' => 'integer#发送对象即客户ID，0表示全部',
            'menu_id' => 'integer#一级菜单id，0表示其他',
            'game_id' => 'integer#二级菜单，游戏id，0表示没有二级菜单',
            'title' => 'string#标题',
            'content' => 'string#内容',
            'start_time' => 'string#开始时间',
            'end_time' => 'string#结束时间',
            'pub_time' => 'string#发布时间',
            'status' => 'int#状态（1：发布，0：未发布）',
            'created' => 'string#创建时间',
            'updated' => 'string#更新时间',
        ]
    ];

    public function run()
    {
        $params = $this->request->getParams();
        $c_name = DB::table('customer')->where('customer', $params['customer'])->first(['id', 'name']);
        if(empty($c_name)){
            return $this->lang->set(886, ['customer不能为空']);
        }
        $sql = DB::table('super_notice')->orderBy('id','desc');
        $sql = $sql->where('status', 1)->whereIn('customer_id', [0, $c_name->id]);//查询发送对象
        $sql = $sql->where('end_time', '>=', date('Y-m-d H:i:s'));
        $data = $sql->orderBy('id', 'desc')->get()->toArray();
        $customer['0'] = '全部';
        $customer[$c_name->id] = $c_name->name;
        $menu = ['0'=>'其他'];
        $game = ['0'=>''];
        foreach($data as $key=>&$val){
            //获取发送对象
            $val->customer_name = $customer[$val->customer_id];

            //获取一级菜单
            if(isset($menu[$val->menu_id])){
                $val->menu_name = $menu[$val->menu_id];
            }else{
                $c = DB::table('game_menu')->where('id', $val->menu_id)->first(['name']);
                $val->menu_name = $menu[$val->menu_id] = $c->name;
            }
            //获取二级菜单
            if(isset($game[$val->game_id])){
                $val->game_name = $game[$val->game_id];
            }else{
                $c = DB::table('game_menu')->where('id', $val->game_id)->first(['name']);
                $val->game_name = $game[$val->game_id] = $c->name;
            }
        }
        return $this->lang->set(0, [], $data);
    }
};