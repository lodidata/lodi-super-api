<?php
/**
 * vegas2.0
 *
 * @auth *^-^*<dm>
 * @copyright XLZ CO.
 * @package
 * @date 2017/4/11 18:27
 */

return new class() extends Logic\Admin\BaseController
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
        'ip' => 'string(required) #的IP',
    ];
    const SCHEMAs     = [
        200 => ['map'],
    ];

    public function run($id)
    {
        $this->checkID($id);
        $ip = $this->request->getParam('ip');
        //数据表做了唯一约束 customer_id  channel_code ip
        if($ip) {
            $ip = \Utils\Utils::RSAEncrypt($ip);
            $re = DB::connection('pay')->table('callback_ip_white')->where('id', $id)->update(['ip' => $ip]);
            if ($re)
                return $this->lang->set(0);
            return $this->lang->set(20);
        }
        return $this->lang->set(-2);
    }

};
