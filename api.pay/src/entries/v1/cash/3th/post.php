<?php
use Utils\Www\Action;

/**
 * 第三方支付列表接口
 */
return new class extends Action {

    const TITLE       = '第三方支付列表接口';
    const DESCRIPTION = '获取第三方支付列表';
    const HINT        = '';
    const QUERY       = [
        // 'page'      => 'int(required)   #页码',
        //  'page_size' => 'int(required)    #每页大小',
        // 'channel'   => 'string(optional)   #渠道名称',
        // 'app_id'    => 'string(optional) #第三方支付 商户编号',
        // 'type'      => 'int(optional) #支付类型',
        //  'status'    => 'int(optional)   #状态，1 启用，0 停用'
    ];
    const TYPE        = 'text/json';

    public function run(){

        $id = $this->request->getParam('id','');

        $data =  DB::table('passageway')->where('id','=',$id)->first([
                'id','name','min_money','max_money','money_stop','money_day_stop','field AS comment','active_rule',
                'field as levels','status','sort',
                \DB::raw('FIND_IN_SET("disabled",status) > 0 AS `default`'),
                \DB::raw('FIND_IN_SET("enabled",status) > 0 AS status'),
                \DB::raw('status AS state'),
            ]);
        $return = array(
            'status'=> 0 ,
            'message'=> 'OK',
            'data'=> $data
        );
        print_r(json_encode($return));  die;
   }

};