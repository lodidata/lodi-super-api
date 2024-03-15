<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/6/29
 * Time: 11:42
 */


/**
 * 支付渠道删除（伪删除）
 */
return new class extends Logic\Admin\BaseController
{
    const TITLE = 'DELETE 支付渠道删除';
    const DESCRIPTION = '支付渠道删除';
    const HINT = '';
    const QUERY = [
        'pay_con_id' => '支付渠道ID'
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
        ]
    ];


    protected $beforeActionList = [
        'verifyToken', 'authorize',
    ];

    public function run($id)
    {
        $this->checkID($id);
        $passageway = DB::connection('pay')->table('passageway')->where('pay_config_id',$id)
            ->where('status','!=','deleted')->count();
        if($passageway <= 0) {
//            $result = DB::connection('pay')->table('pay_config')
//                ->where('id', $id)
//                ->update(['status' => 'deleted']);

            $result = DB::connection('pay')->table('pay_config')
                ->delete($id);
            if ($result !== false) {
                return $this->lang->set(0);
            }
            return $this->lang->set(-2);
        }
        return $this->lang->set(12);
    }

};