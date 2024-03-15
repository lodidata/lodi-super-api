<?php
use Utils\Www\Action;

return new class extends Action {

    const TITLE       = '第三方支付名称列表code';
    const DESCRIPTION = '第三方支付名称列表code';
    const HINT        = '';
    const QUERY       = [
    ];
    const TYPE        = 'text/json';

    public function run(){
        $channel = $this->request->getParam('channel');
        $channel_id = $this->request->getParam('channel_id');
        $pay_scene = $this->request->getParam('pay_scene');
        $status = $this->request->getParam('status');
        $table = DB::table('customer AS c');

        $table = $table->select(['pw.id as id','c.id as cid','pcg.app_id','pw.name','pw.scene as pay_scene',
                'pw.return_type','pw.min_money','pw.max_money','pw.creater','pc.name as channel',
                'pw.money_used','pw.money_stop','channel_id','pw.money_day_used','pw.money_day_stop','pw.created','pcg.terminal','pw.status','pc.code','pw.sort',
                \DB::raw('pc.name AS channel'),
                \DB::raw('find_in_set("default",pw.status) > 0 AS is_default'),
                \DB::raw('find_in_set("enabled",pw.status) > 0 AS is_enabled'),]
        );
        $channel && $table->where('pc.code',$channel);
        $channel_id && $table->where('pc.id',$channel_id);
        $pay_scene && $table->where('pw.scene',$pay_scene);
        if($status == 'disabled') {
            $table->where('pw.status', '!=', 'enabled');
        }elseif($status == 'enabled'){
            $table->where('pw.status','=', 'enabled');
        }
        //客户
        $data=$table->where('c.customer', CUSTOMER)
            ->join('passageway AS pw','c.id','=','pw.customer_id')
            ->join('pay_config AS pcg','pw.pay_config_id','=','pcg.id')
            ->join('pay_channel AS pc','pc.id','=','pcg.channel_id')
            ->get()
            ->toArray();

        $pay_types = \Logic\Recharge\Recharge::$sceneType['scene'];
        $tArr = array();
        foreach($data as $k => $v){
            $tArr[$k] = $v;
            $tArr[$k]->type = $pay_types[$v->pay_scene];

        }

        $reData['data'] =$tArr;

        return $reData;
   }

};