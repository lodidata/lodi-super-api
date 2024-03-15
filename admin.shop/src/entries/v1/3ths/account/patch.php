<?php
use Logic\Admin\BaseController;
//use Logic\Admin\Log;

return new class() extends BaseController{
    const TITLE = "修改银行帐户(目前只支持状态修改)";
    const TYPE = "text/json";
    const PARAMs = [
        "status" => "int(required) #状态，启用 1，停用 0"
    ];
    const QUERY = [
        "id" => "int(required) #银行账户id"
    ];
    const SCHEMAs = [
        200 => [
            "id" => "int    #帐户ID",
            "status" => "enum[enabled,disabled] #状态"
        ]
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken', 'authorize'
    ];

    public function run($id){
        $this->checkID($id);
        if ($this->request->getParam('status')) {
            $status = 'enabled';
        } else {
            $status = 'default';
        }

        /*---操作日志代码---*/
        $data = DB::table('bank_account')->where('id', '=', $id)->get()->first();
        if(empty($data)){
            return $this->lang->set(886, ['收款账户不存在']);
        }
        $data = (array)$data;
        /*---操作日志代码---*/
        if (DB::table('bank_account')->where('id', '=', $id)->update(['state' => $status]) !== false){
            /*================================日志操作代码=================================*/
//            $types = [
//                '1' => '银行转账',
//                '2' => '支付宝',
//                '3' => '微信',
//                '4' => 'QQ钱包',
//                '5' => '京东支付',
//            ];
//            $card = \Utils\Utils::RSADecrypt($data['card']);
//            if ($this->request->getParam('status')) {
//                $sta = '启用';
//                $old_sta="停用";
//            } else {
//                $sta= '停用';
//                $old_sta="启用";
//            }
//            (new Log($this->ci))->create(null, null, Log::MODULE_CASH, '收款账号', '收款账号', $sta, 1, "类型:{$types[$data['type']]}/户名：{$data['name']}/账号： $card  /[$old_sta]更改为：[$sta]");
            /*==================================================================================*/
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};
