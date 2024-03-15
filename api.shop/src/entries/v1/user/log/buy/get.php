<?php
use Utils\Shop\Action;

return new class extends Action {
    const TITLE = "GET 查询 充值记录";
    const TYPE = "text/json";
    const QUERY = [
       "start_time" => "int() #查询开始日期",
       "end_time" => "int() #查询结束日期",
       "page" => "int() #当前第几页",
       "page_size" => "int() #每页数目",
    ];

    public function run() {
        $verify = $this->auth->verfiyToken();
        if (!$verify->allowNext()) {
            return $verify;
        }
        $page = $this->request->getParam('page', $this->page);
        $pageSize = $this->request->getParam('page_size', $this->page_size);
        $stime = $this->request->getParam('start_time');
        $etime = $this->request->getParam('end_time');

        $userId = $this->auth->getUserId();
        $query = \DB::table('goods_order')->where('user_id', $userId);
        $stime && $query->where('created','>=', $stime);
        $etime && $query->where('created','<=',$etime.' 23:59:59');
        $total = $query->count();
        $data = $query->forPage($page, $pageSize)->orderBy('id','desc')->get()->toArray();
        return $this->lang->set(0, [], $data, [
            'number' => $page, 'size' => $pageSize, 'total' => $total
        ]);
    }
};