<?php
/**
 * vegas2.0
 *
 * @auth *^-^*<dm>
 * @copyright XLZ CO.
 * @package
 * @date 2017/4/11 18:27
 */
use Utils\Www\Action;

/**
 * 更改第三方支付
 */
return new class() extends Action
{

    const STATE       = '';
    const TITLE       = '更改第三方支付';
    const DESCRIPTION = '目前只支持更改状态';
    const HINT        = '';
    const QUERY       = [
        'id' => 'int #第三方支付id'
    ];
    const TYPE        = 'text/json';
    const PARAMs      = [
        'status' => 'int(required) #状态, 1:启用， 0:停用',
    ];
    const SCHEMAs     = [
        200 => ['map'],
    ];

    public function run($id = null)
    {


        $status = $this->request->getParam('status');
        $status = $status ? 'enabled' : 'disabled';

        $customer =  DB::table('customer')->select(['id'])->where('customer',CUSTOMER)->first();

        $rs = DB::table('passageway')
            ->where('customer_id', $customer->id)
            ->where('id', $id)
            ->update(['status' => $status]);

        return $rs;
    }

};
