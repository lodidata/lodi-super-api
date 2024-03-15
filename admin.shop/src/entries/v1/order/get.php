<?php
use Logic\Admin\BaseController;

/**
 * 商品订单列表
 */
return new class extends BaseController{
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run(){
        $params = $this->request->getParams();
        $stime = $this->request->getParam('start_time');
        $etime = $this->request->getParam('end_time');

        $sql = DB::table('goods_order')->orderBy('id', 'desc')->select(['*']);
        $sql = isset($params['name']) && !empty($params['name']) ? $sql->where('user_name', $params['name']) : $sql;
        $sql = isset($params['order_number']) && !empty($params['order_number']) ? $sql->where('order_number', $params['order_number']) : $sql;
        $stime && $sql->where('created','>=', $stime);
        $etime && $sql->where('created','<=',$etime.' 23:59:59');
        $total = $sql->count();
        $msg = $sql->forPage($params['page'], $params['page_size'])->orderBy('id','desc')->get()->toArray();
        foreach ($msg as $key=>$val){
            $msg[$key]->order_number = (string)$val->order_number;
        }
        return $this->lang->set(0, [], $msg, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    } 

};