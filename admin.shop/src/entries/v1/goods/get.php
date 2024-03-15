<?php
use Logic\Admin\BaseController;

/**
 * 商品信息
 */
return new class extends BaseController
{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run()
    {
        $params = $this->request->getParams();
        $sql = DB::table('goods')->orderBy('id', 'desc')->select(['*']);

        $sql = isset($params['name']) && !empty($params['name']) ? $sql->where('name', $params['name']) : $sql;

        $total = $sql->count();
        $msg = $sql->forPage($params['page'], $params['page_size'])->orderBy('id','desc')->get()->toArray();
        return $this->lang->set(0, [], $msg, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    } 

};