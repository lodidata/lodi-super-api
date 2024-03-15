<?php
/**
 * 发送对象和游戏类型获取
 * @author Taylor 2019-01-18
 */
use Logic\Admin\BaseController;

return new class extends BaseController
{
    const TITLE = '发送对象和游戏类型获取';
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
            'receive' => 'array 发送对象数组',
            'receive.name' => 'string 发送对象',
            'receive.customer' => 'string 发送对象类型',
            'menu' => 'array 一级类型菜单',
            'menu.id' => 'array 一级类型菜单id',
            'menu.type' => 'string 一级类型菜单类型',
            'menu.name' => 'string 一级菜单名称',
            'menu.second' => 'array 二级菜单',
        ]
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        //获取发送对象
        $receive = DB::table('customer')->where('type','game')
            ->orderBy('id','asc')->get(['id', 'name', 'customer'])->toArray();
        array_unshift($receive, ['id'=>0, 'type'=>'', 'name'=>'全部']);

        //一级菜单
        $menu = DB::table('game_menu')->where('pid', 0)->where('id','<>', 23)->get(['id', 'type', 'name'])->toArray();
        if(!empty($menu)){
            foreach($menu as &$m){
                $m->second = '';
                if($m->type != 'CP'){//彩票没有二级菜单
                    $s_menu = DB::table('game_menu')->where('pid', $m->id)->get(['id', 'type', 'name'])->toArray();
                    if(empty($s_menu)){
                        $m->second = '';
                    }else{
                        $m->second = $s_menu;
                    }
                }
            }
        }
        array_push($menu, ['id'=>0, 'menu'=>'其他', 'second'=> '']);

        return $this->lang->set(0, [], ['receive'=>$receive, 'menu'=>$menu], null);
    }
};