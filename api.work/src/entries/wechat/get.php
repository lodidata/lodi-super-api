<?php
use Utils\Work\Action;
use Logic\Work\WeChat;

/**
 * 公众号列表
 */
return new class extends Action{

    public function run(){
        $params = $this->request->getParams();

        $query= \DB::table('wechat')->orderBy('id','DESC');

        $total = $query->count();
        $data = $query->forPage($params['page'], $params['page_size'])->get()->toArray();
        $logic = new WeChat($this->ci);
        foreach($data as $key=>&$val){
            $val->today_times = $logic->getToday($val->app_id);
        }

        $attr = [
            'page' => $params['page'],
            'page_size' => $params['page_size'],
            'total' => $total,
        ];
        return $this->lang->set(0, [], $data, $attr);
    }

};