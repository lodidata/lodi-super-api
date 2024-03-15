<?php
use Utils\Www\Action;

/**
 * 第三方支付详情
 */
return new class extends Action {

    const TITLE       = '第三方支付详情';
    const DESCRIPTION = '第三方支付详情列表';
    const HINT        = '';
    const QUERY       = [
        //        'id'             => 'int(required) #支付接口ID',
        //        'channel_id'     => 'int(required) #渠道ID',
        //        'app_id'         => 'string(required) #应用ID',
        //        'pay_scene'      => 'string(required) #场景',
        //        'levels'         => 'string(optional) #会员等级',
        //        'deposit_times'  => 'int #提款次数',
        //        'money_stop'     => 'int #累计停用金额',
        //        'money_day_stop' => 'int #日停用金额',
        //        'sort'           => 'int #排序',
        //        'status'         => 'bool #状态集合',
    ];
    const TYPE        = 'text/json';

    public function run(){

        $req = $_GET;   //获取get请求参数

        if (isset($req['status'])) {
            $status = $req['status'] ? 'enabled' : 'default';
        }

        $params = [
            'select'    => '*',
            'page'      => $req['page'] ?? 1,
            'page_size' => $req['page_size'] ?? 99999999999,
            'condition' => [
                'status' => $status ?? null,
                'channel'   => $req['pay_channel']  ?? null,
                'type'      => $req['type']  ?? null,
                'pay_scene' => $req['pay_scence']  ?? null],
            'order'     => 'sort asc',
        ];

        $rs = $this->getPayArray($params);

        $reData = array();
        $reData['state']      = 0;
        $reData['message']    = 'OK';
        $reData['type']       = 'rows';
        $reData['attributes'] = array(
            'number'=> $rs['current_page'],
            'size'  => $rs['per_page'],
            'total' => $rs['total']
        );
        $pay_types = \Logic\Recharge\Recharge::$sceneType['scene'];
        $tArr = array();
        foreach($rs['data'] as $k => $v){
            $tArr[$k] = $v;
            $tArr[$k]->type = $pay_types[$v->pay_scene];

        }

        $reData['data'] =$tArr;

        return $reData;
   }

   //查询数据
   public function getPayArray($params = array()){

       $table = DB::table('customer AS c');

       $condition = $params['condition'];
       $table = $table->select(['pw.id as id','c.id as cid','pcg.app_id','pcg.partner_id','pw.name','pw.scene as pay_scene','pw.field as comment', 'pw.bank_data',
           'pw.return_type','pw.min_money','pw.max_money','pw.creater','pc.name as channel','pw.active_rule',
           'pw.money_used','pw.money_stop','pw.money_day_used','pw.money_day_stop','pw.created','pcg.terminal','pw.status','pc.code','pw.sort',
           \DB::raw('pc.name AS channel'),
           \DB::raw('find_in_set("disabled",pw.status) > 0 AS is_default'),
           \DB::raw('find_in_set("enabled",pw.status) > 0 AS is_enabled'),]
       )->where('pw.status','!=','deleted');
       //客户
       $table->where('c.customer', CUSTOMER);
       //配置状态
       if(isset($condition['status'])){
           $condition['status']  = $condition['status'] == 'enabled' ? 'enabled' : 'disabled';
           $table = $table->where('pw.status', $condition['status']);
       }
       //渠道代码
       isset($condition['channel']) && $table = $table->where('pc.id', $condition['channel']);
       //支付类型
       isset($condition['pay_scene']) && $table = $table->where('pw.scene', $condition['pay_scene']);

       $table = $table->join('passageway AS pw','c.id','=','pw.customer_id');
       $table = $table->join('pay_config AS pcg','pw.pay_config_id','=','pcg.id');
       $table = $table->join('pay_channel AS pc','pc.id','=','pcg.channel_id');

       return $table->paginate($params['page_size'], ['*'], 'page',$params['page'] )->toArray();

   }

};