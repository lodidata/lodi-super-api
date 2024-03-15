<?php
use Logic\Admin\BaseController;

return new class() extends BaseController {
    const TITLE = "新增收款银行帐号";
    const DESCRIPTION = "修改银行帐户";
    const TYPE = "text/json";
    const PARAMs = [
        "name"          => "string #账户名",
        "card"          => "string(required) #帐号",
        "qrcode"        => "string(optional)   #二维码，如果没有二维码传空(null)",
        "limit_day_max" => "int(required) #每日最大存款",
        "limit_max"     => "int(required) #总存款限额",
        "sort"          => "int # 排序",
        "is_enabled"    => "int #启用 1，停用 0",
        "comment"       => "string(required) #存款说明",
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run() {
        $param = $this->request->getParams();
        $validate = new \Lib\Validate\BaseValidate([
            'type'   => 'require',//类型
            'qrcode' => 'url',//收款二维码
//            'address' => 'require',//开户行
            'name'  => 'require',//户名
            'card'  => 'require|alphaNum',//账号
            'limit_once_min' => 'number',//单笔最低金额
            'limit_once_max' => 'number',//单笔最高金额
            'limit_day_max' => 'number',//日停用金额
            'limit_max'     => 'number',//总停用金额
            'state'         => 'require|in:enabled,default',
        ]);

        $validate->paramsCheck('', $this->request, $this->response);
        $param['comment'] = isset($param['comment']) ? strip_tags(trim($param['comment'])) : '';//渠道备注
        $param['bank_id'] = $param['bank_id'] == null ? '' : $param['bank_id'];
        if($param['type'] == 1 && empty($param['bank_id'])){
            return $this->lang->set(886, ['请选择银行']);
        }
        if($param['type'] != 1 && empty($param['qrcode'])){
            return $this->lang->set(886, ['请上传收款二维码']);
        }

//        $types = [
//            '1' => '银行转账',
//            '2' => '支付宝',
//            '3' => '微信',
//            '4' => 'QQ钱包',
//            '5' => '京东支付',
//        ];

//        $card = \Utils\Utils::RSADecrypt($param['card']);
        $param['creater'] = $this->playLoad['uid'];
        if (DB::table('bank_account')->insert($param)) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};
