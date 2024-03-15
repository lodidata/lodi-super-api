<?php
/**
 * vegas2.0
 *
 * @auth *^-^*<dm>
 * @copyright XLZ CO.
 * @package
 * @date 2017/4/11 18:27
 */

/**
 * 更改第三方支付
 */
return new class() extends Logic\Admin\BaseController
{

    const STATE       = '';
    const TITLE       = '新增渠道回调IP白名单';
    const DESCRIPTION = '';
    const HINT        = '';
    const QUERY       = [
    ];
    const TYPE        = 'text/json';
    const PARAMs      = [
        'ip' => 'string(required) #IP',
        'channel_id' => 'int() #渠道ID',
        'customer_id' => 'int() #客户ID',
    ];
    const SCHEMAs     = [
        200 => ['map'],
    ];

    public function run()
    {
        $data['ip'] = $this->request->getParam('ip');
        $data['customer_id'] = $this->request->getParam('customer_id') ?? 0;
        $data['channel_id'] = $this->request->getParam('channel_id');
        (new \Lib\Validate\BaseValidate([
            'ip'=>'require',
//            'customer_id'=>'require',
            'channel_id'=>'require',
        ]))->paramsCheck('',$this->request,$this->response);
        try{
            $data['ip'] = \Utils\Utils::RSAEncrypt($data['ip']);
            if($data['channel_id']){
                $data['channel_code'] = \DB::connection('pay')->table('pay_channel')->where('id',$data['channel_id'])->value('code');
            }
            $re = \DB::connection('pay')->table('callback_ip_white')->insertGetId($data);
            if($re)
                return $this->lang->set(0);
        }catch (\Exception $e){
            return $this->lang->set(20);
        }
        return $this->lang->set(-2);
    }

};
