<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/3
 * Time: 16:35
 */

use Illuminate\Support\Facades\Schema;

/**
 * 删除支付类型信息
 */
return new class extends \Logic\Admin\BaseController
{
    const TITLE = 'DELETE 删除支付类型信息';
    const DESCRIPTION = '删除支付类型信息';
    const HINT = '';
    const QUERY = [
        'pass_id' => '支付渠道id',
    ];
    const TYPE = 'text/json';
    const PARAMs = [];
    const SCHEMAs = [
        200 => [
        ]
    ];
    public function run($pass_id='')
    {
        $this->checkID($pass_id);
        $result = DB::connection('pay')
            ->table('passageway')
            ->where('id',$pass_id)
            ->update(['status'=>'deleted']);
        if ($result!==false) {
            return $this->lang->set(0);
        }
        return $this->lang->set(-2);
    }
};