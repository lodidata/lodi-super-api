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
        'channel_id' => 'int #渠道ID'
    ];
    const SCHEMAs     = [
        200 => ['map'],
    ];

    public function run($id)
    {
        $this->checkID($id);
        $ip = $this->request->getParam('ip');
        $channel_id = $this->request->getParam('channel_id');
        //数据表做了唯一约束 customer_id  channel_code ip
        if ($ip) {
            $ip = \Utils\Utils::RSAEncrypt($ip);
            $data = ['ip' => $ip];
            if (!empty($channel_id)) {
                $db = DB::connection('pay')->table('callback_ip_white')->where('id', $id)->first();
                if ($db->channel_id != $channel_id) {
                    //更改渠道channel
                    $channel = DB::connection('pay')->table('pay_channel')->where('id', $channel_id)->first();
                    if ($channel) {
                        $data['channel_id'] = $channel_id;
                        $data['channel_code'] = $channel->code;
                    }
                }
            }

            $re = DB::connection('pay')->table('callback_ip_white')->where('id', $id)->update($data);
            if ($re)
                return $this->lang->set(0);
            return $this->lang->set(20);
        }
        return $this->lang->set(-2);
    }

};
