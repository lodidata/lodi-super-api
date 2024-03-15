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
    const TITLE       = '更改渠道回调IP白名单';
    const DESCRIPTION = '';
    const HINT        = '';
    const QUERY       = [
        'id' => 'int #id'
    ];
    const TYPE        = 'text/json';
    const PARAMs      = [
        'ip' => 'string(required) #IP',
    ];
    const SCHEMAs     = [
        200 => ['map'],
    ];

    public function run($id)
    {
        $ip = $this->request->getParam('ip');
        $ip = \Utils\Utils::RSAEncrypt($ip);
        //数据表做了唯一约束 customer_id  channel_code ip
        try{
            $re = DB::table('callback_ip_white')->where('id',$id)->update(['ip'=>$ip]);
            if($re)
                return true;
        }catch (\Exception $e){
            return false;
        }
        return false;
    }

};
