<?php
use Lib\Validate\BaseValidate;
/**
 * 超管后台公告添加
 * @author Taylor 2019-01-18
 */
return new class extends Logic\Admin\BaseController
{
    const TITLE = '超管后台公告添加';
    const PARAMs = [
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

    public function run()
    {
        $this->verifyToken();
        (new BaseValidate([
//            'type'=>'require|in:stat,base,balance,withdraw,bank',
            'customer_id' => 'require',
            'menu_id' => 'require|integer',
            'game_id' => 'require|integer',
            'title' => 'require',
            'content' => 'require',
            'start_time' => 'require',
            'end_time' => 'require',
        ]))->paramsCheck('', $this->request, $this->response);

        $params = $this->request->getParams();
        $data['admin_uid'] = $this->playLoad['uid'];
        $data['admin_name'] = $this->playLoad['nick'];
        $data['customer_id'] = $params['customer_id'];
        $data['menu_id'] = $params['menu_id'];
        $data['game_id'] = $params['game_id'];
        $data['title'] = $params['title'];
        $data['content'] = $params['content'];
        $data['start_time'] = $params['start_time'];
        $data['end_time'] = $params['end_time'];
        $result = DB::table('super_notice')->insertGetId($data);
        if ($result) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};