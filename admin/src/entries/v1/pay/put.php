<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/3
 * Time: 9:53
 */

return new class extends Logic\Admin\BaseController
{

    const TITLE = 'PUT 支付渠道信息编辑';
    const DESCRIPTION = '支付渠道信息编辑';
    const HINT = '';
    const QUERY = [
    ];
    const TYPE = 'text/json';
    const PARAMs = [
        'customer_id' => 'integer(required)#客户id',
        'channel_id' => 'integer(required)#支付渠道id',
        'partner_id' => 'integer(required)#商户号',
        'pub_key' => 'string(required)#公钥',
        'key' => 'string(optional)#秘钥',
        'app_id' => 'string(optional)#appID',
        'app_secret' => 'string(optional)#app_secret',
        'app_site' => 'string(optional)#app_site',
        'token' => 'string(optional)#token',
        'terminal' => 'string(optional)#terminal'
    ];
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
        $channel_id = $this->request->getParam('channel_id');
        if($channel_id) {
            $rule = DB::connection('pay')->table('pay_channel')->where('id', $channel_id)->value('desc');
            $arr = explode(',',$rule);
            $validate = (new \Lib\Validate\Admin\PayValidate());
            $arr[] = 'customer_id';
            $arr[] = 'channel_id';
            $arr = $validate->setValidate($arr,'post');
            $validate->paramsCheck('post', $this->request, $this->response);
            $arr = array_unique(array_merge($arr,array_keys(self::PARAMs)));
            //判断是否有相同数据
            $repeat = DB::connection('pay')->table('pay_config')->where('status','!=','deleted');
            $data = [];
            foreach ($arr as $item) {
                $data[$item] = $this->request->getParam($item);
                //只需要判断商户号唯一就行
//                $repeat->where($item, $this->request->getParam($item));
            }
            #加密存储
            $data = \Logic\Recharge\Recharge::encryptConfig($data);
            $repeat->where('customer_id', $data['customer_id'])
                ->where('partner_id', $data['partner_id'])
                ->where('channel_id', $data['channel_id']);
            if ( $repeat->count() > 1) {
//                $repeat->update($data);
//                return $this->lang->set(0);
                return $this->lang->set(8);
            } else {
                $result = DB::connection('pay')->table('pay_config')->where('id',$id)->update($data);
                //更新所属厅主时，相应通道也更新所属厅主
                DB::connection('pay')->table('passageway')->where('pay_config_id',$id)->update(['customer_id'=>$data['customer_id']]);
                if ($result) {
                    return $this->lang->set(0);
                }
            }
        }
        return $this->lang->set(-2);
    }

};