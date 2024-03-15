<?php
use Logic\Admin\BaseController;
//use Logic\Admin\Log;
//use function Setting\Las\RSADecrypt;

return new class() extends BaseController {
    const TITLE = "修改收款银行帐号";
    const TYPE = "text/json";
    const PARAMs = [
        "usage"         => "string(required)    #使用层级",
        "name"          => "string #账户名",
        "card"          => "string(required) #帐号",
        "qrcode"        => "string(optional)   #二维码，如果没有二维码传空(null)",
        "limit_day_max" => "int(required) #每日最大存款",
        "limit_max"     => "int(required) #总存款限额",
        "sort"          => "int # 排序",
        "is_enabled"    => "int #启用 1，停用 0",
        "comment"       => "string(required) #存款说明",
    ];
    const QUERY = [
        "id" => "int(optional) #修改银行账户时传递id",
    ];

    //前置方法
    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id = null) {
        $this->checkID($id);
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

        /*================================日志操作代码=================================*/
//        $types = [
//            '1' => '银行转账',
//            '2' => '支付宝',
//            '3' => '微信',
//            '4' => 'QQ钱包',
//            '5' => '京东支付',
//        ];
//        $card = \Utils\Utils::RSADecrypt($param['card']);
        /*==================================================================================*/
        /*---操作日志代码---*/
        $data = DB::table('bank_account')->where('id', '=', $id)->get()->first();
        if(empty($data)){
            return $this->lang->set(886, ['收款账户不存在']);
        }
//        $data = (array)$data;
        if (\DB::table('bank_account')->where('id', '=', $id)->update($param) !== false){
//            /*================================日志操作代码=================================*/
//            $name_arr = [
//                'card'           => '账号',
//                'name'           => '户名',
//                'limit_day_max'  => '日停用金额',
//                'limit_max'      => '总停用金额',
//                'limit_once_max' => '单笔最高金额',
//                'limit_once_min' => '单笔最低金额',
//                'sort'           => '排序',
//                'state'          => '状态',
//                'comment'        => '渠道备注',
//                'qrcode'         => '收款二维码',
//                'type'           => '类型',
//            ];
//
//            $status = [
//                'enabled' => '启用',
//                'default' => '停用',
//            ];
//            $bank_arr = [
//                '1' => '银行转账',
//                '2' => '支付宝',
//                '3' => '微信',
//                '4' => 'QQ钱包',
//                '5' => '京东支付',
//
//            ];
//            if ($param['type'] == 1) {
//                $name_arr['address'] = '开户行';
//                $name_arr['bank_id'] = '银行/支付名称';
//            }
//
//            $str = "";
//            foreach ($data as $key => $item) {
//                foreach ($name_arr as $key2 => $item2) {
//                    if ($key == $key2) {
//                        if ($item != $param[$key2]) {
//                            if ($key2 == 'limit_day_max' || $key2 == 'limit_max' || $key2 == 'limit_once_max' || $key2 == 'limit_once_min') {
//                                $str .= "/" . $name_arr[$key2] . ":[" . ($item / 100) . "]更改为[" . ($param[$key2] / 100) . "]";
//                            } else if ($key2 == 'card') {
//                                $str .= "/" . $name_arr[$key2] . ":[" . \Utils\Utils::RSADecrypt($item) . "]更改为[" . \Utils\Utils::RSADecrypt($param[$key2]) . "]";
//                            } else if ($key2 == 'state') {
//                                $str .= "/" . $name_arr[$key2] . ":[" . $status[$item] . "]更改为[" . $status[$param[$key2]] . "]";
//                            } else if ($key2 == 'type') {
//                                $str .= "/" . $name_arr[$key2] . ":[" . $bank_arr[$item] . "]更改为[" . $bank_arr[$param[$key2]] . "]";
//                            } else if ($key2 == 'bank_id') {
//                                $bank_old = \DB::table('bank')
//                                               ->where('type', '=', 1)
//                                               ->whereRaw('FIND_IN_SET("enabled",status)')
//                                               ->where('id', '=', $item)
//                                               ->orderBy('sort', 'DESC')
//                                               ->get(['id', 'name', 'code', 'logo as img', 'status'])
//                                               ->first();
//                                $bank = \DB::table('bank')
//                                           ->where('type', '=', 1)
//                                           ->whereRaw('FIND_IN_SET("enabled",status)')
//                                           ->where('id', '=', $param['bank_id'])
//                                           ->orderBy('sort', 'DESC')
//                                           ->get(['id', 'name', 'code', 'logo as img', 'status'])
//                                           ->first();
//                                $str .= "/" . $name_arr[$key2] . ":[" . $bank_old->name . "]更改为[" . $bank->name . "]";
//                            } else {
//                                $str .= "/" . $name_arr[$key2] . ":[{$item}]更改为[{$param[$key2]}]";
//                            }
//                        }
//                    }
//                }
//            }
//
//            (new Log($this->ci))->create(null, null, Log::MODULE_CASH, '收款账号', '收款账号', '编辑', 1, "类型:{$types[$param['type']]}/户名：{$param['name']}/账号：$card/$str");
            /*==================================================================================*/
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};
