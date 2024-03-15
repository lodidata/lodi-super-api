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
    const TITLE       = '删除渠道回调IP白名单';
    const DESCRIPTION = '';
    const HINT        = '';
    const QUERY       = [
        'id' => 'int #id'
    ];
    const TYPE        = 'text/json';
    const PARAMs      = [
    ];
    const SCHEMAs     = [
        200 => ['map'],
    ];

    public function run($id)
    {
        //数据表做了唯一约束 customer_id  channel_code ip
            $re = DB::table('callback_ip_white')->delete($id);
            if($re)
                return true;
            return false;
    }

};
