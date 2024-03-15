<?php
use Utils\Shop\Action;

/**
 * 商品信息
 */
return new class extends Action{
    public function run(){
        $params = $this->request->getParams();
        $params['page'] = isset($params['page']) && !empty($params['page']) ? $params['page'] : $this->page;
        $params['page_size'] = isset($params['page_size']) && !empty($params['page_size']) ? $params['page_size'] : $this->page_size;
        $sql = DB::table('goods')->where('status', 1)->orderBy('id', 'desc')
            ->select(['id','name','customer_name','logo','down_url','price','before_prob','after_prob','start_time','end_time']);
//        $sql = isset($params['name']) && !empty($params['name']) ? $sql->where('name', $params['name']) : $sql;
        $total = $sql->count();
        $msg = $sql->forPage($params['page'], $params['page_size'])->orderBy('id','desc')->get()->toArray();
        return $this->lang->set(0, [], $msg, ['number' => $params['page'], 'size' => $params['page_size'], 'total' => $total]);
    }
};