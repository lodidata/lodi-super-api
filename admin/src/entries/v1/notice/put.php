<?php
/**
 * 超管后台公告添加
 * @author Taylor 2019-01-18
 */
use Lib\Validate\BaseValidate;
use Logic\Admin\BaseController;

return new class extends BaseController
{
    const TITLE = '后台公告修改';
    const TYPE = 'text/json';
    const PARAMs = [
        'id' => 'integer(required)#消息id',
        'customer_id' => 'integer(required)#发送对象即客户ID，0表示全部',
        'menu_id' => 'integer(required)#一级菜单id，0表示其他',
        'game_id' => 'integer(required)#二级菜单，游戏id，0表示没有二级菜单',
        'title' => 'string(required)#标题',
        'content' => 'string(required)#内容',
        'start_time' => 'string(required)#开始时间',
        'end_time' => 'string(required)#结束时间'
    ];
    const SCHEMAs = [
        200 => []
    ];

    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id)
    {
        if(empty($id)){
            return $this->lang->set(886, ['id不能为空']);
        }
        (new BaseValidate([
            'customer_id' => 'require',
            'menu_id' => 'require|integer',
            'game_id' => 'require|integer',
            'title' => 'require',
            'content' => 'require',
            'start_time' => 'require',
            'end_time' => 'require',
        ]))->paramsCheck('', $this->request, $this->response);
        $params = $this->request->getParams();
        $status = DB::table('super_notice')->where('id', $id)->update([
            'customer_id'=>$params['customer_id'],
            'menu_id'=>$params['menu_id'],
            'game_id'=>$params['game_id'],
            'title'=>$params['title'],
            'content'=>$params['content'],
            'start_time'=>$params['start_time'],
            'end_time'=>$params['end_time'],
            ]);
        if($status !== false){
            return $this->lang->set(0);
        }else{
            return $this->lang->set(-2);
        }
    }
};