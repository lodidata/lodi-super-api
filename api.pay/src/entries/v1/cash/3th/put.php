<?php
use Utils\Www\Action;

return new class extends Action {

    //const STATE       = \Utils\Www::DRAFT;
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
        //  'sort'    => 'int(optional)   #排序'
    ];
    const TYPE        = 'text/json';

    public function run(){

        $return = array(
            'state' => 0 ,
            'message' => 'Fail'
        );
        $param = $this->request->getParams();

        $updata = array(
            'min_money' => $param['min_money'],
            'max_money' => $param['max_money'],
            'money_day_stop' => $param['money_day_stop'],
            'money_stop' => $param['money_stop'],
            'field' => $param['comment'] ? : '',
            'status' => $param['status'],
            'sort' => $param['sort'],
            'active_rule' => $param['active_rule'],
        );
        $updata['status'] = $updata['status'] == 'enabled'  ? 'enabled' : 'disabled';
        $rs = DB::table('passageway')->where('id','=',$param['id'])->update($updata);
        if($rs){
            $return['state'] = 1;
            $return['message'] = 'OK';
        }
        return $return;
   }



};