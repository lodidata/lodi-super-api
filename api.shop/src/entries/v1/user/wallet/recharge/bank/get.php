<?php
use Utils\Shop\Action;
/**
 * 返回包括可参加活动、支持的银行列表、最低、最高充值额度、提示等信息
 */
return new class extends Action {
    const TITLE = "银行列表，及存款方式";
    const TYPE = "text/json";

    public function run(){
        $customerData = [
            'type' => [
                ['name' => '网银转账', 'id' => '1'],
                ['name' => 'ATM柜员机', 'id' => '2'],
                ['name' => 'ATM现金入款', 'id' => '3'],
                ['name' => '银行柜台', 'id' => '4'],
                ['name' => '手机转账', 'id' => '5'],
                ['name' => '支付宝转账', 'id' => '6'],
                ['name' => '微信转账', 'id' => '7'],
                ['name' => '财付通转账', 'id' => '8'],
                ['name' => '京东转账', 'id' => '9'],
            ]
        ];
        $customerData['list'] = DB::table('bank')->where('type', '1')->whereRaw('FIND_IN_SET("enabled",status)')
            ->select(['id','name as bank_name', 'name' ,'code','logo'])
            ->paginate(999)->toArray()['data'];
        return $customerData;
    }
};
