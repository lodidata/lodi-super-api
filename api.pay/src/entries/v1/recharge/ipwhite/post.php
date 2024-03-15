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
    const TITLE       = '新增渠道回调IP白名单';
    const DESCRIPTION = '';
    const HINT        = '';
    const QUERY       = [
    ];
    const TYPE        = 'text/json';
    const PARAMs      = [
        'ip' => 'string(required) #加密后的IP',
        'channel_id' => 'string() #渠道code',
        'channel_code' => 'string() #渠道id',
    ];
    const SCHEMAs     = [
        200 => ['map'],
    ];

    public function run()
    {
        $data['ip'] = $this->request->getParam('ip');
        $data['ip'] = \Utils\Utils::RSAEncrypt($data['ip']);
        $data['customer_id'] = CUSTOMERID;
        $data['channel_id'] = $this->request->getParam('channel_id');
        $data['channel_code'] = $this->request->getParam('channel_code');
        //数据表做了唯一约束 customer_id  channel_code ip
        try{
            if($data['channel_id']){
                $data['channel_code'] = \DB::table('pay_channel')->where('id',$data['channel_id'])->value('code');
            }else if($data['channel_code']) {
                $data['channel_id'] = \DB::table('pay_channel')->where('code',$data['channel_code'])->value('id');
            }
            $re = DB::table('callback_ip_white')->insertGetId($data);
            if($re)
                return true;
        }catch (\Exception $e){
            return false;
        }
        return false;
    }

};
