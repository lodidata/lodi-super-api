<?php
use Logic\Admin\BaseController;

return new class() extends BaseController{
    const TITLE       = '第三方支付列表接口';
    const DESCRIPTION = '获取第三方支付列表';
    const QUERY       = [
        'page'      => 'int(required)   #页码',
        'page_size' => 'int(required)    #每页大小',
        'pay_channel'   => 'string(optional)   #渠道名称',
        'pay_scence'   => 'int(optional) #支付类型',
        'status'    => 'int(optional)   #状态，1 启用，0 停用',
          'sort'    => 'int(optional)   #排序'
    ];
    const TYPE        = 'text/json';
    const SCHEMAs     = [
        [
            'type'  => 'enum[rowset, row, dataset]',
            'size'  => 'unsigned',
            'total' => 'unsigned',
            'data'  => 'rows[id:int,app_id:string,channel_name:string,name:string,pay_scene:string,levels:string,type:string
                deposit_times:int, money_used:int, money_stop:int,money_day_stop:int,money_day_used:int,url_notify:string,url_return:string,
                created_uname:string,sort:int,status:set[enabled,default]]',
        ],
    ];
    //前置方法
    protected $beforeActionList = [
        'verifyToken','authorize'
    ];

    public function run(){
        $status = $this->request->getParam('status');//1表示启用
        $channel = $this->request->getParam('pay_channel');//第三方支付渠道code
        $scence = $this->request->getParam('pay_scence');//支付场景
        $sort = $this->request->getParam('sort');
        $page = $this->request->getParam('page') ?? 1;
        $size= $this->request->getParam('page_size') ?? 20;

        $params = array(
            'status' => $status,
            'pay_channel' => $channel,
            'pay_scence' => $scence,
            'sort' => $sort,
            'page' => $page,
            'page_size' => $size,
        );

        $rs =  Logic\Shop\Recharge::requestPaySit('getPayList',$params);
        $attr = array('number'=>$page,'size'=>$size,'tatal'=>0);
        return $this->lang->set(0,[],$rs['data'] ?? [],$rs['attributes'] ?? $attr);
    }
};
