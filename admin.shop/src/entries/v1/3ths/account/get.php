<?php
use Logic\Admin\BaseController;

return new class() extends BaseController {
    const TITLE = "银行帐号列表接口";
    const DESCRIPTION = "获取银行帐户信息列表";
    const TYPE = "text/json";
    const QUERY = [
        "name" => "string(optional) #收款户名",
        "bank_id" => "int #开户银行id",
        "level" => "int #用户层级",
        "status" => "string # 状态，enabled 启用，disabled 禁用",
        "page" => "int(optional)   #页码",
        "page_size" => "int(optional)    #每页大小"
    ];
    const SCHEMAs = [
        200 => [
            "id" => "帐户ID",
            "bank_name" => "银行/支付名称",
            "address" => "开启行",
            "accountname" => "string #户名",
            "card" => "帐号",
            "usage" => "使用层级",
            "limit_once_min" => "单笔最低",
            "limit_once_max" => "单笔最高",
            "today" => "今日累计",
            "limit_day_max" => "每日最大存款",
            "total" => "累计存款",
            "limit_max" => "总存款限制",
            "qrcode" => "二维码",
            "created" => "创建时间",
            "created_uname" => "创建人",
            "sort" => "排序",
            "state" => "集合信息,online:线上, default:默认, enabled:启用",
            "levels" => "array #层级"
        ]
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken', 'authorize'
    ];

    public function run(){
        $page = $this->request->getParam('page') ?? 1;
        $size= $this->request->getParam('page_size') ?? 20;
        $type = $this->request->getParam('type');//收款类型
        $bank_id = $this->request->getParam('bank_id');//银行id
        $name = $this->request->getParam('name');//户名
        $status = $this->request->getParam('state');//enabled：启用，default：停用

        $accounts = \DB::table('bank_account as a')
            ->leftJoin('bank','a.bank_id','=','bank.id')
            ->leftJoin('admin','a.creater','=','admin.id')
            ->whereRaw('!FIND_IN_SET("deleted", state)');
        $type && $accounts->where('a.type', '=', $type);
        $bank_id && $accounts->where('a.bank_id', '=', $bank_id);
        $name && $accounts->where('a.name', '=', $name);
        $status && $accounts->where('a.state', '=', $status);
        $accounts->select(['a.id','a.name','bank.name as bank_name','bank.id as bank_id',
            'a.address','a.card','a.created','admin.name as created_uname','a.limit_max',
            'a.limit_once_min','a.limit_once_max','a.limit_day_max','a.sort',
            'a.qrcode','a.state','a.total','a.today','a.type','a.comment'
        ]);
        $attributes['total'] = $accounts->count();
        $attributes['number'] = $page;
        $attributes['size'] = $size;
        $data = $accounts->orderBy('sort')->forPage($page,$size)->get()->toArray();
        $data = \Utils\Utils::RSAPatch($data);
        return  $this->lang->set(0, [], $data, $attributes);
    }
};

